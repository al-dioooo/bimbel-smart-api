<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChecksJadwalConflict;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateJadwalRequest extends FormRequest
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
            'kelas_id' => ['sometimes', 'exists:kelas,id'],

            'tanggal' => ['sometimes', 'date'],
            'waktu_mulai' => ['sometimes', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'date_format:H:i', 'required_with:waktu_mulai', 'after:waktu_mulai'],

            'materi' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Fields that are not sent keep their stored value for the conflict check. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $jadwal = $this->route('jadwal');
                $this->checkJadwalConflict($validator, [
                    'kelas_id' => $this->input('kelas_id', $jadwal->kelas_id),
                    'tanggal' => $this->input('tanggal', $jadwal->tanggal),
                    'waktu_mulai' => $this->input('waktu_mulai', $jadwal->waktu_mulai),
                    'waktu_selesai' => $this->input('waktu_selesai', $jadwal->waktu_selesai),
                ], $jadwal->id);
            },
        ];
    }
}
