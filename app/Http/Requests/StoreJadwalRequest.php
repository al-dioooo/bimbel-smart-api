<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChecksJadwalConflict;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJadwalRequest extends FormRequest
{
    use ChecksJadwalConflict;

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
            'kelas_id' => ['required', 'exists:kelas,id'],

            'tanggal' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],

            'materi' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->checkJadwalConflict($validator, $this->only(['kelas_id', 'tanggal', 'waktu_mulai', 'waktu_selesai'])),
        ];
    }
}
