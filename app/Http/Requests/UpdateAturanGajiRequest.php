<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAturanGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kelas_id' => ['sometimes', 'required', 'integer', 'exists:kelas,id'],
            'tarif' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}
