<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAturanGajiRequest extends FormRequest
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
            'kelas_id' => ['sometimes', 'required', 'integer', 'exists:kelas,id', Rule::unique('aturan_gaji', 'kelas_id')->ignore($this->route('aturan_gaji'))],
            'tarif' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'kelas_id.unique' => 'Kelas ini sudah memiliki aturan gaji. Ubah aturan yang sudah ada.',
        ];
    }
}
