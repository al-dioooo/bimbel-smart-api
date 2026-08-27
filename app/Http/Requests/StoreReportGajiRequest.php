<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportGajiRequest extends FormRequest
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
            'mentor_id' => ['required', 'integer', 'exists:mentor,id'],
            'bulan' => ['required', 'date'],
            'jumlah_kehadiran' => ['required', 'integer', 'min:0'],
            'total_gaji' => ['required', 'numeric', 'min:0'],
        ];
    }
}
