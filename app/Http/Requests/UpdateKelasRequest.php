<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'sometimes', 'string', 'max:255'],
            'tingkat' => ['required', 'sometimes', 'string', 'max:255'],

            // Relationships
            'mentor_id' => ['required', 'sometimes', 'integer', 'exists:mentor,id'],
            'siswa' => ['sometimes', 'array'],
            'siswa.*' => ['integer', 'exists:siswa,id']
        ];
    }
}
