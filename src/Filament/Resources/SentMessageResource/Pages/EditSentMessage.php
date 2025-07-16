<?php

namespace Rayzenai\LaravelSms\Filament\Resources\SentMessageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource;

class EditSentMessage extends EditRecord
{
    protected static string $resource = SentMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
