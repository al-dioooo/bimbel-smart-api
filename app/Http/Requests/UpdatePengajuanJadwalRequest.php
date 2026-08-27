<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePengajuanJadwalRequest extends FormRequest
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
            'status' => ['sometimes', 'required', 'string', 'in:pending,diterima,ditolak'],

            'tanggal_sesudah' => ['sometimes', 'required', 'date'],
            'waktu_mulai_sesudah' => ['sometimes', 'required', 'date_format:H:i,H:i:s'],
            'waktu_selesai_sesudah' => ['sometimes', 'required', 'date_format:H:i,H:i:s', 'after:waktu_mulai_sesudah'],

            'alasan' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
