<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportGajiRequest extends FormRequest
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
            'bulan' => ['sometimes', 'required', 'date'],
            'jumlah_kehadiran' => ['sometimes', 'required', 'integer', 'min:0'],
            'total_gaji' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}
