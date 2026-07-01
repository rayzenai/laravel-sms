<?php

namespace Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Rayzenai\LaravelSms\Filament\Concerns\WithSegmentPreview;
use Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource;

class EditSmsSegment extends EditRecord
{
    use WithSegmentPreview;

    protected static string $resource = SmsSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
