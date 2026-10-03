<?php

namespace Laragear\Dte\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Laragear\Dte\Validation\ValidatesSiiDocuments;
use SensitiveParameter;
use function value;

class SiiCertificate implements ValidationRule
{
    /**
     * Create a new SII Certificate validation rule.
     *
     * @param  string|Closure  $password  The password, or a closure that returns it.
     */
    public function __construct(
        #[SensitiveParameter]
        protected string|Closure $password = 'password',
    ) {
        //
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!ValidatesSiiDocuments::validateCertificate(value($this->password), $value)) {
            $fail(trans('dte::validation.certificate'));
        }
    }
}
