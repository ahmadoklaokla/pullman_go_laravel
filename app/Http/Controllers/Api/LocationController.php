<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TripLocation;

use App\Models\Trip;
use Carbon\Carbon;



class LocationController extends Controller
{


// هي الدالة بتجيبلي كل الرحلات الي سائق الباص تبع كل رحلة هوا نفس السائق الي مسجل دخوله حاليا بالتطبيق 
    public function getCurrentTrip(Request $request)
    {

        $driver = $request->user();
            
            // 🌟 هون السر: إذا التطبيق بعث تاريخ بياخده، وإذا ما بعث بياخد تاريخ اليوم الفعلي تلقائياً
            $selectedDate = $request->query('date', Carbon::today()->toDateString());

            // جلب كل الرحلات النشطة بالتاريخ المحدد، بشرط يكون السائق المعين للباص هو هاد السائق
            $activeTrips = Trip::with(['company', 'route', 'bus.driver'])
                ->whereHas('bus', function ($query) use ($driver) {
                    $query->where('driver_id', $driver->id); // جيب الرحلة اللي باصها مستلمه هاد السائق
                })

                ->whereDate('trip_date', $selectedDate)
                ->where('is_active', true)
                ->get();


                
            // إذا ما لقى ولا رحلة تابعة لإله في هاد التاريخ
            if ($activeTrips->isEmpty()) {
                return response()->json([
                    'status' => 'empty', 
                    'message' => 'لا توجد رحلات مجدولة لك في تاريخ ' . $selectedDate
                ], 200);
            }


        // تحويل الرحلات لشكل مصفوفة (Array) للقائمة المنسدلة بالفلاتر
        $tripsData = $activeTrips->map(function ($trip) {
            return [

                'trip_id'         => $trip->id,
                
                // الاسم اللي رح ينعرض بالسهم المنسدل (مثال: دمشق إلى حمص - الساعة 14:30)
                'display_name'    => ($trip->route->route_full_name ?? 'غير محدد') . ' - الساعة ' . $trip->scheduled_time,
                'trip_date'       => $trip->trip_date->format('Y-m-d'),
                'scheduled_time'  => $trip->scheduled_time,
                'company_name'    => $trip->company->name ?? 'غير محدد',
                'route_name'      => $trip->route ? $trip->route->route_full_name : 'غير محدد',
                
                // بيانات الباص المحددة لهي الرحلة بالذات
                'bus_numbernnn'   => $trip->bus->bus_numbernnn ?? 'غير محدد', 
                'bus_model'       => $trip->bus->bus_model ?? 'غير محدد',
                'total_seats'     => $trip->bus->total_seats ?? 0,

                // بيانات السائق
                'driver_name'     => $trip->bus->driver->name ?? 'غير محدد',
                'driver_phone'    => $trip->bus->driver->phone ?? 'غير محدد',

                // بيانات المعاون المرتبط بهاد الباص بالذات
                'assistant_name'  => $trip->bus->assistant_name ?? 'لا يوجد',
                'assistant_phone' => $trip->bus->assistant_phone ?? 'لا يوجد',
            ];
        });

        // منرجع القائمة كاملة للفلاتر بوضع النجاح
        return response()->json([
            'status' => 'success',
            'data'   => $tripsData
        ], 200);
    }





    // هاي الدالة اللي رح يستدعيها تطبيق السائق كل 20 ثواني
    public function store(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'speed'     => 'nullable|numeric',
            'trip_id'   => 'required|exists:trips,id', // تأكدنا إنه موجود
        ]);

        // احفظ مباشرة باستخدام الـ trip_id القادم من الفلاتر
        $location = TripLocation::create([
            'trip_id'   => $request->trip_id,
            'latitude'  => $request->latitude,
            'longitude' => $request->longitude,
            'speed'     => $request->speed ?? 0,
        ]);

        return response()->json(['success' => true, 'message' => 'تم استلام الإحداثيات بنجاح'], 200);
    }


}