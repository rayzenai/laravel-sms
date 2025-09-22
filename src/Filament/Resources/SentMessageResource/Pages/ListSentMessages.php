<?php

namespace Rayzenai\LaravelSms\Filament\Resources\SentMessageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Rayzenai\LaravelSms\Filament\Resources\SentMessageResource;

class ListSentMessages extends ListRecords
{
    protected static string $resource = SentMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
