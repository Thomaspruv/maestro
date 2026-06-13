<?php

namespace App\Services;

class ValidationResult
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        private readonly bool $passed,
        private readonly array $errors = [],
    ) {}

    public function passes(): bool
    {
        return $this->passed;
    }

    public function errorsAsString(): string
    {
        if ($this->errors === []) {
            return '';
        }

        $lines = [];

        foreach ($this->errors as $key => $message) {
            $lines[] = "[{$key}] {$message}";
        }

        return implode("\n", $lines);
    }

    public function summary(): string
    {
        if ($this->passed) {
            return 'Toutes les validations ont réussi.';
        }

        return sprintf(
            'Échec de validation (%d erreur(s)) : %s',
            count($this->errors),
            implode(', ', array_keys($this->errors)),
        );
    }
}
