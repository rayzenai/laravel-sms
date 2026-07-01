<?php

namespace Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Rayzenai\LaravelSms\Filament\Concerns\WithSegmentPreview;
use Rayzenai\LaravelSms\Filament\Resources\SmsSegmentResource;

class CreateSmsSegment extends CreateRecord
{
    use WithSegmentPreview;

    protected static string $resource = SmsSegmentResource::class;
}
