<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DriverAuthController extends Controller
{
    public function loginDriver(Request $request)
    {
        // 1. التحقق من البيانات القادمة من فلاتر
        $request->validate([
            'login_field' => 'required|string',
            'password'    => 'required|string',
            'token'       => 'nullable|string',  // هاد هو توكن الفاير بيز او توكن الجهاز
            'role'        => 'required|string|in:driver',
        ]);


        // 2. فحص المدخل (إيميل أو هاتف)
        $loginType = filter_var($request->login_field, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // 3. البحث والتحقق الصارم
        $user = User::where($loginType, $request->login_field)->first();

        if (!$user || !Hash::check($request->password, $user->password) || $user->role !== 'driver') {
            return response()->json([
                'status' => 'error',
                'message' => "❌ البيانات المدخلة غير صحيحة \nأو ليس لديك صلاحية سائق"
            ], 401);
        }



        // 4. بيستقبل التوكن الي جابو الفلاتر من الفاير بيز وبحدثو بال db 
        // هاد هوا السطر تبع جلب التوكن من الفاير بيز للجهاز 
        // String? fcmToken = await FirebaseMessaging.instance.getToken();

        // مشان لما الموظف يبعث اشعار لسائق عندو بالشركة يوصل الو فقط 
        // مو مثل تطبيق المسافرين ببعث على القناة وبتصل للكل 
        $user->update([
            
        'fcm_token' => $request->token,
        'is_active' => true,  // حالة الحساب مفعلة صارت عند تسجيل الدخول للسائق ع التطبيق
        
        ]);

        // هاد التوكن تبع التحقق للسائق فقط
        $token = $user->createToken('driver_token')->plainTextToken;



        
        return response()->json([
            'status' => 'success',
            'message' => 'تم تسجيل الدخول بنجاح ! 🚀',
            'token' => $token,
            'user' => [

                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,

                'company_id' => $user->company_id,
            ]
        ], 200);
    }
}