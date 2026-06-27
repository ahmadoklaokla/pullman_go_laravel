<?php

namespace App\Filament\Staff\Resources\AppNotificationResource\Pages;

use App\Filament\Staff\Resources\AppNotificationResource;
use Filament\Resources\Pages\CreateRecord;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class CreateAppNotification extends CreateRecord
{
// مررت البيانات مرتين للمستخدم 
// withNotification مررت البيانات لشريط الاشعارات ع الموبايل
// withData مررت البيانات للمستخدم على صفحةالاشعارات بالتطبيق


    protected static string $resource = AppNotificationResource::class;

    // رقم 2: حقن البيانات تلقائياً 🌟
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // سحب الآيدي تبع الموظف اللي مسجل دخولو حالياً
        $data['sender_id'] = auth()->id();
        
        // سحب الآيدي تبع شركة هاد الموظف
        $data['company_id'] = auth()->user()->company_id;
        
        return $data;
    }




protected function afterCreate(): void
    {
        $record = $this->record;
        $user = auth()->user();
        $companyId = $user->company_id;
        
        $name = $user->company->name ?? 'PULLMAN GO';
        $logo_url = $user->company->logo_url ?? ''; 
        $fullLogoUrl = $logo_url ? asset('storage/' . $logo_url) : asset('images/default-logo.png');

        try {
            $messaging = app('firebase.messaging');
            
            // 🟢 فحص الـ 3 حالات وتحديد الهدف بالظبط
            if ($record->target_role === 'driver') {
                if ($record->send_to_all_drivers) {
                    // الحالة أ: إرسال لكل سائقين الشركة المحددة عبر توبك مخصص برقم الشركة
                    // مثال: company_drivers_5
                    $message = CloudMessage::withTarget('topic', 'company_drivers_' . $companyId);
                } else {
                    // الحالة ب: إرسال لسائق محدد عبر الـ Token الخاص بجهازه
                    $driverToken = $record->driver->fcm_token ?? null;
                    if (!$driverToken) return; // نلغي الإرسال إذا ما عنده توكن
                    
                    $message = CloudMessage::withTarget('token', $driverToken);
                }
            } else {
                // الحالة ج: إرسال لكافة المسافرين عبر التوبك العام
                $message = CloudMessage::withTarget('topic', 'passenger');
            }

            // إرسال البيانات الموحدة للفلتر ليرسمها بالصفحة
            $message = $message
                ->withNotification(Notification::create($record->title, $record->content)
                    ->withImageUrl($fullLogoUrl))
                ->withData([
                    'notification_id' => (string) $record->id,
                    'name'            => $name,         
                    'logo_url'        => $fullLogoUrl,  
                    'title'           => $record->title,
                    'content'         => $record->content,
                    'created_at'      => $record->created_at->format('Y-m-d H:i A'),
                    'click_action'    => 'FLUTTER_NOTIFICATION_CLICK',
                ]);

            $messaging->send($message);
            
        } catch (\Exception $e) {
            \Log::error("Firebase Error: " . $e->getMessage());
        }
    }



    // رسالة تظهر للموظف بعد نجاح الإرسال
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم إرسال الإشعار لجميع المستخدمين بنجاح!';
    }
    

    // توجيه الموظف للجدول بعد الإرسال بدل ما يضل بصفحة الإضافة
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}