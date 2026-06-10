<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ListPackagesRequest extends FormRequest
{
    private const ALLOWED_KEYS = ['language', 'registry', 'capability', 'status', 'cursor', 'per_page'];

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'language' => ['nullable', 'string', 'alpha_dash', 'max:60'],
            'registry' => ['nullable', 'string', 'alpha_dash', 'max:30'],
            'capability' => ['nullable', 'string', 'alpha_dash', 'max:120'],
            'status' => ['nullable', 'string', 'in:active,archived'],
            'cursor' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! (bool) config('codex.api.strict_query_keys', true)) {
            return;
        }
        $unknown = array_diff(array_keys($this->query->all()), self::ALLOWED_KEYS);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'query' => ['Unknown filter parameter(s): '.implode(', ', $unknown)],
            ]);
        }
    }
}
