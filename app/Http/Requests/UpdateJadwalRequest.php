<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJadwalRequest extends FormRequest
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
            'kelas_id' => ['sometimes', 'exists:kelas,id'],

            'tanggal' => ['sometimes', 'date'],
            'waktu_mulai' => ['sometimes', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'date_format:H:i', 'required_with:waktu_mulai', 'after:waktu_mulai'],

            'materi' => ['nullable', 'string', 'max:255']
        ];
    }
}
