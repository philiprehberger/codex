<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Filter allow-list for GET /api/v1/projects.
 *
 * Phase 1 stance: 422 on any query key not in the allow-list. The
 * lone caller is the Next.js dashboard; typos surface immediately.
 * Phase 2 (public SDK story) revisits — industry-standard SDKs
 * (Stripe, GitHub, AWS) silently ignore unknown keys for
 * forward-compat. config('codex.api.strict_query_keys') is the
 * flip switch. Documented in docs/api-conventions.md.
 *
 * Filter values constrained to ULID/slug shape — no dynamic column
 * name ever reaches the query builder.
 */
class ListProjectsRequest extends FormRequest
{
    private const ALLOWED_KEYS = ['capability', 'industry', 'type', 'architecture', 'year', 'cursor', 'per_page'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'capability' => ['nullable', 'string', 'alpha_dash', 'max:120'],
            'industry' => ['nullable', 'string', 'alpha_dash', 'max:120'],
            'architecture' => ['nullable', 'string', 'alpha_dash', 'max:120'],
            'type' => ['nullable', 'string', 'in:demo,client,personal,open_source,package'],
            'year' => ['nullable', 'integer', 'between:2010,2099'],
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
