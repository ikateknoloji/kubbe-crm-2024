<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurkishPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Numara + ile başlayabilir veya sadece rakamlardan oluşabilir
        if (!preg_match('/^(\+?\d{1,3})?(\d{10,14})$/', $value)) {
            $fail('Telefon numarası geçerli bir formatta olmalıdır.');
        }
    }
}