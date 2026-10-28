<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'phone' => ['nullable', 'string', 'regex:/^[+0-9(). \-]{7,40}$/'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'source' => ['required', Rule::in(['home', 'contact'])],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return ['website.max' => 'Your inquiry could not be accepted. Please leave the website field empty.'];
    }

    protected function getRedirectUrl(): string
    {
        return route('contact').'#contactForm';
    }
}
