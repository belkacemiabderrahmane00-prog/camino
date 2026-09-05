<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItineraryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration_minutes' => ['required', 'integer', 'min:30', 'max:720'],
            'budget_eur' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'free_only' => ['nullable', 'boolean'],
            'mode' => ['nullable', 'in:walk,bike'],
            'interests' => ['nullable', 'array', 'max:8'],
            'interests.*' => ['string', 'exists:categories,slug'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'start_label' => ['nullable', 'string', 'max:80'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:30'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'use_weather' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'duration_minutes' => 'temps disponible',
            'budget_eur' => 'budget',
            'interests' => 'centres d\'intérêt',
            'start_lat' => 'point de départ',
            'start_lng' => 'point de départ',
            'starts_at' => 'heure de départ',
        ];
    }
}
