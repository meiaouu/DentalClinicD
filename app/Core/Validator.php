<?php

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                if ($rule === 'required') {
                    if ($value === null || trim((string) $value) === '') {
                        $this->errors[$field][] = 'This field is required.';
                    }
                }

                if ($rule === 'email' && $value !== null && $value !== '') {
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field][] = 'Invalid email address.';
                    }
                }

                if (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (mb_strlen((string) $value) > $max) {
                        $this->errors[$field][] = "Maximum length is {$max} characters.";
                    }
                }

                if ($rule === 'numeric' && $value !== null && $value !== '') {
                    if (!is_numeric($value)) {
                        $this->errors[$field][] = 'This field must be numeric.';
                    }
                }
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}