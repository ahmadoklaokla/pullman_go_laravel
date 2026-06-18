<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\DriverResource\Pages;
use App\Models\User; // ربطتو بموديل المستخدمين
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;



class DriverResource extends Resource
{

    // 1. تحديد الموديل الأساسي ليكون User
    protected static ?string $model = User::class;



    protected static ?string $navigationLabel = 'إدارة السائقين';
    protected static ?string $pluralModelLabel = 'السائقين';
    protected static ?string $modelLabel = 'سائق';
    protected static ?string $navigationIcon = 'heroicon-o-user-group';



    // 3. فحص عشان اللوحة تعرض "فقط" المستخدمين اللي رتبتهم سائق
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', 'driver')
            ->where('company_id', auth()->user()->company_id); //  الموظف بشوف بس سائقين شركته
    }



    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

            Forms\Components\Section::make('معلومات السائق الحسابية')
                ->description('أدخل بيانات السائق بدقة لإنشاء حساب نظامي له على التطبيق')
                ->schema([
                
                    Forms\Components\Hidden::make('company_id')
                        ->default(fn () => auth()->user()->company_id), // ربط السائق بشركة الموظف اللي جالس يسجله تلقائياً



                    Forms\Components\Hidden::make('role')
                        ->default('driver'), // تثبيت الرتبة كسائق فوراً

                    
                        // 1. اسم السائق
                    Forms\Components\TextInput::make('name')
                        ->label('اسم السائق كاملاً')
                        ->placeholder('أدخل الاسم الثلاثي')
                        ->required()
                        ->maxLength(255),



                    // 2. رقم هاتف السائق
                    Forms\Components\TextInput::make('phone')
                        ->label('رقم هاتف السائق')
                        ->placeholder('')
                        ->tel()
                        ->required()
                        ->unique(ignoreRecord: true) // يمنع تكرار الرقم في السستم
                        ->maxLength(20),



                    // 3. إيميل السائق (مع التحقق الصارم من الصيغة)
                    Forms\Components\TextInput::make('email')
                        ->label('البريد الإلكتروني')
                        ->placeholder('driver@example.com')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true) // يمنع تكرار الإيميل
                        ->maxLength(255),



                    // 4. كلمة المرور (احترافية وآمنة)
                    Forms\Components\TextInput::make('password')
                        ->label('كلمة المرور')
                        ->password()
                        ->revealable() // يسمح للموظف بإظهار وإخفاء الباسوورد وهو بيكتب
                        // إجبارية فقط عند إنشاء حساب جديد، وتصبح اختيارية عند التعديل
                        ->required(fn (string $context): bool => $context === 'create') 
                        // إذا الموظف عدل بيانات السائق وترك الباسوورد فاضي، السستم ما بعدله وبحافظ ع القديم
                        ->dehydrated(fn ($state) => filled($state)) 
                        // تشفير الباسوورد تلقائياً قبل حفظه في قاعدة البيانات
                        ->dehydrateStateUsing(fn ($state) => \Illuminate\Support\Facades\Hash::make($state))
                        ->maxLength(255),


            ])->columns(2) // (حقلين في كل سطر)


        ]);


    }







    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                
            // 1. اسم السائق مع ميزة البحث
            Tables\Columns\TextColumn::make('name')
                ->label('اسم السائق')
                ->searchable()
                ->color('success')
                ->sortable(),



            // 2. رقم الهاتف مع ميزة البحث
            Tables\Columns\TextColumn::make('phone')
                ->label('رقم الهاتف')
                ->searchable(),



            // 3. البريد الإلكتروني
            Tables\Columns\TextColumn::make('email')
                ->label('البريد الإلكتروني')
                ->color('gray')
                ->searchable(),




            Tables\Columns\IconColumn::make('is_active')
                ->label('حالة الحساب')
                ->boolean() // بيعرض علامة صح خضراء أو خطأ حمراء تلقائياً بناءً على القيمة
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger'),



            // 5. تاريخ إنشاء الحساب
            Tables\Columns\TextColumn::make('created_at')
                ->label('تاريخ الإضافة')
                ->dateTime('Y-m-d')
                ->sortable(),



            ])





            ->filters([
                
            Tables\Filters\TernaryFilter::make('is_active')
                ->label('فلترة حسب حالة الحساب')
                ->placeholder('جميع السائقين')
                ->trueLabel('السائقين النشطين فقط')
                ->falseLabel('السائقين المعطلين فقط')
                ->queries(
                    true: fn (Builder $query) => $query->where('is_active', true),
                    false: fn (Builder $query) => $query->where('is_active', false),
                ),

            ], layout: Tables\Enums\FiltersLayout::AboveContent)  // عشان يظهر الفلتر فوق الجدول





            ->actions([
                
                Tables\Actions\EditAction::make()->label('تعديل'),
                Tables\Actions\DeleteAction::make()->label('حذف'),
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
            'index' => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit' => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
