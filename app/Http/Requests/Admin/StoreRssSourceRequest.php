<?php

namespace App\Http\Requests\Admin;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreRssSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'url' => ['required', 'url', 'max:2048', new SafeUrl()],
            'refresh_interval' => ['nullable', 'integer', 'min:5'],
        ];
    }
}
