<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\AppNotificationResource\Pages;
use App\Filament\Staff\Resources\AppNotificationResource\RelationManagers;
use App\Models\AppNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AppNotificationResource extends Resource
{
    protected static ?string $model = AppNotification::class;



    protected static ?string $navigationIcon = 'heroicon-o-bell-alert'; // أيقونة الإشعارات
    protected static ?string $navigationLabel = 'إرسال إشعارات';
    protected static ?string $modelLabel = 'إشعار';
    protected static ?string $pluralModelLabel = 'الإشعارات';




    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Hidden::make('company_id')
                    ->default(auth()->user()->company_id),



 // هاد الحقل المخفيالحقيقي الي بيوخذ شو اختار الموظف من الحقل الوهمي تحت وببعثو للداتا بيز

                Forms\Components\Hidden::make('send_to_all_drivers')
                    ->dehydrateStateUsing(function (Forms\Get $get) {
                        // 1. إذا كان الإرسال للمسافرين -> دايماً يعطي 1 (true)
                        if ($get('target_role') === 'passenger') {
                            return true;
                        }
                        
                        // 2. إذا كان للسائقين -> يأخذ القيمة من الراديو (إذا محدد يعطي 0 وإذا الكل يعطي 1)
                        return $get('driver_selection_type') ?? true;
                    }),



                Forms\Components\Section::make('تفاصيل الإشعار المرسل للتطبيق')
                    ->description('حدد الفئة المستهدفة واكتب تفاصيل الرسالة بعناية.')
                    ->schema([



                        Forms\Components\Radio::make('target_role')
                            ->label('إرسال الإشعار إلى')
                            ->options([
                                'passenger' => 'المسافرين',
                                'driver'    => 'السائقين ',
                            ])
                            ->default('passenger')
                            ->reactive() // لجعل الواجهة تتفاعل فوراً عند التغيير
                            ->required(),




                        // بتظهر لما تكون الفئة المستهدفة هيا السائقين
                        Forms\Components\Group::make([
//الحقل الوهمي مشان قبل ما ينبعث على الداتا بيز بروح للحقل المخفي send_to_all_drivers فوق وهداك الي ببعث للداتا بيز

                            Forms\Components\Radio::make('driver_selection_type')
                                ->label('نوع الإرسال للسائقين')
                                ->options([
                                    true  => 'إرسال إلى كافة سائقي الشركة',
                                    false => 'إرسال إلى سائق محدد فقط',
                                ])
                                ->default(true)
                                ->reactive()

// هاد السطر مشان يقرا من الداتا بيز شو اخترت مين هوا السائق الي اخترتو مشان يبين بالجدول
                                ->afterStateHydrated(fn ($set, $record) => $set('driver_selection_type', $record ? (bool) $record->send_to_all_drivers : true))
                                ->dehydrated(false), // حقل وهمي للواجهة فقط




                            Forms\Components\Select::make('driver_id')
                                ->label('اختر السائق المستهدف')
                                ->relationship(
                                    name: 'driver',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn ($query) => $query
                                        ->where('company_id', auth()->user()->company_id)
                                        ->where('role', 'driver')
                                )
                                ->searchable()
                                ->preload()
                                ->placeholder('ابحث واختر السائق...')
                                ->required(fn (Forms\Get $get) => $get('driver_selection_type') == false)
                                ->visible(fn (Forms\Get $get) => $get('driver_selection_type') == false),
                        ])
                        ->visible(fn (Forms\Get $get) => $get('target_role') === 'driver'), // يظهر فقط للسائقين



                    
                        Forms\Components\TextInput::make('title')
                            ->label('عنوان الرسالة')
                            ->required()
                            ->maxLength(255),


                        Forms\Components\Textarea::make('content')
                            ->label('محتوى الرسالة')
                            ->required()
                            ->rows(5),

                    ]),
            ]);
    }




    // مشان الموظف يقدر يشوف فقط الاشعارات تبعيت شركتو فقط
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('company_id', auth()->user()->company_id);
    }








    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                // عرض لوغو الشركة بالجدول للتأكيد
                Tables\Columns\ImageColumn::make('company.logo_url')
                    ->label('لوغو الشركة')
                    ->alignCenter()
                    ->circular(),


                Tables\Columns\TextColumn::make('sender.name')
                    ->alignCenter()
                    ->label('المرسل (الموظف)'),



                    
                Tables\Columns\TextColumn::make('title')
                    ->label('عنوان الرسالة')
                    ->alignCenter()
                    ->searchable()
                    ->color('gray')
                    ->weight('bold'),


                    

                Tables\Columns\TextColumn::make('target_role')
                    ->label('إرسال إلى')
                    ->alignCenter()

                    ->formatStateUsing(function (string $state, $record): string {
                        if ($state === 'driver') {
                            return $record->send_to_all_drivers 
                                ? 'كافة سائقي الشركة' 
                                : 'السائق: ' . ($record->driver->name ?? 'غير محدد');
                        }
                        return 'كافة المسافرين';
                    })
                    ->badge() 
                    ->color(fn (string $state): string => $state === 'driver' ? 'warning' : 'info')

                    ->icon(fn ($record): string => 
                        $record->target_role === 'driver' && !$record->send_to_all_drivers 
                            ? 'heroicon-m-user' 
                            : 'heroicon-m-users'
                    ),




                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->alignCenter()
                    ->dateTime('Y-m-d H:i a')
                    ->color('success'),


            ])


            ->filters([
                //
            ])

            ->actions([
                
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])


            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

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
            'index' => Pages\ListAppNotifications::route('/'),
            'create' => Pages\CreateAppNotification::route('/create'),
            'edit' => Pages\EditAppNotification::route('/{record}/edit'),
        ];
    }
}
