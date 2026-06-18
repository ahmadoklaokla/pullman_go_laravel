<?php

namespace App\Filament\CompanyOwner\Resources;

use App\Filament\CompanyOwner\Resources\OfferResource\Pages;
use App\Filament\CompanyOwner\Resources\OfferResource\RelationManagers;
use App\Models\Offer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;



                public static function canCreate(): bool
        {
            return false; // إخفاء زر "إضافة سعر" نهائياً
        }


                public static function canDeleteAny(): bool
        {
            return false; // منع الحذف
        }




    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    // المسمى في القائمة الجانبية
    protected static ?string $navigationLabel = ' صفحة العروض';

    // المسمى عند عرض كل الرسائل
    protected static ?string $pluralModelLabel = ' العروض';

    // المسمى عند إنشاء رسالة واحدة
    protected static ?string $modelLabel = 'عرض';





    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }


    // العروض تابعة لشركة وحدة فقط
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', auth()->user()->company_id);
    }



    public static function table(Table $table): Table
    {
        return $table

            ->columns([
                
                Tables\Columns\TextColumn::make('route')
                ->label(' المسار')
                ->alignCenter()
                ->formatStateUsing(fn ($record) => "من {$record->route->departureCity->name} إلى {$record->route->arrivalCity->name}")

                ->searchable(query: function (Builder $query, string $search): Builder {
        return $query->whereHas('route.departureCity', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                     ->orWhereHas('route.arrivalCity', fn ($q) => $q->where('name', 'like', "%{$search}%"));
    })
                ->icon('heroicon-m-map-pin')
                ->color('success')
                ->iconColor('primary'),





                Tables\Columns\TextColumn::make('trip_id')
                    ->label('الرحلات المشمولة')
                    ->alignCenter()
                    ->badge() 
                    ->color('success') 
                    ->getStateUsing(function ($record) {
                        
                        $rawState = $record->trip_id;

                        $tripIds = is_string($rawState) ? json_decode($rawState, true) : $rawState;
                        if (!is_array($tripIds)) {
                            $tripIds = $tripIds ? [$tripIds] : []; 
                        }

                        if (empty($tripIds)) {
                            $trips = \App\Models\Trip::where('route_id', $record->route_id)->get();
                        } else {
                            $trips = \App\Models\Trip::whereIn('id', $tripIds)->get();
                        }

                        return $trips->map(function ($trip) {
                            // تنسيق الوقت
                            $time = \Carbon\Carbon::parse($trip->scheduled_time)->format('g:i A');
                            // تنسيق التاريخ اللي طلبته
                            $date = \Carbon\Carbon::parse($trip->trip_date)->format('Y-m-d');
                            
                            // النتيجة النهائية بالباج
                            return "الرحلة رقم {$trip->id} | التاريخ: {$date} | وقت الإنطلاق: {$time}";
                        })->toArray(); 
                    }),








            Tables\Columns\TextColumn::make('route.base_price')
                ->label('السعر قبل الخصم (الأساسي)')
                ->alignCenter()

                //  number_format($state) إضافة الفواصل تلقائياً كل 3 أرقام.
                // <s>...</s>:بتعمل السطر المشطوب
                ->formatStateUsing(fn ($state) => "<s>" . number_format($state) . " ل.س</s>")  // بيطلع السعر القديم مشطوب

                // حطيت ال  HTML هون لانو في عندي وسم الشطب فوق 
                ->html()
                ->color('gray'),  




            Tables\Columns\TextColumn::make('offer_price')
                ->label(' السعر بعد الخصم (العرض)')
                ->alignCenter()

                ->formatStateUsing(fn ($state) => number_format($state) . " ل.س") 
                ->color('success')
                ->weight('bold'),



            Tables\Columns\TextColumn::make('start_date')
                ->label('تاريخ بدء العرض')
                ->alignCenter()
                ->date('Y/m/d'),


            Tables\Columns\TextColumn::make('end_date')
                ->label('تاريخ انتهاء العرض')
                ->alignCenter()
                ->date('Y/m/d'),



                // حقل وهمي مابينحفظ بالداتا بيز

                Tables\Columns\TextColumn::make('remaining_days')
                    ->label('باقي من العرض')
                    ->alignCenter()
                    // هون بنعمل الحسبة الذكية باستخدام Carbon تبع لارافيل

                // بتخليلي الحقل يظهر في الريسلورسيس ولكن بحقل وهمي بالنسبة للداتا بيز
                ->getStateUsing(function ($record) {
                    $startDate = \Carbon\Carbon::parse($record->start_date)->startOfDay();
                    $endDate = \Carbon\Carbon::parse($record->end_date)->startOfDay();
                    $now = \Carbon\Carbon::now()->startOfDay();

                    // 1. إذا العرض لسا ما بدأ: اعرض مدة العرض كاملة
                    if ($now->isBefore($startDate)) {
                        return $startDate->diffInDays($endDate) . ' يوم (لم يبدأ)';
                    }

                    // 2. إذا انتهى العرض
                    if ($now->isAfter($endDate)) {
                        return 'انتهى العرض';
                    }

                    // 3. إذا إحنا حالياً جوا فترة العرض: احسب الباقي من اليوم للنهاية
                    $days = $now->diffInDays($endDate);

                    return $days == 0 ? 'ينتهي اليوم' : $days . ' يوم';
                })

                    // شوية لمسات جمالية عشان يطلع بشكل (باج) ملون
                    ->badge()
                    ->color(function ($record) {
                        $endDate = \Carbon\Carbon::parse($record->end_date);
                        
                        if ($endDate->isPast()) {
                            return 'danger'; // لون أحمر إذا انتهى
                        }
                        
                        if (\Carbon\Carbon::now()->diffInDays($endDate) <= 2) {
                            return 'warning'; // لون أصفر إذا باقي يومين أو أقل
                        }
                        
                        return 'success'; // لون أخضر إذا العرض شغال وباقي وقت
                    }),




            Tables\Columns\IconColumn::make('is_active')
                ->label('حالة العرض')
                ->alignCenter()
                ->boolean(),

            ])
            


            ->filters([

            
            // فلتر العروض المفعلة/غير المفعلة
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('حالة العرض')
                ->placeholder('كل العروض')
                ->trueLabel('المفعلة فقط')
                ->falseLabel('المتوقفة فقط'),


            // فلتر حسب المسار
            Tables\Filters\SelectFilter::make('route_id')
                ->label('تصفية حسب المسار')
                ->options(function () {
                    // بنجيب المسارات وبنفلترها حسب الشركة
                    $query = \App\Models\Route::query();
                    
                    if (auth()->user()->company_id) {
                        $query->where('company_id', auth()->user()->company_id);
                    }

                    // بنرتبهم وبنطلع النص "من ... إلى ..." كقيمة للعرض
                    return $query->with(['departureCity', 'arrivalCity'])
                        ->get()
                        ->pluck('full_route_name', 'id'); 
                        // ملاحظة: إذا ما عندك attribute اسمه full_route_name، استخدم التعديل اللي تحت
                })
                // إذا ما بدك تعمل attribute بالموديل، استخدم هاي الطريقة المباشرة:
                ->options(function () {
                    $routes = \App\Models\Route::query()
                        ->when(auth()->user()->company_id, fn($q) => $q->where('company_id', auth()->user()->company_id))
                        ->get();

                    return $routes->mapWithKeys(function ($route) {
                        return [$route->id => "من " . ($route->departureCity->name ?? '') . " إلى " . ($route->arrivalCity->name ?? '')];
                    });
                })
                ->searchable()
                ->preload(),

            ], layout: Tables\Enums\FiltersLayout::AboveContent)  // عشان يظهر الفلتر فوق الجدول
            

            ->actions([


            Tables\Actions\Action::make('edit_price')
                ->label('تعديل سعر العرض')
                ->icon('heroicon-m-pencil-square')
                ->visible(fn ($livewire) => $livewire->activeTab === 'active_offers')
                ->color('success')
                ->form([
                    Forms\Components\Section::make('تفاصيل العرض الحالي')
                        ->schema([
                            Forms\Components\Grid::make(2)
                                ->schema([
                                    // لازم نستخدم $record للتأكد من وصول البيانات
                                    Forms\Components\Placeholder::make('departure_city')
                                        ->label('من مدينة')
                                        // الوصول للمدينة من خلال علاقة route
                                        ->content(fn ($record) => $record->route?->departureCity?->name ?? '-'),

                                    Forms\Components\Placeholder::make('arrival_city')
                                        ->label('إلى مدينة')
                                        ->content(fn ($record) => $record->route?->arrivalCity?->name ?? '-'),
                                    Forms\Components\Placeholder::make('start_date')
                                        ->label('بداية العرض')
                                        ->content(fn ($record) => $record->start_date ?? '-'),

                                    Forms\Components\Placeholder::make('end_date')
                                        ->label('نهاية العرض')
                                        ->content(fn ($record) => $record->end_date ?? '-'),
                                ]),
                        ]),
                        
                                    Forms\Components\TextInput::make('offer_price')
                                        ->label('سعر العرض الجديد')
                                        ->required()
                                        ->formatStateUsing(fn ($record) => number_format($record->offer_price) . ' ل.س')
                                        ->helperText('أدخل القيمة الجديدة للمسار خلال فترة العرض'),
                ])


                        // تعبئة الحقل بالسعر القديم تلقائياً عند فتح النافذة
                        ->mountUsing(fn (Forms\ComponentContainer $form, $record) => $form->fill([
                            'offer_price' => $record->offer_price,
                        ]))

                        
                        ->action(function ($record, array $data) {
                            $record->update([
                                'offer_price' => $data['offer_price'],
                            ]);


                                    \Filament\Notifications\Notification::make()
                                        ->title('تم تحديث السعر بنجاح')
                                        ->success()
                                        ->send();
                    

                }),

             Tables\Actions\DeleteAction::make(),



            ])

           



            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])

            // مشان تطلع احدث العروض اول شي 
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOffers::route('/'),

        ];
    }
}
