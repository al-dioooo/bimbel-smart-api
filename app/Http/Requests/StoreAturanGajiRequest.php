<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAturanGajiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // One tarif per kelas (REQ-055); a second rule doubled the salary rows.
            'kelas_id' => ['required', 'exists:kelas,id', 'unique:aturan_gaji,kelas_id'],
            'tarif' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'kelas_id.unique' => 'Kelas ini sudah memiliki aturan gaji. Ubah aturan yang sudah ada.',
        ];
    }
}
