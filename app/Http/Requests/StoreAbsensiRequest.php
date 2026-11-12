<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAbsensiRequest extends FormRequest
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
            'jadwal_id' => ['required', 'integer', 'exists:jadwal,id'],
            'tanggal' => ['nullable', 'date'],

            // Bulk payload: one row per siswa in the class.
            'absensi' => ['required', 'array', 'min:1'],
            'absensi.*.siswa_id' => ['required', 'integer', 'distinct', 'exists:siswa,id'],
            'absensi.*.status' => ['required', 'string', 'in:h,s,i,a,H,S,I,A'],
        ];
    }

    public function messages(): array
    {
        return [
            // UC-08 2a: the same siswa twice in one payload is "input ganda".
            'absensi.*.siswa_id.distinct' => 'Input ganda: siswa yang sama dikirim lebih dari sekali.',
        ];
    }
}
