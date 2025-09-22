<?php

namespace Rayzenai\LaravelSms\Models;

use Illuminate\Database\Eloquent\Model;

class SentMessage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sent_messages';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<string>|bool
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'provider_response' => 'array',
    ];
}
