<?php

namespace Rayzenai\LaravelSms\Rules;

use Illuminate\Contracts\Validation\Rule;

class NepaliPhoneNumber implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // Remove any spaces or special characters except + and digits
        $cleaned = preg_replace('/[^\d+]/', '', $value);
        
        // Pattern for Nepali phone numbers:
        // - Must start with +977 (country code)
        // - Followed by 9 (for mobile numbers)
        // - Then 9 more digits
        // Total: +977 9XXXXXXXXX (14 characters)
        $pattern = '/^\+977[9][0-9]{9}$/';
        
        return preg_match($pattern, $cleaned) === 1;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be a valid Nepali mobile phone number starting with +977 followed by 10 digits (e.g., +977 9801002468).';
    }
}
