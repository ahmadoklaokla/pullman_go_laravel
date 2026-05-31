<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Route; 
use App\Models\City; 
use Illuminate\Http\Request;

class TripController extends Controller
{
    
    //  * جلب قائمة المدن/المحافظات المفعلة من قاعدة البيانات
    //  */
    public function getCities()
    {
        try {
            // جلب المحافظات التي حالتها "مفعلة" (is_active = 1)
            $cities = City::where('is_active', 1)->get(['id', 'name']);
            
            return response()->json([
                'status' => true,
                'data' => $cities
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء جلب المدن',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // /
    //  * البحث عن الرحلات المتاحة بناءً على المدن واليوم المختار
    //  */
    public function search(Request $request)
    {
        // التحقق من صحة المدخلات (معرف مدينة الانطلاق والوصول)
        $request->validate([
            'from_id' => 'required|exists:cities,id', 
            'to_id' => 'required|exists:cities,id',   


            // ضفت التحقق من التاريخ
            'date' => 'required|date',

        ]);

        try {
            // 1. بناء الاستعلام الأساسي للمسارات وجلب العلاقات (الشركة والمدن)
            $query = Route::with(['company', 'departureCity', 'arrivalCity'])
                ->where('departure_city_id', $request->from_id)
                ->where('arrival_city_id', $request->to_id);

        // البحث عن الرحلات في حقل ال trip_date 
            if ($request->has('date') && $request->date !== null) {
                $query->whereHas('trips', function($q) use ($request) {

                    $q->whereDate('trip_date', $request->date)

                      ->where('is_active', 1); // لضمان جلب الرحلات المفعلة فقط
                });
            }

            $trips = $query->get();


            // قاموس لترجمة الميزات من الإنجليزية للعربية لعرضها في التطبيق
            $featuresTranslation = [
                'wifi' => 'واي فاي مجاني',
                'ac' => 'تكييف هواء',
                'comfortable_seats' => 'مقاعد مريحة',
                'usb' => 'شواحن USB',
                'wc' => 'دورة مياه داخلية',
                'screen' => 'شاشات عرض',
                'water' => 'توزيع مياه ضيافة',
                'snacks' => 'وجبات خفيفة',
                'coffee' => 'مشروبات ساخنة',
                'gps' => 'تتبع مباشر (GPS)',
                'camera' => 'كاميرات مراقبة',
                'insurance' => 'تأمين سفر شامل',
                'luggage' => 'خدمة الأمتعة',
                'extra_bag' => 'وزن إضافي',
            ];

            // تحويل ميزات كل شركة إلى نصوص عربية
            foreach ($trips as $trip) {
                if ($trip->company && !empty($trip->company->features)) {
                    $translatedFeatures = [];
                    
                    // التأكد إذا كانت الميزات مخزنة كنص JSON أو مصفوفة
                    $rawFeatures = $trip->company->features;
                    $features = is_string($rawFeatures)
                        ? json_decode((string)$rawFeatures, true) 
                        : $rawFeatures;

                    if (is_array($features)) {
                        foreach ($features as $feature) {
                            // حل المشكلة: التأكد أن الميزة نص وليس مصفوفة قبل فحص الترجمة
                            if (is_string($feature)) {
                                $translatedFeatures[] = $featuresTranslation[$feature] ?? $feature;
                            } elseif (is_array($feature) && isset($feature['name'])) {
                                // إذا كانت الميزة مصفوفة وبداخلها حقل الاسم
                                $translatedFeatures[] = $featuresTranslation[$feature['name']] ?? $feature['name'];
                            }
                        }
                    }
    
    $trip->company->features = $translatedFeatures;
}
            }

            return response()->json([
                'status' => true,
                'count' => $trips->count(),
                'data' => $trips
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'حدث خطأ أثناء البحث',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // /
    //  * جلب تفاصيل شركة معينة
    //  */
    public function getCompanyDetails($id)
    {
        try {
            $company = \App\Models\Company::find($id);
            if (!$company) {
                return response()->json(['status' => false, 'message' => 'الشركة غير موجودة'], 404);
            }
            return response()->json(['status' => true, 'data' => $company], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // /
    //  * جلب المواعيد التفصيلية المتاحة فقط في اليوم المختار
    //  */
   /**
     * جلب المواعيد التفصيلية المتاحة فقط في اليوم المختار مع بيانات المسار والكراجات
     */
    public function getCompanyTrips(Request $request)
{
    try {


        // 💡 جلب بيانات المسار والباص المرتبط بالرحلة ديناميكياً بناءً على حقول قاعدة البيانات الحالية
        $query = \App\Models\Trip::with
        ([
            'route', // جلب موديل المسار كامل ببياناته وعناوينه وأسعاره
            'bus'    // جلب موديل الباص كامل بعدد المقاعد ورقم الباص
        ])

        ->where('company_id', $request->company_id)
        ->where('route_id', $request->route_id);



        // 💡 التعديل هنا: الفلترة حسب التاريخ الفعلي (trip_date)
            if ($request->has('date') && $request->date !== null) {
                $query->whereDate('trip_date', $request->date)
                
                      ->where('is_active', 1); // للتأكد إن الرحلة نشطة
            }


        
// 💡 التعديل هنا: جلب الكائن كامل لضمان دمج مصفوفة الـ route والـ bus بنجاح للتطبيق
            $trips = $query->get();



        return response()->json([
            'status' => true,
            'data' => $trips
        ], 200);

    } catch (\Exception $e) {
        return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
    }
}



    // داخل TripController.php

    public function getReservedSeats(Request $request)
{
    // نحتاج للـ trip_id والتاريخ للتأكد من حجز المقاعد لهذا الموعد بالضبط
    $reservedSeats = \App\Models\BookingSeat::where('trip_id', $request->trip_id)
        ->where('travel_date', $request->travel_date)
        ->pluck('seat_number')
        ->toArray();

    return response()->json([
        'status' => true,
        'reserved_seats' => $reservedSeats
    ]);
}







/**
 * إلغاء حجز معين للمسافر الحالي بأمان
 */
public function cancelBooking(Request $request)
{
    // التحقق من تمرير معرف الحجز
    $request->validate([
        'booking_id' => 'required|integer|exists:bookings,id',
    ]);

    // جلب المستخدم
    $user = $request->user();

    // 👈 حماية إضافية: إذا التوكن مو واصل أو منتهي اقطع العملية فوراً بدون ما ينهار السيرفر
    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'عذراً، الجلسة منتهية أو لم يتم التعرف على المستخدم. يرجى إعادة تسجيل الدخول.'
        ], 401);
    }

    try {
        // البحث عن الحجز التابع لهذا المستخدم حصراً
        $booking = \App\Models\Booking::where('id', $request->booking_id)
            ->where('user_id', $user->id) // هسا الآيدي آمن تماماً وماراح يضرب null
            ->first();

        if (!$booking) {
            return response()->json([
                'status' => false,
                'message' => 'عذراً، هذا الحجز غير موجود أو لا يتبع لحسابك.'
            ], 404);
        }

        // التحقق مما إذا كان الحجز ملغى مسبقاً
        if ($booking->payment_status === 'cancelled') {
            return response()->json([
                'status' => false,
                'message' => 'هذا الحجز ملغى بالفعل.'
            ], 400);
        }

        // تحديث حالة الحجز إلى ملغى
        $booking->update([
            'payment_status' => 'cancelled'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم إلغاء رحلتك بنجاح وتحرير المقاعد.'
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'حدث خطأ غير متوقع أثناء إلغاء الحجز',
            'error' => $e->getMessage()
        ], 500);
    }
}

}