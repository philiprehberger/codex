<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Filter allow-list for GET /api/v1/drill-down.
 *
 * Strict-keys enforcement mirrors ListProjectsRequest. Values are
 * slug/category shaped — no dynamic column name reaches the query
 * builder. `category` permits internal capitalisation (UserMgmt) so
 * its rule allows ASCII alpha + dash, no `alpha_dash` (which would
 * require all-lowercase).
 */
class DrillDownRequest extends FormRequest
{
    private const ALLOWED_KEYS = ['capability', 'industry', 'architecture', 'category'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'capability' => ['nullable', 'string', 'alpha_dash', 'max:120'],
            'industry' => ['nullable', 'string', 'alpha_dash', 'max:120'],
            'architecture' => ['nullable', 'string', 'alpha_dash', 'max:120'],
            'category' => ['nullable', 'string', 'regex:/^[A-Za-z]{1,60}$/'],
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
