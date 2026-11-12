<?php

namespace App\Http\Requests;

use App\Models\Kelas;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreKelasRequest extends FormRequest
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
            'nama' => ['required', 'string', 'max:255'],
            'tingkat' => ['required', 'string', 'max:255'],
            'mentor_id' => ['required', 'integer', 'exists:mentor,id'],

            // Relationships
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

                $nama = (string) $this->input('nama');
                $tingkat = (string) $this->input('tingkat');

                if (Kelas::duplicateExists($nama, $tingkat, null)) {
                    $validator->errors()->add('nama', "Kelas {$nama} tingkat {$tingkat} sudah terdaftar.");
                }
            },
        ];
    }
}
