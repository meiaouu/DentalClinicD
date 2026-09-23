<?php

namespace App\Services;

use InvalidArgumentException;

class PhoneNumberService
{
    public function normalizePhilippineMobile(string $input): string
    {
        $value = preg_replace('/\D+/', '', $input ?? '');

        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Mobile number is required.');
        }

        if (str_starts_with($value, '639') && strlen($value) === 12) {
            return $value;
        }

        if (str_starts_with($value, '09') && strlen($value) === 11) {
            return '63' . substr($value, 1);
        }

        if (str_starts_with($value, '9') && strlen($value) === 10) {
            return '63' . $value;
        }

        throw new InvalidArgumentException('Please enter a valid Philippine mobile number.');
    }
}