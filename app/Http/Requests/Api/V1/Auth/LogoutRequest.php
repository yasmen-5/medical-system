<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LogoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', Rule::in(['mobile', 'web'])],
            'refreshToken' => ['sometimes', 'string', 'regex:/^[0-9a-fA-F-]{36}\.[a-f0-9]{64}$/'],
        ];
    }
}
