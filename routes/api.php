<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// استخدمت هاد الكلاس مشان رابط انشاء حساب جديد register
use App\Http\Controllers\Api\AuthController;

//للحجز
use App\Http\Controllers\Api\TripController;
//كونترولر الحجز
use App\Http\Controllers\Api\BookingController;
//كونترولر العروض
use App\Http\Controllers\Api\OfferController;
// كونترولر لمعالجة احداثيات الرحلة
use App\Http\Controllers\Api\LocationController;

// هاد الكونترولر تبع السائق لتسجيل الدخول
use App\Http\Controllers\Api\DriverAuthController;



Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// رابط إنشاء حساب للمسافرين
Route::post('/register', [AuthController::class, 'register']);


// رابط التحقق من الرمز
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);


//رابط تسجيل الدخول الي رح يطلبو التطبيق عن طريق دالة loginUser الموجودة بملف ال APIs
Route::post('/login', [AuthController::class, 'login']);


// رابط الي رح يطلبه التطبيق مشان يبعث للسيرفر الايميل او رقم الهاتف (نسيت كلمة المرور)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);


// رابط لارسال الايميل ورمز التحقق الي دخلو المستخدم بنسيت كلمة المرور 
Route::post('/verify-reset-otp', [AuthController::class, 'verifyResetOtp']);


//رابط لارسال كلمة المرور الجديدة من المستخدم 
Route::post('/reset-password', [AuthController::class, 'resetPassword']);



// لتطبيق السائق
// رابط لتسجيل الدخول للسائق 
Route::post('/login-driver', [DriverAuthController::class, 'loginDriver']);






// شغل البنات من الرئيسية

// رابط جلب المحافظات لعرضها في القائمة المنسدلة (Dropdown)
// وظيفته: يذهب لقاعدة البيانات ويحضر قائمة بكل المدن المتاحة

Route::get('/cities', [TripController::class, 'getCities']);


// رابط لجلب الشركات اعتمادا عالمحافظات يلي اخترناها بالبحث 
// يأخذ "المدن" التي اختارها المستخدم (مثلاً: من حمص إلى اللاذقية) ويرسلها للباك آند. الباك آند يبحث في جدول الرحلات والمسارات ويعيد فقط الشركات التي لديها رحلات مطابقة لهذا المسار.
Route::get('/search-trips', [TripController::class, 'search']);


//للمواعيد
Route::get('/get-company-trips', [TripController::class, 'getCompanyTrips']);


//لجلب العروض
Route::get('/offers', [OfferController::class, 'index']);


// الروابط الي ناقصة

// رابط جلب المقاعد المحجوزة للرحلة (ضروري جداً لشاشة المقاعد)
Route::get('/get-reserved-seats', [TripController::class, 'getReservedSeats']);

// رابط جلب تفاصيل الشركة
Route::get('/company-details/{id}', [TripController::class, 'getCompanyDetails']);


// رابط الـ API  المسؤول عن حفظ وتأكيد الحجوزات والمقاعد بالكامل
// اضافة الى الدفع الالكتروني
Route::post('/store-booking', [BookingController::class, 'store']);






// عملت هي الحماية مشان تعديل بالملف الشخصي

// 2.  الروابط المحمية (لازم توكككننن عشان تشتغل وما تضرب)
// حماية الـ Sanctum. هاد معناه: "يا سيرفر، أي طلب بيجيك على الروابط هي بدون توكن
// ارفضه لهيك كنت مجبور امرر التوكن بكل الواجهات بالفلاتر

Route::middleware('auth:sanctum')->group(function () {
    
    // رابط جلب بيانات المستخدم
    Route::get('/user', function (Request $request) {
        return $request->user();
    });


    // رابط تحديث بيانات المستخدم في ملفه الشخصي (الرقم والاسم) في حال التحديث
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);


    //جديد لرحلاتي 
    //لرحلاتي لقادمة والسابقة
    Route::get('/user-trips', [BookingController::class, 'getUserTrips']);


    // مسار إلغاء الحجز الجديد
    Route::post('/cancel-booking', [TripController::class, 'cancelBooking']);


    //الرابط الي رح يطلبو الفلاتر مشان يوخذ بيانات الرحلة من السيرفر للسائق
    Route::get('/driver/current-trip', [App\Http\Controllers\Api\LocationController::class, 'getCurrentTrip']);


    // رابط لاستقبال الاحداثيات من السائق
    Route::post('/track-location', [LocationController::class, 'store']);
    
});