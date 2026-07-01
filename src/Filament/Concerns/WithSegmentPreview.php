<?php

namespace Rayzenai\LaravelSms\Filament\Concerns;

use Rayzenai\LaravelSms\Segments\SegmentQuery;

/**
 * Gives a Filament page/component the Livewire method the compact segment
 * builder calls to show a live match count. Returns a plain array so it
 * round-trips cleanly to the Alpine front-end.
 */
trait WithSegmentPreview
{
    /**
     * @param  array<string, mixed>  $tree
     * @return array{ok: bool, count?: int, error?: string}
     */
    public function previewSegmentCount(array $tree): array
    {
        try {
            $count = SegmentQuery::for(config('laravel-sms.user_model.class'), $tree)->count();

            return ['ok' => true, 'count' => $count];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
