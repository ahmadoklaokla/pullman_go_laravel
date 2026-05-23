<?php

namespace App\Filament\Staff\Resources\TripResource\Pages;

use App\Filament\Staff\Resources\TripResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrip extends EditRecord
{
    protected static string $resource = TripResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }




    // مشان لما اعدل على سطر في جدول الرحلات يوخذ التعديل ويعدل على جميع الرحلات المتكررة لهذا السجل (السطر فقط)

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // 1. منحتفظ بالبيانات القديمة قبل التعديل عشان نقدر نصيد الرحلات المكررة بالداتابيز
        $oldRouteId = $record->getOriginal('route_id');
        $oldBusId = $record->getOriginal('bus_id');
        $oldTime = $record->getOriginal('scheduled_time');

        // 2. منحدث السجل اللي الموظف فات عليه
        $record->update($data);

        // 3. منروح عالداتابيز، ومنحدث كل الرحلات اللي بتشبه البيانات القديمة للرحلة
        \App\Models\Trip::where('id', '!=', $record->id)
            ->where('route_id', $oldRouteId)
            ->where('bus_id', $oldBusId)
            ->where('scheduled_time', $oldTime)

            ->update([
                'route_id'       => $data['route_id'],
                'bus_id'         => $data['bus_id'],
                'scheduled_time' => $data['scheduled_time'],
// استخدمنا array_values عشان نضمن إنها مصفوفة نظيفة قبل ما نحولها لـ JSON
            'days_of_week'   => json_encode(array_values($data['days_of_week'] ?? [])), 
            'is_active'      => $data['is_active'] ?? true,
            ]);

        return $record;
    }
}
