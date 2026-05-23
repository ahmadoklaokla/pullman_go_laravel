<?php

namespace App\Filament\Staff\Resources\BookingResource\Pages;

use App\Filament\Staff\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;


use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;


class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }



    public function getTabs(): array
    {
        // توقيت سوريا مشان يعمل عملية نقل الحجوزات الساعة 12 صباحا
        $tz = 'Asia/Damascus';

        // جلب الوقت الحالي بالساعة والدقيقة والثانية بتوقيت دمشق
        $nowTime = Carbon::now($tz)->format('H:i:s');



        // 🔴إذا المستخدم واقف على تبويب "حجوزات اليوم"، اقلب الحجوزات لمقروءة 
        if ($this->activeTab === 'today') {
            BookingResource::getModel()::whereDate('travel_date', Carbon::today($tz))
                ->where('payment_status', '!=', 'cancelled')
                ->where('is_seen', false)

                ->whereHas('trip', function ($q) use ($nowTime) {
                    $q->whereRaw("TIME_TO_SEC(scheduled_time) + 300 >= TIME_TO_SEC('{$nowTime}')");
                })
                ->update(['is_seen' => true]);
        }




        if ($this->activeTab === 'all') { 
            BookingResource::getModel()::whereDate('travel_date', '>=', Carbon::today($tz))
                ->where('payment_status', '!=', 'cancelled')
                ->where('is_seen', false)
                ->update(['is_seen' => true]);
        }




        if ($this->activeTab === 'tomorrow') { 
            BookingResource::getModel()::whereDate('travel_date', Carbon::tomorrow($tz))
                ->where('payment_status', '!=', 'cancelled')
                ->where('is_seen', false)
                ->update(['is_seen' => true]);
        }
            



        // للملغية
        if ($this->activeTab === 'cancelled') { 
            BookingResource::getModel()::where('payment_status', 'cancelled')
                ->where('is_seen', false)
                ->update(['is_seen' => true]);
        }
                



        return [
            
            'today' => Tab::make('حجوزات اليوم')
                ->icon('heroicon-m-calendar-days')

    // 🔴 العداد بيعد فقط الحجوزات اللي ما انشافت (is_seen هو false)
                ->badge(fn () => BookingResource::getModel()::whereDate('travel_date', Carbon::today($tz))
                    ->where('payment_status', '!=', 'cancelled')
                    ->where('is_seen', false)
                    
                    ->whereHas('trip', function ($q) use ($nowTime) {
                        $q->whereRaw("TIME_TO_SEC(scheduled_time) + 300 >= TIME_TO_SEC('{$nowTime}')");
                    })
                    ->count() ?: null // إذا صفر ما يظهر شي عشان يضل الشكل نظيف
                )
                ->badgeColor('danger')

                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereDate('travel_date', Carbon::today($tz))
                    ->where('payment_status', '!=', 'cancelled')

                    // استقبل حجوزات اليوم الي موعد انطلاق الرحلة بيكون فيها اكبر من الوقت الفعلي الحالي 
                    ->whereHas('trip', function ($q) use ($nowTime) {
                        $q->whereRaw("TIME_TO_SEC(scheduled_time) + 300 >= TIME_TO_SEC('{$nowTime}')");
                    })
                ),





            'today_past' => Tab::make('حجوزات اليوم المنتهية')
                ->icon('heroicon-m-check-badge')

                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereDate('travel_date', Carbon::today($tz))
                        ->where('payment_status', '!=', 'cancelled')
                        
                        ->whereHas('trip', function ($q) use ($nowTime) {
                            // الحجوزات اللي مر على موعد انطلاقها أكثر من 10 دقائق (300 ثانية)
                            $q->whereRaw("TIME_TO_SEC(scheduled_time) + 300 < TIME_TO_SEC('{$nowTime}')");
                        })
                ),



            'tomorrow' => Tab::make('حجوزات غداً')
                ->icon('heroicon-m-arrow-trending-up')

                ->badge(fn () => BookingResource::getModel()::whereDate('travel_date', Carbon::tomorrow($tz))
                    ->where('payment_status', '!=', 'cancelled')
                    ->where('is_seen', false) 
                    ->count() ?: null //إذا صفر ما يظهر شي    
                )
                ->badgeColor('danger')

                
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereDate('travel_date', Carbon::tomorrow($tz))
                ),



            // يظهر فقط الرحلات الحالية والمستقبلية
            'all' => Tab::make('كل الحجوزات')
                ->icon('heroicon-m-rectangle-stack')

                ->badge(fn () => BookingResource::getModel()::whereDate('travel_date','>=', Carbon::today($tz))
                ->where('payment_status', '!=', 'cancelled')
                    ->where('is_seen', false) 
                    ->count() ?: null // إذا صفر ما يظهر شي عشان يضل الشكل نظيف
                )
                ->badgeColor('danger')

                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereDate('travel_date', '>=', Carbon::today($tz))
                    ->where('payment_status', '!=', 'cancelled')
                ),




            'cancelled' => Tab::make('الملغية')
                ->icon('heroicon-m-x-circle')

                ->badge(fn () => BookingResource::getModel()::where('payment_status', 'cancelled')
                    ->where('is_seen', false) 
                    ->count() ?: null
                )
                ->badgeColor('danger')
                
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('payment_status', 'cancelled') // يجلب فقط الملغي بكل التواريخ
                ),




            'past' => Tab::make('الحجوزات المنتهية')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereDate('travel_date', '<', Carbon::today($tz))
                    // لا تجيبلي الحجوزات الملغية
                        ->where('payment_status', '!=', 'cancelled')
                ),
        ];
    }
}
