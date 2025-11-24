<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePengajuanJadwalRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'jadwal_id' => ['required', 'exists:jadwal,id'],
            
            'tanggal_sebelum' => ['required', 'date'],
            'tanggal_sesudah' => ['required', 'date', 'after_or_equal:tanggal_sebelum'],
            'waktu_mulai_sebelum' => ['required', 'date_format:H:i:s'],
            'waktu_mulai_sesudah' => ['required', 'date_format:H:i:s'],
            'waktu_selesai_sebelum' => ['required', 'date_format:H:i:s'],
            'waktu_selesai_sesudah' => ['required', 'date_format:H:i:s'],

            'alasan' => ['nullable', 'string'],

            'status' => ['nullable', 'in:pending,diterima,ditolak']
        ];
    }
}
