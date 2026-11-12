<?php

namespace App\Http\Requests;

use App\Models\Kelas;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateKelasRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'sometimes', 'string', 'max:255'],
            'tingkat' => ['required', 'sometimes', 'string', 'max:255'],

            // Relationships
            'mentor_id' => ['required', 'sometimes', 'integer', 'exists:mentor,id'],
            'siswa' => ['sometimes', 'array'],
            'siswa.*' => ['integer', 'exists:siswa,id'],
        ];
    }

    /** REQ-028: refuse a second kelas with the same nama and tingkat. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['nama', 'tingkat'])) {
                    return;
                }

                $kelas = $this->route('kelas');

                $nama = (string) $this->input('nama', $kelas->nama);
                $tingkat = (string) $this->input('tingkat', $kelas->tingkat);

                if (Kelas::duplicateExists($nama, $tingkat, $kelas->id)) {
                    $validator->errors()->add('nama', "Kelas {$nama} tingkat {$tingkat} sudah terdaftar.");
                }
            },
        ];
    }
}
