<?php

namespace App\Filament\Staff\Resources;

use App\Filament\Staff\Resources\BookingResource\Pages;
use App\Filament\Staff\Resources\BookingResource\RelationManagers;
use App\Models\Booking;
use Doctrine\DBAL\Schema\Schema;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    

// الفلامينت عم بدور على علاقة بين الحجوزات والشركات بس اني ربطت الحجوزات بالرحلات وهيا مربوطة بالباصات والمسارات والشركات
    protected static bool $isScopedToTenant = false;

    

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    
    // اسم التبويب باللوحة
    protected static ?string $navigationLabel = 'إدارة الحجوزات';
    protected static ?string $modelLabel = 'حجز';
    protected static ?string $pluralModelLabel = 'الحجوزات';




// الدالتين هدول مشان عدداد الاشعارات على ادارة الحجوزات في القائمة الجانبية
    public static function getNavigationBadge(): ?string
    {
        $tz = 'Asia/Damascus';

        // بيعد فقط الحجوزات غير المقروءة، وبس تشوفهم ينقص للصفر ويختفي تلقائياً
        $count = static::getModel()::where('is_seen', false)
        ->where('payment_status', '!=', 'cancelled')
        ->whereDate('travel_date', '>=', \Carbon\Carbon::today($tz))
        ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // النقطة الحمراء
        return 'danger'; 
    }





    public static function form(Form $form): Form
    {
        return $form
            ->schema([

            
        Forms\Components\Section::make('قائمة بيانات الحجز')
                ->schema([
                    // 1. حقل مخفي لـ company_id بياخذ شركة الموظف اللي مسجل دخول تلقائياً
                    Forms\Components\Hidden::make('company_id')
                        ->default(auth()->user()->company_id),



                    Forms\Components\Placeholder::make('current_day_display')
                        ->label('تاريخ اليوم الحالي')
                        ->content(now()->translatedFormat('l, d F Y')) // بيعرض الأحد، 19 أبريل 2026
                        ->extraAttributes(['class' => 'text-primary-600 font-bold']), // تلوين التاريخ ليميزه الموظف



                        
                    Forms\Components\Placeholder::make('reference_number')
                        ->label('رقم الحجز المرجعي')
                        ->content(fn ($record) => $record?->reference_number ?? 'سيتم توليده عند الحفظ'),





                    // 2. اختيار المسار
                     Forms\Components\Select::make('route_id')
                        ->label('تحديد المسار (الخط)')
                        ->options(fn () => \App\Models\Route::where('company_id', auth()->user()->company_id)
                        ->get()
                        ->mapWithKeys(fn ($record) => [
                            $record->id => "من مدينة " . ($record->departureCity->name ?? '---') . " إلى " . ($record->arrivalCity->name ?? '---')

                        ])
                    )
                        ->preload()
                        ->required()
                        // هي مشان لون الخط
                        ->native(false)
                        ->placeholder(' إختر مسسار أولا لكي تستطيع إضافة حجز جديد')
                        // هي بتاخذ سطر كامل لحالها
                        ->columnSpanFull()

                        // خليت خانة اختيار المسار "تراقب" التغيير
                        ->live()

                        // مشان نحسب السعر الاجمالي للحجز اذا كان اكثر من مقعد
                        ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                            // 1. نجيب المسار المختار
                            $route = \App\Models\Route::find($state);
                            $basePrice = $route ? $route->base_price : 0;
                            
                            // 2. نجيب عدد المقاعد (وإذا كان فاضي نعتبره 1)
                            $seatsCount = $get('seats_count') ?? 1;
                            
                            // 3. نضرب ونخزن الناتج بحقل السعر الإجمالي
                            $set('total_price', $basePrice * $seatsCount);
                        })


                        // مشان لما افتح صفحة التعديل لأي حجز تتعبى البيانات
                        ->afterStateHydrated(function ($state, Forms\Set $set, $record) {
                            if ($record && $record->trip) {
                                //يا حقل المسار، روح شوف رحلة هاد الحجز شو مسارها وتعبى فيه
                                $set('route_id', $record->trip->route_id);
                            }
                        }),
                        



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
                        




            Forms\Components\Grid::make(2) // رقم 2 يعني تقسيم السطر لعمودين
                ->schema([
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

                ]),




                    Forms\Components\Placeholder::make('route_stops')
                        ->label('استراحات هذا المسار')
                        ->content(function (Forms\Get $get) {
                            $routeId = $get('route_id');
                            
                            if (! $routeId) {
                                return ' ⚠️ يُرجى اختيار مسار أولاً' ;
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
                        ->extraAttributes(['style' => 'white-space: pre-line; color: #10b981; font-weight: bold;'])
                        // هي بتاخذ سطر كامل لحالها
                        ->columnSpanFull()
                        ->dehydrated(false),




                    Forms\Components\Select::make('trip_id')
                        ->label(' الرحلات المتوفرة لهذا المسار ')
                        ->options(function (Forms\Get $get) {
                            $routeId = $get('route_id'); 
                                if (! $routeId) return [];
                            
                                return \App\Models\Trip::where('route_id', $routeId)


                                ->where('is_active', true) // 🟢  شرط لجلب الرحلات النشطة فقط
                                ->get()
                                ->filter(function ($record) {
                                        if (!$record->trip_date || empty($record->days_of_week)) return true;
                                            
                                            // 🟢 التعديل هون: بنجيب رقم اليوم (0 للأحد، 1 للاثنين... 4 للخميس) عشان يطابق داتابيز 
                                            $currentDayNum = (string) \Carbon\Carbon::parse($record->trip_date)->dayOfWeek;
                                            
                                            // بنتأكد إن مصفوفة الأيام مجهزة صح كـ سياق نصي
                                            $activeDays = array_map('strval', (array)$record->days_of_week);
                                            
                                            // إذا كان رقم يوم الرحلة موجود بالأيام النشطة بنخليه، غير هيك بنخفيه
                                            return in_array($currentDayNum, $activeDays);
                                })



                                ->mapWithKeys(function ($record) {
                                    // 1. تنسيق الوقت
                                        $time = \Carbon\Carbon::parse($record->scheduled_time)->format('g:i A');
                                        // 2. استخراج التاريخ واسم اليوم (الإضافة الجديدة)
                                        $date = $record->trip_date ? \Carbon\Carbon::parse($record->trip_date)->format('Y-m-d') : '';
                                        $dayName = $record->trip_date ? \Carbon\Carbon::parse($record->trip_date)->translatedFormat('l') : '';
                                    return [$record->id => "رحلة رقم {$record->id} | {$dayName} | موعد الإنطلاق: {$time} | بتاريخ: {$date}"];
                                });
                        })
                        ->live() 

                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            // 1. منجيب بيانات الرحلة من الداتابيز بناءً على الـ ID اللي اخترناه
                            $trip = \App\Models\Trip::find($state);
                            
                            if ($trip) {
                                    $set('bus_id', $trip->bus_id);
                                    $set('scheduled_time', \Carbon\Carbon::parse($trip->scheduled_time)->format('H:i:s'));
                                    $set('days_of_week', $trip->days_of_week);


                                    // 1. نعالج التاريخ ونجيب اسم اليوم
                                    $parsedDate = \Carbon\Carbon::parse($trip->trip_date);
                                    
                                    // 2. بنخزن التاريخ الصافي بالحقل المخفي عشان الداتابيز
                                    $set('travel_date', $parsedDate->format('Y-m-d')); 
                                    
                                    // 3. بنعرض التاريخ مع اسم اليوم بالحقل اللي بيشوفه الموظف
                                    $set('travel_date_display', $parsedDate->translatedFormat('l | Y-m-d')); 
                                } else {
                                    $set('bus_id', null);
                                    $set('travel_date', null);
                                    $set('travel_date_display', null);
                                    $set('scheduled_time', null);
                                    $set('days_of_week', []);
                                }
                            })

                    // هاد مشان الحقول تتعبى اول ما اضغط على اي رحلة تتعبى تلقائيا على حسب شو ضغطت
                        ->afterStateHydrated(function ($state, Forms\Set $set, $record) {
                            // إذا كنا بصفحة تعديل والـ record موجود
                            if ($record) {

                                    $set('trip_id', $record->trip_id); 
                                    $set('bus_id', $record->bus_id);
                                    
                                    if ($record->scheduled_time) {
                                        $set('scheduled_time', \Carbon\Carbon::parse($record->scheduled_time)->format('H:i:s'));
                                    }
                                    
                                    $set('days_of_week', $record->days_of_week);
                                    
                                    // تعبئة حقل العرض عند فتح صفحة التعديل
                                    if ($record->travel_date) {
                                        $set('travel_date_display', \Carbon\Carbon::parse($record->travel_date)->translatedFormat('l | Y-m-d'));
                                    }
                                }
                            })

                        ->preload()
                        ->searchable()

                        // هاد السطر مهم لحتى يعرضلي 2000 رحلة مكررة
                        ->optionsLimit(2000)

                        // هي بتاخذ سطر كامل لحالها
                        ->columnSpanFull()
                        ->placeholder('اختر الرحلة لعرض تفاصيلها'),




                Forms\Components\Placeholder::make('bus_details')
                        ->label('بيانات الباص والسائق')
                        ->content(function (Forms\Get $get) {
                            $tripId = $get('trip_id'); // بنجيب رقم الرحلة المختار
                            
                            if (! $tripId) return '⚠️ يرجى اختيار رحلة أولاً لعرض بيانات الباص';

                        // 1. بنجيب الرحلة مع علاقة الباص وعلاقة السائق تبعه مشان ما يضرب السيستم
                            $trip = \App\Models\Trip::with('bus.driver')->find($tripId);
                            $bus = $trip?->bus;

                            if (! $bus) return '❌ لا يوجد باص مرتبط بهذه الرحلة';

                        // 2. استخراج البيانات (السائق من علاقة المستخدمين، والمعاون من جدول الباص مباشرة)
                                $busNumber = $bus->bus_numbernnn ?? 'غير معروف';
                                $driverName = $bus->driver?->name ?? 'غير متوفر';
                                $driverPhone = $bus->driver?->phone ?? 'غير متوفر';
                                $assistantName = $bus->assistant_name ?? 'لا يوجد';
                                $assistantPhone = $bus->assistant_phone ?? 'لا يوجد';

                        // 3.التنسيق بالألوان (أخضر للسائق وبرتقالي للمعاون) داخل مصفوفة HTML
                                return new \Illuminate\Support\HtmlString("
                                    <span>🚌 باص رقم: <b>{$busNumber}</b></span>
                                    <span style='margin: 0 8px; color: #ccc;'>|</span>
                                    <span style='color: #16a34a; font-weight: bold;'>👨‍✈️ السائق: {$driverName} (📱 {$driverPhone})</span> 
                                    <span style='margin: 0 8px; color: #ccc;'>|</span>
                                    <span style='color: #ea580c; font-weight: bold;'>🤝 المعاون: {$assistantName} (📱 {$assistantPhone})</span>
                                ");
                            })
                        ->columnSpanFull(),




            Forms\Components\Grid::make(12)
                ->schema([




                    Forms\Components\TextInput::make('passenger_phone')
                        ->label('رقم الهاتف')
                        ->placeholder('أدخل رقم صاحب الحجز')
                        ->columnSpan(5),




                    Forms\Components\TextInput::make('seats_count')
                        ->label('عدد المقاعد المحجوزة')
                        ->numeric()
                        ->readOnly() // للقراءة فقط، الموظف ما بيقدر يلعب فيه
                        ->default(1) // افتراضياً مقعد واحد
                        ->columnSpan(2)
                        ->extraInputAttributes(['style' => 'font-weight: bold; color: #10b981;']),

                ]),


            Forms\Components\Grid::make(12)
                ->schema([
                    Forms\Components\TextInput::make('total_price')
                        ->label('السعر الإجمالي (ل.س)')
                        ->numeric()
                        ->columnSpan(4)  // بيوخذ مساحة خانتين
                        ->readOnly()
                        ->default(0)
                        ->extraInputAttributes(['style' => 'font-weight: bold; color: #10b981; font-size: 1.2rem;']),


                ]),




            Forms\Components\Grid::make(12)
                ->schema([


                    
                // رح يتخزن تلقائيا على اساس الرحلة الي بيختارها الموظف لانو معها تاريخها
                Forms\Components\Hidden::make('travel_date')
                    ->required(),




                // الحقل فقط للعرض مشان يعرض تاريخ السفر مع اسم اليوم من قيمة تاريخ الرحلة
                    Forms\Components\TextInput::make('travel_date_display')
                        ->label('تاريخ السفر')
                        ->readonly()
                        ->columnSpan(4)
                        ->prefix('📅')
                    ->extraInputAttributes(['style' => 'font-weight: bold; color: #10b981;']),

                ]),


                    // 2. مكرر الحقول (الريبيتر) لإضافة الركاب
                    Forms\Components\Repeater::make('seats')
                        ->relationship() // الفلامينت: بيربط الحقول بجدول المقاعد( بموديل الحجز عملت علاقة مع جدول الحجوزات seats )
                        ->label('بيانات الركاب والمقاعد')
                        ->schema([

                            Forms\Components\TextInput::make('passenger_name')
                                ->label('اسم الراكب')
                                ->required()
                                ->placeholder('أدخل اسم الراكب...'),


                            Forms\Components\TextInput::make('seat_number')
                                ->label('رقم المقعد')
                                ->required()
                                ->numeric()
                                ->readOnly()
                                ->placeholder('أدخل رقم المقعد لهذا الراكب فقط')

                                // زر فتح خريطة المقاعد داخل الحقل
                                ->suffixAction(fn (Forms\Get $get) => Forms\Components\Actions\Action::make('open_bus_map')
                                        ->label('اختر المقعد من الخريطة   💺')
                                        ->icon('heroicon-m-table-cells') 
                                        ->color('success')   
                                        ->modalHeading('خريطة مقاعد الحافلة التفاعلية') 
                                        ->modalDescription('الرجاء اختيار مقعد متاح للراكب الحالي')
                                        ->modalWidth('xl') 
                                        ->modalSubmitActionLabel('تأكيد المقعد المختار') 


                                        ->form([
                                            // 1. تثبيت رقم الرحلة بشكل صحيح داخل المودال
                                            Forms\Components\Hidden::make('modal_trip_id')
                                                ->default($get('../../trip_id')), // سحبنا القيمة فوراً من الفورم الأب قبل العزل

                                            // 2. حقل الـ View المخصص
                                            Forms\Components\ViewField::make('selected_seat')
                                                ->label('اختر مقعداً:')
                                                ->view('filament.staff.components.bus-map-picker') 
                                                ->required(),
                                        ])
                                        ->action(function (array $data, Forms\Set $set) {
                                            if (isset($data['selected_seat'])) {
                                                $set('seat_number', $data['selected_seat']);
                                            }
                                        })
                                    ),



                            // 👑علامة صاحب الحجز
                            Forms\Components\Toggle::make('is_primary')
                                ->label('صاحب الحجز 👑')
                                ->onColor('success') // بيصير لونه أخضر لما يتفعل
                                ->inline(false) // عشان الكلمة تضل فوق الزر
                                ->columnSpan(4),
                        ])

                        ->columns(2)
                        ->defaultItems(1) // بيفتح خانة لراكب واحد عالأقل
                        ->addActionLabel('إضافة راكب جديد ➕')
                        ->reorderable(false) // عشان ما يوجع راس الموظف بترتيبهم فوق وتحت
                        ->live()

                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, $state) {
                                // 1. نحسب عدد الركاب ونحدث الحقل
                                $count = count($state);
                                $set('seats_count', $count);

                                // 2. نجيب رقم المسار المختار حالياً
                                $routeId = $get('route_id');
                                
                                // 3. نجيب سعر المسار
                                $basePrice = 0;
                                if ($routeId) {
                                    $route = \App\Models\Route::find($routeId);
                                    $basePrice = $route ? $route->base_price : 0;
                                }

                                // 4. نضرب العدد بالسعر ونحدث حقل السعر الإجمالي فوراً
                                $set('total_price', $count * $basePrice);
                            })
                        ->columnSpanFull(),
                





                    Forms\Components\Grid::make(2)
                        ->schema([

                            Forms\Components\Select::make('payment_method')
                                ->label('طريقة الدفع')
                                ->options([
                                    'on_boarding' => 'عند الصعود للباص',
                                    'transfer' => 'تحويل للشركة',
                                ])
                                ->required()
                                ->native(false)
                                ->default('on_boarding')
                                ->prefixIcon('heroicon-o-credit-card'),



                    Forms\Components\Select::make('payment_status')
                    ->label('حالة الدفع')
                        ->options([
                            'paid' => 'مدفوع ✅',
                            'unpaid' => 'غير مدفوع ❌',
                            'cancelled' => 'ملغي',
                        ])
                        ->required()
                        ->native(false)
                        ->default('unpaid'),
                        ]),
            ])
            

        ]);

    }






// جيبلي رقم الموظف الي مسجل دخول حاليا 
// اذا كان ادمن اعرضلو كلشي حجوزات للشركات 
// واذا كان موظف او صاحب شركة اعرض حجوزات الرحلة الي تابعة لشركة الموظف

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

// دالة موجودة بموديل المستخدمين
        if ($user->isAdmin()) {
            return $query;
        }

        // 2. إذا كان موظف أو صاحب شركة، جيب الحجوزات اللي رحلتها تابعة لشركته فقط
        return $query->whereHas('trip', function ($q) use ($user) {
            $q->where('company_id', $user->company_id);
        });
    }








public static function table(Table $table): Table
    {
        return $table
            ->columns([
                

                Tables\Columns\TextColumn::make('user.passenger_image') 
                    ->label('الصورة الشخصية')
                    ->alignCenter()
                    ->html() //  تفعل قراءة الـ HTML
                    ->state(function ($record) {
                        // جلب مسار الصورة المخزن بالداتابيز
                        $imagePath = $record->user?->passenger_image;
                        
                        if ($imagePath) {
                            // عرض الصورة برابطها المباشر من مجلد الـ public الرئيسي وبشكل دائري 100%
                            return '<img src="' . url($imagePath) . '" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; display: block; margin: 0 auto;">';
                        }
                        
                        // شكل دائري رمادي بديل كـ Placeholder لو المستخدم مو حاطط صورة
                        return '<div style="width: 40px; height: 40px; border-radius: 50%; background-color: #e5e7eb; display: flex; align-items: center; justify-content: center; margin: 0 auto;"><svg style="width:20px; height:20px; color:#9ca3af;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg></div>';
                    }),



            
                TextColumn::make('reference_number')
                    ->label('رقم الحجز')
                    ->alignCenter()
                    ->searchable()
                    ->badge()
                    ->color('primary'),




                // 3. المسار (من جدول الـ Route عن طريق الـ Trip)
                // هاد الحقل الوهمي بالنسبة لل db وفلامينت بشوفه حقيقيroute_full_name
                TextColumn::make('trip.route.route_full_name')
                    ->label('المسار')
                    ->alignCenter()
                    ->color('success')
                    ->icon('heroicon-m-map-pin')
                    ->iconColor('danger'),



                // 2. اسم صاحب الحساب (اللي حجز)
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('صاحب الحجز')
                    ->icon('heroicon-m-user')
                    ->iconColor('success')
                    ->color('success')
                    ->alignCenter()
                    
                    ->getStateUsing(function ($record) {

                    // اذا كان الحجز من التطبيق اعرض اسم المستخدم
                        if ($record->user_id) {
                            return $record->user->name;
                        }

                        // حجز مكتبي: دور على الراكب اللي عنده إشارة تاج
                        $primaryPassenger = $record->seats->where('is_primary', true)->first();
                        return $primaryPassenger ? $primaryPassenger->passenger_name : 'غير محدد';
                    }),





                Tables\Columns\TextColumn::make('display_phone')
                    ->label('رقم الهاتف')
                    ->alignCenter()
                    ->getStateUsing(function ($record) {

                    // اذا كان حجز تطبيق اعرض رقم الهاتف تبعو من جدول المستخدمين 
                        if ($record->user_id) {
                            return $record->user->phone ?? 'لا يوجد';
                        }

                    // اما اذا كان حجز مكتبي اعرض رقم الهاتف الي دخلو الموظف
                        return $record->passenger_phone ?? 'لا يوجد';
                    })
                    ->color('info')
                    ->badge()
                    ->description(fn ($record) => $record->user_id ? '📱 حجز تطبيق' : '🏢 حجز مكتبي'),





                // 4. موعد وتاريخ الرحلة (من جدول الـ Trip) الي حددو المسافر 
                TextColumn::make('travel_date')
                    ->label('تاريخ السفر')
                    ->color('success')
                    ->date('Y-m-d')
                    ->alignCenter(),




                TextColumn::make('trip.scheduled_time')
                    ->label('موعد انطلاق الرحلة')
                    ->badge()
                    ->alignCenter()
                    ->time('h:i A'), // عشان يطبع AM / PM
                    


                TextColumn::make('seats_count')
                    ->label('عدد المقاعد المحجوزة')
                    // مشان يطلع الرقم بالانكليزي 
                    ->formatStateUsing(fn ($state) => (string) $state)
                    ->badge()
                    ->color('success')
                    ->alignCenter(),




                Tables\Columns\TextColumn::make('seats')
                    ->label(' بيانات المسافرين ')
                    ->alignCenter()
                    ->formatStateUsing(function ($record) {
                        return $record->seats->map(function ($seat, $index) {
                            $order = $index + 1;


                            // إذا كان هو صاحب الحجز بنضيف تاج
                $isPrimaryBadge = $seat->is_primary ? "<span style='color: #eab308; font-size: 0.8rem;'>👑 (صاحب الحجز)</span>" : "";
                
                
                            // استخدمنا span مع nowrap عشان نمنع تكسير السطر للراكب الواحد
                            return "<span style='white-space: nowrap;'>
                                <span style='color: #22c55e;'>👤 الراكب {$order} :</span> 
                                <b style='color: #3b82f6;'>{$seat->passenger_name}</b> {$isPrimaryBadge}
                                <span style='color: #22c55e;'> | 💺 رقم المقعد : </span> 
                                <b style='color: #3b82f6;'>{$seat->seat_number}</b>
                            </span>";
                        })->implode("<br>"); // فصلنا بين الركاب بـ br بدل \n
                    })
                    ->html()
                    ->color('info'),



                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->alignCenter()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'on_boarding' => 'عند الصعود',
                        'transfer' => 'تحويل بنكي/شركة',
                        default => $state,
                    })
                    ->badge()
                    ->color('gray'),



                Tables\Columns\TextColumn::make('payment_status')
                    ->label('حالة الدفع')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'unpaid' => 'danger',
                        'cancelled' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => 'مدفوع ✅',
                        'unpaid' => 'غير مدفوع ❌',
                        'cancelled' => 'ملغي',
                        default => $state,
                    }),


                    
                // 6. السعر الإجمالي
                TextColumn::make('total_price')
                    ->label('السعر الإجمالي ')
                    ->alignCenter()
                    ->formatStateUsing(fn ($state) => number_format($state)   . "  ل.س")
                    ->color('success'),

                    
            ])

            ->defaultSort('created_at', 'desc')
            // بتحكم بعدد الاعمدة
            ->filtersFormColumns(4)





->filters([

    // 1. فلتر ذكي ومدمج (المسار + الرحلة) عشان يتفاعلوا مع بعض Live
    Tables\Filters\Filter::make('route_and_trip')
        ->form([
            Forms\Components\Select::make('route_id')
                ->label('تصفية حسب المسار')
                ->options(fn () => \App\Models\Route::where('company_id', auth()->user()->company_id)
                    ->get()
                    ->pluck('route_full_name', 'id')
                )
                ->searchable()
                ->live()
                ->columnSpan(1)    // بياخد مساحة خانة وحدة
                ->afterStateUpdated(fn (Forms\Set $set) => $set('trip_id', null)), // تصفير الرحلة لما نغير المسار




            Forms\Components\Select::make('trip_id')
                ->label('تصفية حسب الرحلة')

                // مشان رقم الباص واسم السائق يبينو عند اختياري للرحلة
                ->live()
                ->options(function (Forms\Get $get) {
                    $routeId = $get('route_id');
                    $query = \App\Models\Trip::where('company_id', auth()->user()->company_id);

                    // إذا الموظف اختار مسار، جيب بس رحلات هاد المسار
                    if ($routeId) {
                        $query->where('route_id', $routeId);
                    }

                    return $query->get()->mapWithKeys(function ($trip) {
                        // تنسيق الوقت (صباحاً/مساءً) مع رقم الرحلة والتاريخ
                        $time = $trip->scheduled_time ? \Carbon\Carbon::parse($trip->scheduled_time)->format('h:i A') : 'غير محدد';
                        $date = $trip->trip_date ? \Carbon\Carbon::parse($trip->trip_date)->format('Y-m-d') : '';
                        $dayName = $trip->trip_date ? \Carbon\Carbon::parse($trip->trip_date)->translatedFormat('l') : '';  // بجيب اسم اليوم حرف lبجيب اسم اليوم كامل 
                        
                        return [$trip->id => "رحلة رقم {$trip->id} | {$dayName} | الانطلاق: {$time} | بتاريخ: {$date}"];
                    });
                })
                
                ->searchable()

                // هاد السطر مهم لحتى يعرضلي 2000 رحلة مكررة
                ->optionsLimit(2000)
                
                ->columnSpan(2),  // بيوخذ مساحة خانتين




                // content هي مشان ادمجهم مع بعض احسن ما اعمل 3 حقول 

                Forms\Components\Placeholder::make('bus_details')
                    ->label('بيانات الحافلة والسائق')
                    ->content(function (Forms\Get $get) {
                        $tripId = $get('trip_id'); // بنجيب رقم الرحلة اللي اختارها الموظف
                        
                        if (! $tripId) return 'يرجى اختيار رحلة أولاً';

                        // بنجيب بيانات الرحلة ومعها الباص المرتبط فيها مع علاقة السائق المربوط بالباص 
                        $trip = \App\Models\Trip::with('bus.driver')->find($tripId);
                        $bus = $trip?->bus;

                        if (! $bus) return 'لا يوجد باص مرتبط بهذه الرحلة';


                        // احسب عدد المقاعد المحجوزة لكل حجز واجمعهم مع بعض
                        $bookedSeats = \App\Models\Booking::where('trip_id', $tripId)->sum('seats_count');

                        // عدد الحجوزات لكل رحلة منعمللها فلترة
                        $bookingscount = \App\Models\Booking::where('trip_id', $tripId)->count();

                        // عدد المقاعد المتاحة
                        $availableSeats = max(0, $bus->total_seats - $bookedSeats);



                // 2.استخراج المتغيرات الجديدة للـطاقم
                        $busNumber = $bus->bus_numbernnn ?? 'غير معروف';
                        $driverName = $bus->driver?->name ?? 'غير متوفر';
                        $driverPhone = $bus->driver?->phone ?? 'غير متوفر';
                        $assistantName = $bus->assistant_name ?? 'لا يوجد';
                        $assistantPhone = $bus->assistant_phone ?? 'لا يوجد';


                        // في حال تم تغيير المعاون التابع لهذه الرحلة
$emergencyAssistantHtml = '';
if (!empty($trip->trip_assistant_name) || !empty($trip->trip_assistant_phone)) {
    $eName = $trip->trip_assistant_name ?? 'غير معروف';
    $ePhone = $trip->trip_assistant_phone ?? 'غير معروف';
    
    $emergencyAssistantHtml = "
        <div style='margin-top: 16px; padding: 14px 18px; background-color: #000000; border: 2px dashed #ea580c; border-radius: 10px;'>
            <div style='color: #fb923c; font-weight: bold; display: flex; align-items: center; gap: 8px; font-size: 1rem;'>
                <span>⚠️</span> <span>تنبيه: تم تغيير المعاون الاستثنائي لهذه الرحلة:</span>
            </div>
            <div style='margin-top: 6px; padding-right: 25px; color: #f97316; display: flex; gap: 15px; flex-wrap: wrap;'>
                <span>👤 الاسم: <span style='color: #ffffff;'>{$eName}</span></span>
                <span style='color: #ea580c;'>|</span>
                <span>📱 موبايل: <span style='color: #cbd5e1; font-family: monospace;'>{$ePhone}</span></span>
            </div>
        </div>
    ";
}

// إرجاع التصميم بالملي (الطاقم داخل كروت سوداء + الإحصائيات أسطر عادية بدون كروت)
return new \Illuminate\Support\HtmlString("
    <div style='display: flex; flex-direction: column; gap: 16px; font-family: system-ui, -apple-system, sans-serif; direction: rtl;'>
        
        <div style='display: flex; flex-wrap: wrap; gap: 12px;'>
            
            <div style='flex: 1; min-width: 180px; padding: 14px; background: #000000; border: 1px solid #27272a; border-right: 6px solid #3b82f6; border-radius: 10px;'>
                <div style='color: #a1a1aa; font-size: 0.85rem; font-weight: bold; margin-bottom: 4px;'>🚌 بيانات الحافلة</div>
                <div style='color: #ffffff; font-size: 0.95rem;'>باص رقم: <b style='color: #3b82f6; font-size: 1.1rem;'>{$busNumber}</b></div>
            </div>
            
            <div style='flex: 2; min-width: 280px; padding: 14px; background: #000000; border: 1px solid #27272a; border-right: 6px solid #16a34a; border-radius: 10px;'>
                <div style='color: #a1a1aa; font-size: 0.85rem; font-weight: bold; margin-bottom: 8px;'>👨‍✈️ السائق</div>
                <div style='display: flex; gap: 15px; flex-wrap: wrap; color: #ffffff; font-size: 0.95rem;'>
                    <span><b>👤 الاسم:</b> <span style='color: #4ade80; font-weight: 600;'>{$driverName}</span></span>
                    <span style='color: #27272a;'>|</span>
                    <span><b>📱 موبايل:</b> <span style='color: #cbd5e1; font-family: monospace;'>{$driverPhone}</span></span>
                </div>
            </div>
            
            <div style='flex: 2; min-width: 280px; padding: 14px; background: #000000; border: 1px solid #27272a; border-right: 6px solid #ea580c; border-radius: 10px;'>
                <div style='color: #a1a1aa; font-size: 0.85rem; font-weight: bold; margin-bottom: 8px;'>🤝 المعاون الأساسي </div>
                <div style='display: flex; gap: 15px; flex-wrap: wrap; color: #ffffff; font-size: 0.95rem;'>
                    <span><b>👤 الاسم:</b> <span style='color: #f97316; font-weight: 600;'>{$assistantName}</span></span>
                    <span style='color: #27272a;'>|</span>
                    <span><b>📱 موبايل:</b> <span style='color: #cbd5e1; font-family: monospace;'>{$assistantPhone}</span></span>
                </div>
            </div>
            
        </div>

        <div style='color: #ffffff; font-size: 1rem;'>
            <div style='margin-top: 10px;'>📝 عدد الحجوزات (الطلبات) : <span style='font-weight: bold;'>{$bookingscount}</span></div>
            <div style='margin-top: 10px; padding-right: 40px;'>💺 عدد المقاعد الكلي : <span style='font-weight: bold;'>{$bus->total_seats}</span></div>
            <div style='margin-top: 10px; padding-right: 40px; color: #dc2626; font-weight: bold;'>🔴 المقاعد المحجوزة : {$bookedSeats}</div>
            <div style='margin-top: 10px; padding-right: 40px; color: #16a34a; font-weight: bold;'>🟢 المقاعد المتاحة : {$availableSeats}</div>
        </div>

        {$emergencyAssistantHtml}

    </div>
");

}) // <--- إغلاق حقل الـ HTML
->columnSpanFull(),

]) // <--- إغلاق مصفوفة الحقول لنموذج الفلترة
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['route_id'], fn ($q, $routeId) => $q->whereHas('trip', fn($t) => $t->where('route_id', $routeId)))
                        // لا تعطيني اي حجز غير حجوزات الرحلة الي بحددها اني حصرا
                        ->when($data['trip_id'], fn ($q, $tripId) => $q->where('trip_id', $tripId));
                })

                ->columns(3) // تقسيم داخلي لـ 3 أعمدة
                ->columnSpanFull(),

                

                


                
            // 2. فلتر حسب حالة الدفع
        Tables\Filters\Filter::make('payment_status_filter')

            // 1. إخفاء عنوان الفلتر في تبويب اليوم والغد عشان ما يضل نص معلق بالهوا
            ->label(fn ($livewire) => $livewire->activeTab === 'cancelled' ? '' : 'حالة الدفع')
                
            ->form([
                Forms\Components\Select::make('payment_status')
                            ->label('حالة الدفع')
                            ->options([
                                'paid' => 'مدفوع  ✅',
                                'unpaid' => ' غير مدفوع ❌',
                            ])
                            // 2. إخفاء الحقل نفسه تماماً وبشكل مستقر داخل تبويب الملغية
                            ->hidden(fn ($livewire) => $livewire->activeTab === 'cancelled'),
                    ])

                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['payment_status'], fn ($q, $status) => $q->where('payment_status', $status));
                    })
                ->columnSpan(1),




            // اذا الموظف بدو يطلع تقارير 
            // بيعرضلي المسافرين مع تفاصيل الحجز الي فعلا حجزو
            Tables\Filters\Filter::make('travel_date')
                // 1. إخفاء عنوان الفلتر في تبويب اليوم والغد عشان ما يضل نص معلق بالهوا
                ->label(fn ($livewire) => in_array($livewire->activeTab, ['today', 'tomorrow']) ? '' : 'تاريخ السفر')
                
                ->form([
                    Forms\Components\DatePicker::make('from')->label('من تاريخ')
                    ->native(false)         // تقويم مخصص خفيف وسريع التنقل
                    ->hidden(fn ($livewire) => in_array($livewire->activeTab, ['today', 'tomorrow']))
                    ->displayFormat('Y-m-d'),


                    Forms\Components\DatePicker::make('until')->label('إلى تاريخ')
                    ->native(false)
                    ->hidden(fn ($livewire) => in_array($livewire->activeTab, ['today', 'tomorrow']))
                    ->displayFormat('Y-m-d'),
                ])

                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['from'], fn ($q, $date) => $q->whereDate('travel_date', '>=', $date))
                        ->when($data['until'], fn ($q, $date) => $q->whereDate('travel_date', '<=', $date));
                })

                ->columns(2)
                ->columnSpan(2)

                // حقول التاريخ اخفيهن بهدول التبويبين 


        ], layout: Tables\Enums\FiltersLayout::AboveContent)  // عشان يظهر الفلتر فوق الجدول
                    





        ->actions([
//             إذا كان الـ user_id يحتوي على قيمة (يعني حجز تطبيق)، الزر بيظهر ✅.
//             إذا كان الـ user_id فاضي null (يعني حجز مكتبي)، الزر بيختفي تماماً ❌

            Tables\Actions\ViewAction::make()
                ->modalWidth('5xl')
                //اظهر زر العرض لكل الحجوزات المكتبية والتطبيق
                ->visible(function ($livewire, $record) {
                    $activeTab = $livewire->activeTab;
                    
                    // اذا كان السجل فيو اسم مستخدم فعلي (حجز تطبيق) بهدول التبويبات اعرضلهن 
                        if (in_array($activeTab, ['today', 'tomorrow', 'all'])) {
                            return $record->user_id !== null;
                        }

                        // بهدول التبويبات اعرض لكل السجلات زر عرض ساء عبر المكتب او عبر التطبيق
                        if (in_array($activeTab, ['today_past', 'past', 'cancelled'])) {
                            return true;
                        }
                        return false;
                }),



            Tables\Actions\EditAction::make() ->modalWidth('5xl')   // لتوسيع النافذة المنبثقة
                ->visible(function ($livewire, $record) {
                    $activeTab = $livewire->activeTab;
                    
                    $allowedTabs = ['today', 'tomorrow', 'all'];

                    // اظهر زر التعديل فقط للحجوزات مكتبية
                    return in_array($activeTab, $allowedTabs) && $record->user_id === null;
                }),



            Tables\Actions\DeleteAction::make()

                ->visible(function ($livewire, $record) {
                    $activeTab = $livewire->activeTab;
                    

                        if (in_array($activeTab, ['today', 'tomorrow', 'all'])) {
                            return $record->user_id === null;
                        }


                        if (in_array($activeTab, ['past', 'cancelled'])) {
                            return true;
                        }
                        return false;
                }),


            ])




            ->bulkActions([

                Tables\Actions\BulkActionGroup::make([

                    Tables\Actions\DeleteBulkAction::make()
                    
                        ->visible(function (\Filament\Tables\Table $table) {
                            // دالة data_get بتجيب القيمة بأمان وبدون أي تنبيهات صفراء من المحرر
                            $activeTab = data_get($table->getLivewire(), 'activeTab', 'today');

                            $allowedBulkTabs = ['past', 'cancelled'];

                            return in_array($activeTab, $allowedBulkTabs);
                        }),
                ]),
            ]);
            
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
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
        ];
    }
}
