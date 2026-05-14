<?php

namespace App\Http\Requests\Agent\V1\Concerns;

use Illuminate\Validation\Validator;

trait ForbidsSensitiveAgentSecrets
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->sensitivePaths($this->all()) as $path) {
                $validator->errors()->add($path, 'Sensitive certificate secrets must not be sent to the API.');
            }
        });
    }

    /**
     * @param  array<string|int, mixed>  $payload
     * @return list<string>
     */
    private function sensitivePaths(array $payload, string $prefix = ''): array
    {
        $paths = [];
        $blocked = ['pin', 'a3_pin', 'a1_password', 'certificate_password', 'certificate_pin'];

        foreach ($payload as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (in_array(strtolower((string) $key), $blocked, true)) {
                $paths[] = $path;
            }

            if (is_array($value)) {
                array_push($paths, ...$this->sensitivePaths($value, $path));
            }
        }

        return $paths;
    }
}
