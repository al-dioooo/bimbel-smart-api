<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbsensiRequest extends FormRequest
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
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'tanggal' => ['nullable', 'date'],

            // Bulk payload: one row per siswa in the class.
            'absensi' => ['required', 'array', 'min:1'],
            'absensi.*.siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'absensi.*.status' => ['required', 'string', 'in:h,s,i,a,H,S,I,A'],
        ];
    }
}
