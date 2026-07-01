<?php

namespace Rayzenai\LaravelSms\Contracts;

/**
 * Implemented by any model (typically User) that can receive SMS.
 *
 * The model owns exactly how its phone number is derived — a single column, a
 * concatenation of country code + number, an accessor, whatever. Return the number
 * ready to send (E.164 like `+9779801002468` is recommended), or `null` when there
 * is no usable number for this record (the recipient is then skipped).
 */
interface HasSmsNumber
{
    public function smsPhoneNumber(): ?string;
}
