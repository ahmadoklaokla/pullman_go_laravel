<?php

namespace App\Filament\Staff\Resources\AppNotificationResource\Pages;

use App\Filament\Staff\Resources\AppNotificationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAppNotifications extends ListRecords
{
    protected static string $resource = AppNotificationResource::class;



    // 🔹 زر الإنشاء الموحد والعادي اللّي بيفتح الفورم الرئيسي
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('إنشاء إشعار جديد')
                ->icon('heroicon-o-plus'),
        ];
    }




    public function getTabs(): array
    {
        return [

            'passengers' => Tab::make('إشعارات المسافرين')
                ->icon('heroicon-o-users')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('target_role', 'passenger')),



            'drivers' => Tab::make('إشعارات السائقين')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('target_role', 'driver')),



            'all' => Tab::make('كافة الإشعارات')
                ->icon('heroicon-o-bell'),
        ];
    }
}