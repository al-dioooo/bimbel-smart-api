<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMentorRequest extends FormRequest
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
            // User fields
            'name' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:users,username,'.$this->mentor->user_id,
            'email' => 'sometimes|required|email|max:255|unique:users,email,'.$this->mentor->user_id,
            'password' => 'nullable|string|min:8',

            // Mentor fields
            // REQ-022: "nomor telepon unik".
            'kontak' => 'sometimes|nullable|string|max:100|unique:mentor,kontak,'.$this->mentor->id,
            'tempat_tanggal_lahir' => 'sometimes|nullable|string',
            'nik' => 'sometimes|nullable|string|max:16',
            'npwp' => 'sometimes|nullable|string|max:25',
            'alamat' => 'sometimes|nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'kontak.unique' => 'Nomor telepon sudah dipakai mentor lain.',
        ];
    }
}
