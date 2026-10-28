<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreWordPressImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'xml' => ['required', 'file', 'extensions:xml', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return [
            'xml.required' => 'Choose your WordPress XML export before starting the import.',
            'xml.uploaded' => 'PHP could not accept this file. Hosting upload limit: '.ini_get('upload_max_filesize').'; request limit: '.ini_get('post_max_size').'. Check these limits in cPanel MultiPHP INI Editor, or use the terminal import command.',
            'xml.max' => 'The WordPress XML export must be 8 MB or smaller.',
            'xml.extensions' => 'Choose the .xml file downloaded from WordPress Tools → Export.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('filament.admin.pages.wordpress-import');
    }
}
