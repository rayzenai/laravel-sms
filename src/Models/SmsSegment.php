<?php

namespace Rayzenai\LaravelSms\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Rayzenai\LaravelSms\Segments\SegmentQuery;

/**
 * A saved, named query over the application's user table. The query definition
 * is stored; the matching users are always recomputed, never cached.
 *
 * @property string $name
 * @property array $conditions
 * @property int|null $previous_count
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
class SmsSegment extends Model
{
    protected $table = 'sms_segments';

    protected $guarded = [];

    protected $casts = [
        'conditions' => 'array',
        'previous_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * The evaluator that turns this segment's conditions into a safe query.
     */
    public function segmentQuery(): SegmentQuery
    {
        return new SegmentQuery($this->userModelClass(), $this->conditions ?? []);
    }

    /**
     * How many users this segment currently matches.
     */
    public function matchCount(): int
    {
        return $this->segmentQuery()->count();
    }

    /**
     * The users this segment currently matches.
     *
     * @return Collection<int, Model>
     */
    public function recipients(): Collection
    {
        return $this->segmentQuery()->users();
    }

    /**
     * Record that the segment was just used to send, with its live match count.
     */
    public function markUsed(int $count): void
    {
        $this->forceFill([
            'previous_count' => $count,
            'last_used_at' => now(),
        ])->save();
    }

    protected function userModelClass(): string
    {
        return (string) config('laravel-sms.user_model.class');
    }
}
