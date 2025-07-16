<?php

namespace Rayzenai\LaravelSms\Filament\Resources\SentMessageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource;

class ViewSentMessage extends ViewRecord
{
    protected static string $resource = SentMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
