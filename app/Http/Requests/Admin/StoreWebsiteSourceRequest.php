<?php

namespace App\Http\Requests\Admin;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteSourceRequest extends FormRequest
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
            'crawl_depth' => ['required', 'integer', 'min:1', 'max:10'],
            'min_content_length' => ['required', 'integer', 'min:0'],
            'require_article_markup' => ['boolean'],
            'json_ld_types' => ['nullable', 'string', 'max:500'],
            'refresh_interval' => ['nullable', 'integer', 'min:15'],
        ];
    }
}
