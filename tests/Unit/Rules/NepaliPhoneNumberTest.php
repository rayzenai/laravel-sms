<?php

namespace Rayzenai\LaravelSms\Tests\Unit\Rules;

use PHPUnit\Framework\TestCase;
use Rayzenai\LaravelSms\Rules\NepaliPhoneNumber;

class NepaliPhoneNumberTest extends TestCase
{
    protected $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new NepaliPhoneNumber();
    }

    /**
     * Test valid Nepali phone numbers
     */
    public function test_valid_nepali_phone_numbers_pass()
    {
        $validNumbers = [
            '+977 9801002468',  // With space
            '+9779801002468',   // Without space
            '+977 9812345678',  // Another valid number
            '+9779898765432',   // Different valid number
            '+977 9811111111',  // All ones after prefix
            '+9779800000000',   // All zeros after prefix
        ];

        foreach ($validNumbers as $number) {
            $this->assertTrue(
                $this->rule->passes('phone', $number),
                "Failed asserting that {$number} is a valid Nepali phone number"
            );
        }
    }

    /**
     * Test invalid Nepali phone numbers
     */
    public function test_invalid_nepali_phone_numbers_fail()
    {
        $invalidNumbers = [
            '9801002468',           // Missing country code
            '+977 801002468',       // Missing 9 prefix for mobile
            '+977 980100246',       // Too few digits
            '+977 98010024689',     // Too many digits
            '+1 9801002468',        // Wrong country code
            '+977 8801002468',      // Wrong mobile prefix (should start with 9)
            '+977 7801002468',      // Wrong mobile prefix
            'invalid',              // Not a number
            '',                     // Empty string
            '+977',                 // Only country code
            '+977 9',               // Incomplete number
            '+91 9801002468',       // Indian country code
            '977 9801002468',       // Missing + sign
        ];

        foreach ($invalidNumbers as $number) {
            $this->assertFalse(
                $this->rule->passes('phone', $number),
                "Failed asserting that {$number} is an invalid Nepali phone number"
            );
        }
    }

    /**
     * Test the error message
     */
    public function test_error_message()
    {
        $expected = 'The :attribute must be a valid Nepali mobile phone number starting with +977 followed by 10 digits (e.g., +977 9801002468).';
        $this->assertEquals($expected, $this->rule->message());
    }

    /**
     * Test that various formatting styles are handled correctly
     */
    public function test_phone_number_formatting_is_normalized()
    {
        $formattedNumbers = [
            '+977 980 100 2468',    // Multiple spaces
            '+977  9801002468',     // Double space
            '+977-980-100-2468',    // With dashes
            '+977.980.100.2468',    // With dots
            '+977(980)1002468',     // With parentheses
            '+977 980 100 2468 ',   // Trailing space
            ' +977 9801002468',     // Leading space
        ];

        foreach ($formattedNumbers as $number) {
            $this->assertTrue(
                $this->rule->passes('phone', $number),
                "Failed asserting that {$number} is valid after normalization"
            );
        }
    }
}
