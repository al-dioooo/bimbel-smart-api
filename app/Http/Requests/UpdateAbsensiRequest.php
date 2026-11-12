<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', 'in:h,s,i,a,H,S,I,A'],
            // REQ-049: the admin may correct the note as well as the status.
            'catatan' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_open' => ['sometimes', 'boolean'],
        ];
    }
}
