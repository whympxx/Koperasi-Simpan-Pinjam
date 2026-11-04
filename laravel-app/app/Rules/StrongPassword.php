<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka. */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = (string) $value;

        if (strlen($value) < 8) {
            $fail('Password minimal 8 karakter.');
        }
        if (! preg_match('/[A-Z]/', $value)) {
            $fail('Password harus mengandung huruf besar.');
        }
        if (! preg_match('/[a-z]/', $value)) {
            $fail('Password harus mengandung huruf kecil.');
        }
        if (! preg_match('/[0-9]/', $value)) {
            $fail('Password harus mengandung angka.');
        }
    }
}
