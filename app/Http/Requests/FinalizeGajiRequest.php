<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('sanctum')->check();
    }

    public function rules(): array
    {
        return [
            'bulan' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            // UC-10 3a: confirms finalizing although some sessions have no absensi.
            'lanjutkan' => ['sometimes', 'boolean'],
            // Optional: the total the admin previewed; refused if it changed.
            'total' => ['sometimes', 'nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'bulan.required' => 'Pilih periode gaji yang akan difinalisasi.',
            'bulan.regex' => 'Format bulan harus YYYY-MM, misalnya 2026-09.',
        ];
    }
}
