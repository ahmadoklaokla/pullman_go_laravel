<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\BusResource\Pages;
use App\Models\Bus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BusResource extends Resource
{
    // عشان company_id ينزل تلقائياً
    protected static bool $isScopedToTenant = true;

    protected static ?string $model = Bus::class;
    protected static ?string $navigationIcon = 'heroicon-s-truck'; 
    protected static ?string $navigationLabel = 'باصات الشركة';
    protected static ?string $pluralModelLabel = 'الباصات';
    protected static ?string $modelLabel = 'باص';



public static function form(Form $form): Form
{
    return $form
        ->schema([

            Forms\Components\Section::make('معلومات الباص الأساسية')
                ->description('أدخل بيانات الباص التابع لشركتك .')

                ->schema([

                    Forms\Components\Select::make('route_id')
                        ->label('تحديد المسار (الخط)')
                        ->relationship(
                            name: 'route', 
                            titleAttribute: 'id', // تأكد إن هذا اسم الحقل اللي فيه اسم المسار عندك

                            // مشان صاحب الشركة يشوف بس مساراته (modifyQueryUsing)
                            modifyQueryUsing: fn (Builder $query) => $query->where('company_id', auth()->user()->company_id), 
                        )


                        // بيعرض من مدينة كذا الى مدينة كذا في القائمة
                        ->getOptionLabelFromRecordUsing(fn ($record) => 

                        // 'غير معروف مشان ما يعطيني خطأ 
                            "من مدينة " . ($record->departureCity->name ?? 'غير معروف') . 
                            " إلى مدينة " . ($record->arrivalCity->name ?? 'غير معروف')
                        )


                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('اختر المسار الذي سيعمل عليه هذا الباص'),




                    Forms\Components\Grid::make(2) // تقسيم لعمودين
                        ->schema([
                            // 1. رقم اللوحة
                            Forms\Components\TextInput::make('bus_number')
                                ->label('رقم اللوحة  ')
                                ->required()
                                 ->placeholder('أدخل رقم لوحة الباص')
                                ->unique(ignoreRecord: true),


                            //   رقم الباص  
                            Forms\Components\TextInput::make('bus_numbernnn')
                                ->label('رقم الباص  ')
                                ->required()
                                ->placeholder(''),


                            // 3. عدد المقاعد
                            Forms\Components\TextInput::make('total_seats')
                                ->label('عدد المقاعد')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                                


                            // 4. موديل الباص
                            Forms\Components\TextInput::make('bus_model')
                                ->label('موديل الباص')
                                ->required(),
                                



                                // 👈 اختيار السائق من الحسابات المتاحة للشركة
                                Forms\Components\Select::make('driver_id')
                                    ->label('السائق المخصص')
                                    ->relationship(
                                        name: 'driver',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn ($query) => $query->where('company_id', auth()->user()->company_id)->where('role', 'driver')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->placeholder('اختر سائقاً لهذا الباص'),




                                Forms\Components\TextInput::make('assistant_name')
                                    ->label('اسم المعاون (اختياري)')
                                    ->placeholder('الاسم الكامل للمعاون'),



                                Forms\Components\TextInput::make('assistant_phone')
                                    ->label('رقم هاتف المعاون (اختياري)')
                                    ->tel()
                                    ->extraInputAttributes(['style' => 'direction: ltr !important; text-align: left;'])
                                    ->maxLength(15),




                            // 5. حالة الباص
                            Forms\Components\Select::make('status')
                                ->label('حالة الباص')
                                ->options([
                                    'active' => 'يعمل (متاح)',
                                    'maintenance' => 'في الصيانة',
                                    'inactive' => 'خارج الخدمة',

                                ])


                                ->default('active')
                                ->required(),

                        ]),

                ]),

        ]);


}




    // الباصات تابعة لشركة وحدة فقط
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', auth()->user()->company_id);
    }







   public static function table(Table $table): Table
    {
        return $table

            ->columns([

            Tables\Columns\TextColumn::make('bus_numbernnn')
                ->label('رقم الباص')
                ->searchable()
                ->alignCenter()
                ->sortable()
                ->weight('bold')
                ->color('success')
                ->copyable(), // ميزة حلوة ينسخ الرقم بكبسة وحدة



            // رقم الباص بخط عريض
            Tables\Columns\TextColumn::make('bus_number')
                ->label('رقم اللوحة')
                ->searchable()
                ->sortable()
                ->alignCenter()
                ->weight('bold')
                ->copyable(), // ميزة حلوة ينسخ الرقم بكبسة وحدة






                // 👈 جلب اسم السائق من العلاقة
                Tables\Columns\TextColumn::make('driver.name')
                    ->label('السائق')
                    ->icon('heroicon-m-user')
                    ->alignCenter()
                    ->color('success')
                    ->searchable(),


                // 👈 جلب هاتف السائق من العلاقة
                Tables\Columns\TextColumn::make('driver.phone')
                    ->label('هاتف السائق')
                    ->searchable()
                    ->alignCenter()
                    ->extraAttributes(['style' => 'direction: ltr !important; text-align: right;'])
                    ->copyable()
                    ->color('success'),



                

            // عدد المقاعد مع أيقونة صغيرة
            Tables\Columns\TextColumn::make('total_seats')
                ->label('المقاعد')
                ->color('warning')
                ->alignCenter()
                ->icon('heroicon-m-users')
                ->sortable(),



            // العمود الجديد للمسار
            Tables\Columns\TextColumn::make('route')
                ->label('المسار (الخط)')
                ->alignCenter()
                ->formatStateUsing(function ($record) {
                    // منجيب بيانات المسار عن طريق العلاقة
                    $route = $record->route;
                    
                    if (!$route) return 'غير محدد';

                    // بنادي أسماء المدن من علاقات مودل المسار
                    return "من " . ($route->departureCity->name ?? '؟') . 
                           " إلى " . ($route->arrivalCity->name ?? '؟');
                })
                ->color('info') //  لون أزرق خفيف  
                ->icon('heroicon-m-map-pin'), // أيقونة لوكيشن صغيرة



            // العمود الخاص باسم المعاون بلون برتقالي
            Tables\Columns\TextColumn::make('assistant_name')
                ->label('المعاون')
                ->icon('heroicon-m-user')
                ->color('warning')
                ->searchable()
                ->default('لا يوجد'),




            // الحالة ملونة (اخضر، اصفر، احمر)
            Tables\Columns\TextColumn::make('status')
                ->label('الحالة')
                ->alignCenter()
                ->badge() // بيعملها مثل كبسولة ملونة
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success',      // أخضر
                    'maintenance' => 'warning',  // أصفر
                    'inactive' => 'danger',     // أحمر
                    default => 'gray',
                })


                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'active' => 'يعمل',
                    'maintenance' => 'صيانة',
                    'inactive' => 'متوقف',
                    default => $state,
                }),
        ])





            ->filters([

                // فلتر سريع حسب الحالة
            Tables\Filters\SelectFilter::make('status')
                ->label('تصفية حسب الحالة')
                ->options([
                    'active' => 'يعمل',
                    'maintenance' => 'صيانة',
                    'inactive' => 'متوقف',
                ]),


            ], layout: Tables\Enums\FiltersLayout::AboveContent)  // عشان يظهر الفلتر فوق الجدول




            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(), // صاحب الشركة مسموح له يحذف باصه
            ])


            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])

            
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
            'index' => Pages\ListBuses::route('/'),
            'create' => Pages\CreateBus::route('/create'),
            'edit' => Pages\EditBus::route('/{record}/edit'),
        ];
    }
}
