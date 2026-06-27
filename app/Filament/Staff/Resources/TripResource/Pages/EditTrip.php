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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $oldRouteId = $record->getOriginal('route_id');
        $oldBusId = $record->getOriginal('bus_id');
        $oldTime = $record->getOriginal('scheduled_time');

        $startDate = \Carbon\Carbon::parse($data['start_date']);
        $endDate = \Carbon\Carbon::parse($data['end_date']);
        $daysOfWeek = array_map('intval', $data['days_of_week'] ?? []);

        $targetDates = [];
        $period = \Carbon\CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            if (in_array($date->dayOfWeek, $daysOfWeek)) {
                $targetDates[] = $date->format('Y-m-d');
            }
        }

        $existingTrips = \App\Models\Trip::where('route_id', $oldRouteId)
            ->where('bus_id', $oldBusId)
            ->where('scheduled_time', $oldTime)
            ->get();

        $existingTripsByDate = $existingTrips->keyBy('trip_date');
        
        $keptTripIds = [];
        $datesToCreate = [];

        foreach ($targetDates as $date) {
            if (isset($existingTripsByDate[$date])) {
                $trip = $existingTripsByDate[$date];
                $trip->update([
                    'route_id'       => $data['route_id'],
                    'bus_id'         => $data['bus_id'],
                    'scheduled_time' => $data['scheduled_time'],
                    // التعديل هنا: شلنا json_encode
                    'days_of_week'   => array_values($data['days_of_week'] ?? []), 
                    'is_active'      => $data['is_active'] ?? true,
                    'start_date'     => $data['start_date'],
                    'end_date'       => $data['end_date'],
                ]);
                $keptTripIds[] = $trip->id;
            } else {
                $datesToCreate[] = $date;
            }
        }

        $excessTrips = $existingTrips->whereNotIn('id', $keptTripIds);

        $recordTrip = $excessTrips->where('id', $record->id)->first();
        $otherExcessTrips = $excessTrips->where('id', '!=', $record->id)->values();

        foreach ($datesToCreate as $newDate) {
            if ($recordTrip) {
                $recordTrip->update([
                    'route_id'       => $data['route_id'],
                    'bus_id'         => $data['bus_id'],
                    'scheduled_time' => $data['scheduled_time'],
                    // التعديل هنا
                    'days_of_week'   => array_values($data['days_of_week'] ?? []), 
                    'is_active'      => $data['is_active'] ?? true,
                    'start_date'     => $data['start_date'],
                    'end_date'       => $data['end_date'],
                    'trip_date'      => $newDate,
                ]);
                $keptTripIds[] = $recordTrip->id;
                $recordTrip = null; 
            } elseif ($otherExcessTrips->isNotEmpty()) {
                $tripToRecycle = $otherExcessTrips->pop();
                $tripToRecycle->update([
                    'route_id'       => $data['route_id'],
                    'bus_id'         => $data['bus_id'],
                    'scheduled_time' => $data['scheduled_time'],
                    // التعديل هنا
                    'days_of_week'   => array_values($data['days_of_week'] ?? []), 
                    'is_active'      => $data['is_active'] ?? true,
                    'start_date'     => $data['start_date'],
                    'end_date'       => $data['end_date'],
                    'trip_date'      => $newDate,
                ]);
                $keptTripIds[] = $tripToRecycle->id;
            } else {
                \App\Models\Trip::create([
                    'company_id'     => $record->company_id ?? auth()->user()->company_id,
                    'route_id'       => $data['route_id'],
                    'bus_id'         => $data['bus_id'],
                    'scheduled_time' => $data['scheduled_time'],
                    // التعديل هنا
                    'days_of_week'   => array_values($data['days_of_week'] ?? []), 
                    'is_active'      => $data['is_active'] ?? true,
                    'start_date'     => $data['start_date'],
                    'end_date'       => $data['end_date'],
                    'trip_date'      => $newDate,
                ]);
            }
        }

        foreach ($otherExcessTrips as $remainingExcess) {
            if (!in_array($remainingExcess->id, $keptTripIds)) {
                $remainingExcess->delete();
            }
        }

        if ($recordTrip) {
            $recordTrip->update([
                'route_id'       => $data['route_id'],
                'bus_id'         => $data['bus_id'],
                'scheduled_time' => $data['scheduled_time'],
                // التعديل هنا
                'days_of_week'   => array_values($data['days_of_week'] ?? []), 
                'is_active'      => $data['is_active'] ?? true,
                'start_date'     => $data['start_date'],
                'end_date'       => $data['end_date'],
                'trip_date'      => end($targetDates) ?: $recordTrip->trip_date,
            ]);
        }

        return $record;
    }
}