<?php

namespace App\Filament\CompanyOwner\Resources;

use App\Filament\CompanyOwner\Resources\TripResource\Pages;
use App\Filament\CompanyOwner\Resources\TripResource\RelationManagers;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationLabel = 'عرض الرحلات';
    protected static ?string $pluralModelLabel = ' الرحلات';


    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';





    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }




    // مشان يعرضلي فقط الرحلات التابعة لهاد المسار الي تابع لهي الشركة 
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->whereHas('route', function ($query) {
            $query->where('company_id', auth()->user()->company_id);
        })
        // مشان ما يطلعلي سجلات كثير من الرحلات عند الموظف لانو فقط بال db 
        ->whereIn('id', function ($query) {
            $query->selectRaw('MIN(id)')
                ->from('trips')
                ->groupBy('route_id', 'bus_id', 'scheduled_time');
        });
}


    



    public static function table(Table $table): Table
    {
        return $table
        
            ->columns([

                // 1. المسار (من مدينة إلى مدينة)
            Tables\Columns\TextColumn::make('route')
                ->label('المسار')
                ->alignCenter()
                ->formatStateUsing(fn ($record) => 
                    "من " . ($record->route->departureCity->name ?? '؟') . 
                    " إلى " . ($record->route->arrivalCity->name ?? '؟')
                )
                ->description(fn ($record) => "سعر التذكرة: " . number_format($record->route->base_price) . " ل.س")

                ->searchable(query: function ($query, string $search) {
                    return $query->whereHas('route', function ($q) use ($search) {
                        $q->whereHas('departureCity', fn($city) => $city->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('arrivalCity', fn($city) => $city->where('name', 'like', "%{$search}%"));
                    });

    })
            ->icon('heroicon-m-map-pin')
            ->color('success')
            ->iconColor('primary'),



            // 2. الباص
            Tables\Columns\TextColumn::make('bus.bus_numbernnn')
                ->label('رقم الباص')
                ->alignCenter()
                ->description(fn ($record) => "السائق: " . $record->bus->driver?->name ?? 'غير متوفر'),



            // 3. وقت الانطلاق (تنسيق 12 ساعة مع AM/PM)
            Tables\Columns\TextColumn::make('scheduled_time')
                ->label('موعد الانطلاق')
                ->alignCenter()
                ->time('h:i A') // بيعرض الوقت مثلاً 09:00 AM
                ->color('primary')
                ->weight('bold'),





            Tables\Columns\TextColumn::make('days_of_week')
                ->label('أيام الرحلة')
                ->alignCenter()
                ->html()

                ->formatStateUsing(function ($record) {
                    // تحويل البيانات لمصفوفة  (سواء كانت مخزنة Array أو JSON)
                    $days = $record->days_of_week;
                    if (!is_array($days)) {
                        $days = json_decode($days, true) ?? [];
                    }
                    // خريطة تحويل الأرقام لأسماء الأيام
                    $daysMap = [
                        0 => 'الأحد', 
                        1 => 'الإثنين', 
                        2 => 'الثلاثاء', 
                        3 => 'الأربعاء',
                        4 => 'الخميس', 
                        5 => 'الجمعة', 
                        6 => 'السبت'
                    ];
                    // تحويل كل يوم لكبسولة خضراد
                    $badges = collect($days)->map(function ($day) use ($daysMap) {
                        $dayName = $daysMap[$day] ?? 'غير معروف';
                        return "<span style='white-space: nowrap; border: 1px solid #22c55e; background-color: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 2px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: bold;'>{$dayName}</span>";
                    })->implode(' ');

                    // الحاوية الخارجية بتجبرهم يضلوا بسطر واحد (nowrap) وبتوسطهم بالخلية
                    return "<div style='display: flex; flex-wrap: nowrap; justify-content: center; gap: 6px; white-space: nowrap;'>{$badges}</div>";
                }),


            

            Tables\Columns\TextColumn::make('route.distance')
                ->label('المسافة (كم)')
                ->alignCenter()
                ->formatStateUsing(fn ($state) => number_format($state)) 
                ->color('gray'),


            Tables\Columns\TextColumn::make('route.estimated_time')
                ->label(' الوقت التقريبي للرحلة')
                ->alignCenter()
                ->color('gray'),



            Tables\Columns\TextColumn::make('route.base_price')
                ->label('سعر التذكرة ')
                ->alignCenter()
                ->formatStateUsing(fn ($state) => number_format($state) . " ل.س") 
                ->color('success') // بخلي السعر لونه أخضر
                ->weight('bold'),



//  ساستخدمها للحجز لاحقا 

            // Tables\Columns\TextColumn::make('trip_date')
            //     ->label('تاريخ الرحلة')

            //     // translatedFormat('l'): بتجيب اسم اليوم باللغة العربية
            //     ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->translatedFormat('l Y-m-d'))
            //     ->color('success')
            //     ->weight('bold'),   // بيخلي الخط عريض,




            // 5. حالة الرحلة (مافي داعي لهاد الكود بس للاحتياط)
            Tables\Columns\ToggleColumn::make('is_active')
                ->alignCenter()
                ->label('نشطة')

                // مشان لما اعدل هاد الزر من الجدول ينحفظ او يمر بصفحة التعديل تبعيت الرحلات Edit.php
                ->updateStateUsing(function ($record, $state) {
                    // 1. بنحدث السطر الحالي
                    $record->update(['is_active' => $state]);
                    
                    // 2. بنحدث كل الرحلات المكررة التابعة إله
                    \App\Models\Trip::where('route_id', $record->route_id)
                        ->where('bus_id', $record->bus_id)
                        ->where('scheduled_time', $record->scheduled_time)
                        ->update(['is_active' => $state]);
                }),

            

            Tables\Columns\TextColumn::make('created_at')
                ->label('تاريخ إنشاء الرحلة')
                ->alignCenter()
                ->dateTime('Y-m-d H:i') // هون بحدد التنسيق 
                ->color('success'), // لون اخضر




            ])




            ->filters([

            
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


//  empty('0') بترجع true (يعني بيعتبرها فاضية)، وهاد اللي كان يخرب عليك يوم الأحد.
// filled('0') بترجع true (بيعتبرها فيها قيمة)، وهيك السيستم بيعرف إنك قاصد يوم الأحد وبنفذ الفلترة صح

            Tables\Filters\SelectFilter::make('day')
                    ->label('الفلترة حسب اليوم')
                    ->options([
                        '0' => 'الأحد',
                        '1' => 'الإثنين',
                        '2' => 'الثلاثاء',
                        '3' => 'الأربعاء',
                        '4' => 'الخميس',
                        '5' => 'الجمعة',
                        '6' => 'السبت',
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        // التعديل هون: بنستخدم filled بدل empty عشان الصفر ما يضيع
                        if (filled($data['value'])) { 
                            return $query->where(function ($q) use ($data) {
                                $q->whereJsonContains('days_of_week', $data['value'])
                                ->orWhereJsonContains('days_of_week', (int) $data['value']);
                            });
                        }
                        return $query;
                    }),

            ], layout: Tables\Enums\FiltersLayout::AboveContent)  // عشان يظهر الفلتر فوق الجدول



            ->actions([

            
                // مشان لما احذف سطر واحد من جدول الرحلات يحذفلي كلشي رحلات مكررة لهاد السجل (السطر)
                Tables\Actions\DeleteAction::make()
                    ->action(function ($record) {
                        // رح نحذف كل الرحلات اللي إلها نفس المسار والباص وموعد الانطلاق تبع السطر اللي ضغطت عليه
                        \App\Models\Trip::where('route_id', $record->route_id)
                            ->where('bus_id', $record->bus_id)
                            ->where('scheduled_time', $record->scheduled_time)
                            ->delete();
                    }),
            ])





    

            
            ->bulkActions([
                
                Tables\Actions\BulkActionGroup::make([

                ]),
            ])

            // هي مشان تطلع احدث الرحلات اول شي 
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
            'index' => Pages\ListTrips::route('/'),
        ];
    }
}
