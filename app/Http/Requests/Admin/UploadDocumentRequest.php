<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:txt,md,html,htm,pdf,doc,docx', 'max:10240'],
        ];
    }
}
