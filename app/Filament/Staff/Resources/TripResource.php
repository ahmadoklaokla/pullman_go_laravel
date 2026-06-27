<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\TripResource\Pages;
use App\Filament\Staff\Resources\TripResource\RelationManagers;
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

    
    // المسمى في القائمة الجانبية
    protected static ?string $navigationLabel = ' إضافة رحلة';

    // المسمى عند عرض كل الرسائل
    protected static ?string $pluralModelLabel = ' الرحلات';

    // المسمى عند إنشاء رسالة واحدة
    protected static ?string $modelLabel = 'رحلة';


    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';






    public static function form(Form $form): Form
    {
        return $form
            ->schema([


            Forms\Components\Placeholder::make('current_day_display')
                    ->label('تاريخ اليوم الحالي')
                    ->content(now()->translatedFormat('l, d F Y')) // بيعرض مثلاً: الأحد، 19 أبريل 2026
                    ->extraAttributes(['class' => 'text-primary-600 font-bold']), // تلوين التاريخ ليميزه الموظف


            Forms\Components\Section::make('تفاصيل الرحلة الأساسية')
                ->schema([
                    // 1. حقل مخفي لـ company_id بياخذ شركة الموظف اللي مسجل دخول تلقائياً
                    Forms\Components\Hidden::make('company_id')
                        ->default(auth()->user()->company_id),




                    // 2. اختيار المسار
                     Forms\Components\Select::make('route_id')
                        ->label('تحديد المسار (الخط)')
                        ->relationship(
                            name: 'route', 
                            titleAttribute: 'id', // تأكد إن هذا اسم الحقل اللي فيه اسم المسار عندك

                            // مشان  الموظف يشوف بس مسارات الشركة التابع الها (modifyQueryUsing)
                            modifyQueryUsing: fn (Builder $query) => $query->where('company_id', auth()->user()->company_id), 
                        )


                        // بيعرض من مدينة كذا الى مدينة كذا في القائمة
                        ->getOptionLabelFromRecordUsing(fn ($record) => 

                        // 'غير معروف مشان ما يعطيني خطأ 
                            "من مدينة " . ($record->departureCity->name ?? 'غير معروف') . 
                            " إلى مدينة " . ($record->arrivalCity->name ?? 'غير معروف')
                        )

                        ->preload()
                        ->required()
                        // هي مشان لون الخط
                        ->native(false)
                        ->placeholder('اختر المسار الذي ستعمل عليه هذه الرحلة ')
                        // هي بتاخذ سطر كامل لحالها
                        ->columnSpanFull()

                        // خليت خانة اختيار المسار "تراقب" التغيير
                        ->live(), 
                        


            // Placeholder بيعطيني نص عادي بدون حدود 
            // 2. حقل السعر الأساسي (بدون حدود + مع فواصل)
            Forms\Components\Placeholder::make('base_price')

                ->label('السعر الأساسي للتذكرة على هذا المسار ')
                ->content(function (Forms\Get $get) {
                    // بنجيب قيمة المسار اللي اختاره الموظف فوراً
                    $routeId = $get('route_id'); 

                    // إذا مو مختار مسار، بنعرض صفر
                    if (! $routeId) {
                        return '0 ل.س';
                    }

                    // ببحث عن المسار وبجيب سعره
                    $price = \App\Models\Route::find($routeId)?->base_price ?? 0;

                    // تنسيق الرقم مع الفواصل
                    return number_format($price) . ' ل.س';
                })
                 // هي بتاخذ سطر كامل لحالها
                ->columnSpanFull()
                ->dehydrated(false), // ما بنخزنه بجدول الرحلات لأنه موجود بالمسارات  
                
                


                // 1. عرض عنوان الانطلاق بالتفصي)
            Forms\Components\Placeholder::make('departure_address')
                ->label('عنوان الانطلاق بالتفصيل')
                ->content(function (Forms\Get $get) {
                    $routeId = $get('route_id'); 
                    
                    if (! $routeId) {
                        return 'لم يتم اختيار مسار بعد';
                    }

                    // بنجيب عنوان الانطلاق من موديل المسار
                    $address = \App\Models\Route::find($routeId)?->departure_address;

                    return $address ?: 'لا يوجد عنوان تفصيلي مسجل';
                })
                ->dehydrated(false),

                

            // 2. عرض عنوان الوصول بالتفصيل (للعرض فقط)
            Forms\Components\Placeholder::make('arrival_address')
                ->label('عنوان الوصول بالتفصيل')
                ->content(function (Forms\Get $get) {
                    $routeId = $get('route_id'); 

                    if (! $routeId) {
                        return 'لم يتم اختيار مسار بعد';
                    }

                    // بنجيب عنوان الوصول من موديل المسار
                    $address = \App\Models\Route::find($routeId)?->arrival_address;

                    return $address ?: 'لا يوجد عنوان تفصيلي مسجل';
                })
                ->dehydrated(false),






            Forms\Components\Placeholder::make('route_stops')
                ->label('استراحات هذا المسار')
                ->content(function (Forms\Get $get) {
                    $routeId = $get('route_id');
                    
                    if (! $routeId) {
                        return 'يُرجى اختيار مسار أولاً';
                    }

                    // منجيب المسار ومنشوف الاستراحات اللي مخزنة جواه
                    $route = \App\Models\Route::find($routeId);
                    $stops = $route?->rest_stops; // تأكد إنو الاسم مطابق لعمود الاستراحات عندك بالداتابيز

                    // إذا ما في استراحات (لأنو مو إجباري متل ما قلت يا قلب)
                    if (empty($stops)) {
                        return 'لا يوجد استراحات مسجلة لهذا المسار (مسار مباشر)';
                    }

                    // إذا فيه استراحات، منعرضهم بشكل قائمة مرتبة
                    $output = "";
                    
                    foreach ($stops as $index => $stop) {
                        // مصفوفة الأسماء الترتيبية
                        $ordinals = [
                            0 => 'الأولى', 1 => 'الثانية', 2 => 'الثالثة', 3 => 'الرابعة', 
                            4 => 'الخامسة', 5 => 'السادسة', 6 => 'السابعة', 7 => 'الثامنة'
                        ];

                        // بنجيب الاسم من المصفوفة، وإذا مو موجود بنحط الرقم عادي
                        $label = $ordinals[$index] ?? ($index + 1);
                        
                        $name = $stop['stop_name'] ?? 'بدون اسم';
                        $location = !empty($stop['stop_location']) ? " ({$stop['stop_location']})" : "";
                        
                        // التنسيق النهائي: الاستراحة الأولى : اسم الاستراحة (الموقع)
                        $output .= "📍 الاستراحة {$label} : {$name}{$location} \n";
                    }

                    return $output;
                })
                // هي السطرين مشان يظهر النص بشكل مرتب تحت بعضه
                ->extraAttributes(['style' => 'white-space: pre-line; color: #fbbf24; font-weight: bold;'])
                // هي بتاخذ سطر كامل لحالها
                ->columnSpanFull()
                ->dehydrated(false),





                // 2. اختيار المسار
                Forms\Components\Select::make('bus_id')
                        ->label('تحديد الباص')
                        ->relationship(
                            name: 'bus', 
                            titleAttribute: 'id', // تأكد إن هذا اسم الحقل اللي فيه اسم المسار عندك

                            // مشان  الموظف يشوف بس مسارات الشركة التابع الها (modifyQueryUsing)
                            modifyQueryUsing: fn (Builder $query) => $query->where('company_id', auth()->user()->company_id), 
                        )


                        // بيعرض رقم الباص والموديل في القائمة بشكل أنيق
                        ->getOptionLabelFromRecordUsing(fn ($record) => 
                            "باص رقم: " . ($record->bus_numbernnn ?? 'غير معروف') . 
                            " - موديل: " . ($record->bus_model ?? 'غير معروف')
                        )

                        ->preload()
                        ->required()
                        // هي مشان لون الخط
                        ->native(false)
                        ->placeholder('اختر الباص المخصص لهذه الرحلة')
                         // هي بتاخذ سطر كامل لحالها
                        ->columnSpanFull()

                      // بيراقب التغيير عشان يجيب بيانات السائق فوراً
                        ->live(),
                        


            // Placeholder بيعطيني نص عادي بدون حدود 
            // 4. تفاصيل الباص والسائق (تعبئة تلقائية للقراءة فقط)
            Forms\Components\Grid::make(4) // قسمتهن 3 أعمدة
                ->schema([
                    
                    // رقم اللوحة
                    Forms\Components\Placeholder::make('bus_number')
                        ->label('رقم اللوحة')
                        ->content(function (Forms\Get $get) {

                            // بجيب رقم لوحة الباص على حسب الباص الي اختاره الموظف
                            $busId = $get('bus_id'); 
                            if (! $busId) return '---';
                            
                            return \App\Models\Bus::find($busId)?->bus_number ?? 'غير متوفر';
                        }),


                     // عدد مقاعد هذا الباص
                    Forms\Components\Placeholder::make('total_seats')
                        ->label('العدد الكلي للمقاعد')
                        ->content(function (Forms\Get $get) {
                            $busId = $get('bus_id');
                            if (! $busId) return '---';
                            
                            return \App\Models\Bus::find($busId)?->total_seats ?? 'غير متوفر';
                        }),



                    // اسم السائق من جدول المستخدمين عن طريق العلاقة الي بموديل الباص driver
                    Forms\Components\Placeholder::make('driver_name')
                                ->label('اسم السائق')
                                ->content(function (Forms\Get $get) {
                                    $busId = $get('bus_id');
                                    if (! $busId) return '---';
                                    
                        $name = \App\Models\Bus::find($busId)?->driver?->name ?? 'غير متوفر';
                       
                        return new \Illuminate\Support\HtmlString("<span style='color: #16a34a; font-weight: bold;'>{$name}</span>");
                                }),





                            // 4. رقم هاتف السائق (مُعدّل ليسحب من علاقة الحساب الجديد)
                            Forms\Components\Placeholder::make('driver_phone')
                                ->label('رقم هاتف السائق')
                                ->content(function (Forms\Get $get) {
                                    $busId = $get('bus_id'); 
                                    if (! $busId) return '---';
                                    
                            $phone = \App\Models\Bus::find($busId)?->driver?->phone ?? 'غير متوفر';
                            
                            return new \Illuminate\Support\HtmlString("<span style='color: #16a34a; font-weight: bold;'>{$phone}</span>");
                                }),



                    ])->columnSpanFull(),



                     Forms\Components\Grid::make(4)
                            ->schema([

                            Forms\Components\Placeholder::make('assistant_name')
                                ->label('اسم المعاون')
                                ->content(function (Forms\Get $get) {
                                    $busId = $get('bus_id'); 
                                    if (! $busId) return '---';
                                    
                            $astName = \App\Models\Bus::find($busId)?->assistant_name ?? 'لا يوجد';
                            
                            return new \Illuminate\Support\HtmlString("<span style='color: #ea580c; font-weight: bold;'>{$astName}</span>");
                                }),
                                



                            Forms\Components\Placeholder::make('assistant_phone')
                                ->label(' رقم هاتف المعاون')
                                ->content(function (Forms\Get $get) {
                                    $busId = $get('bus_id'); 
                                    if (! $busId) return '---';
                                    
                            $astPhone = \App\Models\Bus::find($busId)?->assistant_phone ?? 'لا يوجد';
                          
                            return new \Illuminate\Support\HtmlString("<span style='color: #ea580c; font-weight: bold;'>{$astPhone}</span>");
                                })


                ->dehydrated(false), // ما بنخزنه بجدول الرحلات لأنه موجود بالباصات     
                ])->columnSpanFull(), // عشان تأخذ عرض الصفحة كامل       






                    Forms\Components\TextInput::make('trip_assistant_name')
                       ->label('⚠️ المعاون البديل (تعديل استثنائي لهذه الرحلة فقط وليس لجميع الرحلات بين الفترتين)')
                        ->visible(fn (string $operation): bool => $operation === 'edit')    //يظهر عند التعديل فقط
                        ->placeholder('أتركه فارغاً إذا كنت تريد الاعتماد على المعاون الأساسي المعروض فوق')
                        ->columnSpan(1),



                    Forms\Components\TextInput::make('trip_assistant_phone')
                        ->label('اكتب الرقم هنا لتغييره في هذه الرحلة فقط')
                        ->tel()
                        ->visible(fn (string $operation): bool => $operation === 'edit')   //يظهر عند التعديل فقط
                        ->placeholder('أتركه فارغاً إذا كنت تريد الاعتماد على الرقم المعروض فوق')
                        ->columnSpan(1),




                    // 4. وقت الانطلاق
                    Forms\Components\TimePicker::make('scheduled_time')
                        ->label('موعد انطلاق الرحلة ')
                        // اقتراحات سريعة للموظف
                        ->datalist([
                                '01:00','02:00','03:00','04:00','05:00','06:00','07:00', '08:00', '09:00', '10:00', '11:00', '12:00', // صباحي وظهيرة
                                '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', // بعد الظهر ومسائي
                                '19:00', '20:00', '21:00', '22:00', '23:00', '00:00', // ليلي
                        ])
                        ->required(),


                    // 5. أيام التكرار
                    Forms\Components\Select::make('days_of_week')
                        ->label('أيام عمل الرحلة')
                        ->multiple()  // بقدر يحدد اكثر من يوم
                        ->options([
                            0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء',
                            3 => 'الأربعاء', 4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
                        ])
                        ->required(),






                    Forms\Components\Section::make('فترة تكرار الرحلة')
                        ->schema([

                            Forms\Components\DatePicker::make('start_date')
                                ->label('تاريخ بداية التشغيل')
                                ->default(now())
                                ->required()
                                ->native(false)

                                 // 1. يمنع اختيار أيام قديمة من التقويم
                                ->minDate(now()->startOfDay()) 
                                // 2. تفعيل رسالة الخطأ إذا الموظف عدل التاريخ يدوياً لشي قديم
                                ->rules(['after_or_equal:today'])
                                ->validationMessages([
                                    'after_or_equal' => 'يجب ان يكون تاريخ تشغيل هذه الرحلة كتاريخ اليوم او اكبر',
                                ]),



                            Forms\Components\DatePicker::make('end_date')
                                ->label('تاريخ نهاية التشغيل')
                                ->helperText('سيتم إنشاء رحلة منفصلة لكل يوم عمل ضمن هذه الفترة')
                                ->required()
                                ->native(false)

                                ->minDate(fn (Forms\Get $get) => $get('start_date') ?: now())
                                ->rules([
                                    'after_or_equal:today',
                                    fn (Forms\Get $get) => 'after_or_equal:start_date'
                                ])
                                ->validationMessages([
                                    'after_or_equal' => 'يجب ان يكون نهاية تشغيل الرحلة أكبر من تاريخ تشغيلها',
                                ]),
                        ])->columns(2),




                    // 6. التفعيل
                    Forms\Components\Toggle::make('is_active')
                        ->label('الرحلة مفعلة وتظهر للمسافرين؟')
                        ->default(true)
                        ->columnSpanFull(),


                ])->columns(2), // قسمنا الحقول لعمودين عشان الشكل يكون أرتب

        ]);
           
    }




    // مشان يعرضلي فقط الرحلات التابعة لهاد المسار الي تابع لهي الشركة 
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('route', function ($query) {
                $query->where('company_id', auth()->user()->company_id);
            })

            // هون بجمعلي الرحلات بسجل واحد عند اضافة رحلة جديدة ولكن بالداتابيز لكل رحلة الها سجل
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

                Tables\Actions\EditAction::make(),

                    

                // مشان لما احذف سطر واحد من جدول الرحلات يحذفلي كلشي رحلات مكررة لهاد السجل (السطر)
                Tables\Actions\DeleteAction::make()
                    ->modalHeading('هل أنت متأكد من أنك تريد حذف هذا السجل؟')
                        ->modalDescription(new \Illuminate\Support\HtmlString(
                            '<div style="background-color: rgba(220, 38, 38, 0.1); border-right: 4px solid #dc2626; color: #ef4444; padding: 10px 14px; border-radius: 6px; margin-top: 12px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; text-align: right;" dir="rtl">
                                <span>⚠️</span>
                                <strong>علماً أنه سوف يتم حذف جميع الرحلات لهذا السجل المحدد.</strong>
                            </div>'
                        ))

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
            'create' => Pages\CreateTrip::route('/create'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }
}
