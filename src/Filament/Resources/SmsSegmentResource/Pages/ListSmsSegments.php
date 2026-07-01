<?php

namespace Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource;

class ListSmsSegments extends ListRecords
{
    protected static string $resource = SmsSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New segment')
                ->icon('heroicon-o-plus'),
        ];
    }
}
