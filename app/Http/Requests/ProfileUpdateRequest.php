<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'bio' => ['nullable', 'string', 'max:280'],
            'city' => ['nullable', 'string', 'max:80'],
            'mobility' => ['nullable', 'in:walk,bike'],
            'interests' => ['nullable', 'array', 'max:8'],
            'interests.*' => ['string', 'exists:categories,slug'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:6144'],
            'remove_avatar' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nom', 'email' => 'e-mail', 'avatar' => 'photo', 'interests' => 'centres d\'intérêt'];
    }
}
