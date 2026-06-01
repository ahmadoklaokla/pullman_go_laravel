<x-dynamic-component
        :component="$getFieldWrapperView()"
        :field="$field"
    >
        @php
            // جلب البيانات الأساسية للرحلة
            $statePath = $getStatePath(); 
            $tripIdPath = str_replace('selected_seat', 'modal_trip_id', $statePath);
            $tripId = data_get($this, $tripIdPath);

            $totalSeats = 0;
            $bookedSeats = [];
            $otherPassengersSeats = []; // مصفوفة لتجميع مقاعد ركاب نفس المجموعة حالياً

            if ($tripId) {
                $trip = \App\Models\Trip::with('bus')->find($tripId); 
                if ($trip && $trip->bus) {
                    $totalSeats = $trip->bus->total_seats; 
                }




                // جلب أرقام المقاعد المحجوزة
                
                $bookedSeats = \App\Models\BookingSeat::whereHas('booking', function($query) use ($tripId) {
                    $query->where('trip_id', $tripId)

                          ->where('payment_status', '!=', 'cancelled');  // سيجعل المقاعد الملغية تظهر متاحة فوراً

                })->pluck('seat_number')->toArray();
            }





            // لقطة ذكية: قراءة مقاعد بقية الركاب في الـ Repeater الحالي من الـ Form لمنع الاختيار المزدوج
            $allData = $this->data ?? [];
            $passengers = data_get($allData, 'passengers', []);

            if (empty($passengers) && isset($this->mountedActionsData[0]['passengers'])) {
                $passengers = $this->mountedActionsData[0]['passengers'];
            }

            foreach ($passengers as $index => $passenger) {
                $seatValue = data_get($passenger, 'selected_seat');
                if ($seatValue) {
                    $passengerPath = preg_replace('/passengers\.\d+\.selected_seat/', "passengers.{$index}.selected_seat", $statePath);
                    $otherPassengersSeats[$passengerPath] = (int) $seatValue;
                }
            }

            $rowsCount = $totalSeats > 0 ? ceil($totalSeats / 4) : 0;

            $currentSelectedSeat = $getState();
        @endphp

    <style>
            .custom-bus-seat {
                width: 52px;
                height: 70px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: flex-end; /* عشان تبين القاعدة بالأسفل والمسند ممتد خلفها */
                cursor: pointer;
                position: relative;
                transition: all 0.25s ease-in-out;
                user-select: none;
                background: transparent; /* الخلفية تعتمد على القطع الداخلية */
            }

            /* 1. مسند الظهر والرأس المطاول من الخلف (3D) */
            .backrest {
                position: absolute;
                top: 0;
                width: 44px;
                height: 52px; /* مطاول وممتد من الخلف */
                border-radius: 10px 10px 4px 4px;
                z-index: 1;
                box-shadow: inset 0 4px 6px rgba(255,255,255,0.3), 0 4px 8px rgba(0,0,0,0.6);
                border: 1px solid rgba(0,0,0,0.2);
                transition: all 0.25s ease;
            }

            /* الجزء العلوي المدمج لمسند الرأس */
            .backrest::before {
                content: '';
                position: absolute;
                top: 3px;
                left: 50%;
                transform: translateX(-50%);
                width: 24px;
                height: 8px;
                background: rgba(255, 255, 255, 0.65);
                border-radius: 4px;
            }

            /* 2. قاعدة الكرسي الأمامية البارزة */
            .seat-cushion {
                width: 46px;
                height: 26px; /* قاعدة مريحة */
                border-radius: 4px 4px 8px 8px;
                z-index: 3; /* فوق مسند الظهر ليعطي البعد الثالث */
                box-shadow: inset 0 2px 4px rgba(255,255,255,0.4), 0 5px 6px rgba(0,0,0,0.65);
                border-bottom: 5px solid rgba(0,0,0,0.35); /* سماكة وعمق القاعدة */
                border-left: 1px solid rgba(0,0,0,0.15);
                border-right: 1px solid rgba(0,0,0,0.15);
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.25s ease;
            }

            /* 3. رقم الكرسي بوضوح فوق القاعدة */
            .custom-bus-seat .seat-num {
                font-size: 15px;
                font-weight: 900;
                text-shadow: 0 1px 2px rgba(0,0,0,0.6);
                z-index: 4;
            }

            /* 4. مساند اليدين الجانبية باللون الأسود الواضح جداً على أجناب القاعدة */
            .armrest {
                position: absolute;
                bottom: 8px;
                width: 5px;
                height: 24px;
                background: linear-gradient(to bottom, #2d3748, #000000); /* أسود ملكي 3D */
                border-radius: 3px;
                z-index: 5; /* بره وجنب القاعدة تماماً */
                box-shadow: 1px 2px 4px rgba(0,0,0,0.4);
                border: 0.5px solid #4a5568;
            }
            /* مسافة ايدين الكرسي عن القاعدة والظهر */
            .armrest-right { right: -3px; }
            .armrest-left { left: -3px; }
            
            /* 🔵 تأثيرات الألوان والحالات على القطع (متاح، محجوز، محدد) */
            
            /* حالة المتاح (أخضر خيالي) */
            .seat-green .backrest { background: linear-gradient(135deg, #22c55e, #15803d); }
            .seat-green .seat-cushion { background: linear-gradient(135deg, #4ade80, #16a34a); }
            .seat-green { color: #ffffff; }
            .seat-green:hover {
                transform: translateY(-5px) scale(1.05);
            }
            .seat-green:hover .backrest { box-shadow: 0 12px 20px rgba(34, 197, 94, 0.4); }

            /* حالة المحجوز (أحمر ناري) */
            .seat-red .backrest { background: linear-gradient(135deg, #ef4444, #b91c1c); }
            .seat-red .seat-cushion { background: linear-gradient(135deg, #f87171, #dc2626); }
            .seat-red { color: #ffffff; cursor: not-allowed; opacity: 0.85; }

            /* حالة المحدد (أسود فخم جداً والرقم بلون مميز لجذب العين) */
            .seat-black .backrest { background: linear-gradient(135deg, #374151, #111827); border-color: #4b5563; }
            .seat-black .seat-cushion { background: linear-gradient(135deg, #4b5563, #000000); border-bottom-color: #1f2937; }
            .seat-black { 
                color: #10b981; /* الرقم بيصير أخضر فوسفوري عشان يظهر بقوة جوا الأسود */
                transform: scale(1.15); 
                z-index: 10;
            }
            .seat-black .backrest { box-shadow: 0 15px 25px rgba(0,0,0,0.9); }

            /* حالة مقعد الراكب الزميل في المجموعة (مغلق وممنوع الضغط تماماً) */
            .seat-locked-group {
                cursor: not-allowed !important;
                pointer-events: none !important; /* قفل الماوس واللمس نهائياً */
            }
            .seat-locked-group .backrest { background: linear-gradient(135deg, #1f2937, #111827); border-color: #374151; box-shadow: none; }
            .seat-locked-group .seat-cushion { background: linear-gradient(135deg, #374151, #000000); border-bottom-color: #111827; box-shadow: none; }
            .seat-locked-group { color: #9ca3af; }
            .seat-locked-group::after {
                content: '🔒';
                position: absolute;
                top: -4px;
                right: -4px;
                font-size: 11px;
                z-index: 6;
            }
        </style>

        <div 
            x-data="{
                state: $wire.$entangle('{{ $getStatePath() }}'),
                bookedSeats: {{ json_encode($bookedSeats) }},
                groupSeats: {{ json_encode($otherPassengersSeats) }},
                currentPath: '{{ $statePath }}',

                // دالة التهيئة هنا
                init() {
                    let current = '{{ $currentSelectedSeat }}';
                    if (current) {
                        this.state = parseInt(current); // تحويل القيمة لرقم عشان المقارنة تشتغل صح
                    }
                },

                // فحص ما إذا كان المقعد يخص راكب آخر في نفس الحجز
                isSeatTakenByGroup(seat) {
                    let targetSeat = parseInt(seat);
                    for (let path in this.groupSeats) {
                        if (path !== this.currentPath && parseInt(this.groupSeats[path]) === targetSeat) {
                            return true;
                        }
                    }
                    return false;
                },

                selectSeat(seat) {
                    let targetSeat = parseInt(seat);
                    // المنع والاعتراض الصارم إذا كان المقعد مأخوذاً من الراكب الآخر أو محجوز بالسيستم
                    if (this.bookedSeats.includes(targetSeat) || this.isSeatTakenByGroup(targetSeat) || targetSeat > {{ $totalSeats }}) return;
                    this.state = this.state == targetSeat ? null : targetSeat;
                }
            }"
            style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; padding: 20px; background: #f9fafb; border-radius: 20px;"
            dir="rtl"
        >
            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 24px; margin-bottom: 30px; padding: 15px 25px; background: white; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; width: 100%;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; background: #22c55e; border-radius: 6px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);"></div>
                    <span style="font-weight: 800; color: #374151;">متاح</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; background: #ef4444; border-radius: 6px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);"></div>
                    <span style="font-weight: 800; color: #374151;">محجوز</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 24px; height: 24px; background: #111827; border-radius: 6px; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);"></div>
                    <span style="font-weight: 800; color: #374151;">محدد (أنت)</span>
                </div>

            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 25px; margin-bottom: 30px; width: 100%; padding-left: 10px; padding-right: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; background: #f8fafc; padding: 6px 14px; border-radius: 16px; border: 2px solid #e2e8f0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                    <span style="font-size: 13px; font-weight: 900; color: #475569; margin-left: 4px;">مدخل الحافلة</span>
                    
                    <div style="display: flex; gap: 2px; background: #334155; padding: 3px; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.15); border-bottom: 3px solid #1e293b;">
                        <div style="width: 16px; height: 36px; background: linear-gradient(135deg, #38bdf8, #0284c7); border-radius: 3px; position: relative; border-left: 2px solid #eab308; box-shadow: inset 0 1px 3px rgba(255,255,255,0.5);">
                            <div style="position: absolute; top: 14px; left: 1px; width: 2px; height: 8px; background: #000000; border-radius: 1px;"></div>
                        </div>
                        <div style="width: 16px; height: 36px; background: linear-gradient(135deg, #38bdf8, #0284c7); border-radius: 3px; position: relative; border-right: 2px solid #eab308; box-shadow: inset 0 1px 3px rgba(255,255,255,0.5);">
                            <div style="position: absolute; top: 14px; right: 1px; width: 2px; height: 8px; background: #000000; border-radius: 1px;"></div>
                        </div>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; align-items: center; position: relative;">
                    <div style="
                        width: 52px; 
                        height: 56px; 
                        background: linear-gradient(135deg, #2d3748, #111827); 
                        border-radius: 12px 12px 6px 6px; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        box-shadow: inset 0 3px 6px rgba(255,255,255,0.2), 0 6px 12px rgba(0,0,0,0.4);
                        border-bottom: 6px solid #000000;
                        border-top: 1px solid #4a5568;
                    ">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 32px; height: 32px; color: #94a3b8; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.4));">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="2" x2="12" y2="12"></line>
                            <line x1="12" y1="12" x2="4" y2="16"></line>
                            <line x1="12" y1="12" x2="20" y2="16"></line>
                        </svg>
                    </div>
                    <div style="position: absolute; bottom: -16px; background: #111827; color: #10b981; font-size: 11px; font-weight: 900; padding: 2px 10px; border-radius: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); border: 1px solid #1f2937;">
                        السائق
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 133px 1fr 1fr; gap: 35px 25px; justify-items: center; align-items: center;">
                @for ($r = 0; $r < $rowsCount; $r++)
                    @php
                        // توزيع أرقام المقاعد
                        $seatRight1 = $r * 4 + 1;
                        $seatRight2 = $r * 4 + 2;
                        $seatLeft1  = $r * 4 + 3;
                        $seatLeft2  = $r * 4 + 4;
                    @endphp

                    @foreach([$seatRight1, $seatRight2, 'aisle', $seatLeft1, $seatLeft2] as $seatNum)
                        @if($seatNum === 'aisle')
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                <div style="width: 4px; height: 12px; background: #e5e7eb; border-radius: 2px;"></div>
                            </div>
                        @else
                            @if($seatNum <= $totalSeats)
                                <div @click="selectSeat({{ $seatNum }})" 
                                    class="custom-bus-seat"
                                    :class="{
                                        'seat-red': bookedSeats.includes({{ $seatNum }}),
                                        'seat-locked-group': isSeatTakenByGroup({{ $seatNum }}),
                                        'seat-black': parseInt(state) === {{ $seatNum }} && !bookedSeats.includes({{ $seatNum }}),
                                        'seat-green': parseInt(state) !== {{ $seatNum }} && !bookedSeats.includes({{ $seatNum }}) && !isSeatTakenByGroup({{ $seatNum }})
                                    }">
                                    
                                    <div class="backrest"></div>
                                    <div class="armrest armrest-right"></div>
                                    <div class="armrest armrest-left"></div>
                                    
                                    <div class="seat-cushion">
                                        <span class="seat-num">{{ $seatNum }}</span>
                                    </div>
                                </div>
                            @else
                                <div></div> 
                            @endif
                        @endif
                    @endforeach
                @endfor
            </div>

            <div style="margin-top: 40px; padding-top: 15px; border-top: 3px solid #e5e7eb; display: flex; justify-content: center; width: 100%;">
                <div style="padding: 6px 20px; background: #f3f4f6; color: #9ca3af; font-size: 12px; font-weight: 800; border-radius: 20px;">
                    خريطة مقاعد الباص 
                </div>
            </div>
        </div>
    </x-dynamic-component>