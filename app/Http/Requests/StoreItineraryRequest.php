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
            'mode' => ['nullable', 'in:walk,bike,transit'],
            'interests' => ['nullable', 'array', 'max:8'],
            'interests.*' => ['string', 'exists:categories,slug'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:40'],
            'start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'start_label' => ['nullable', 'string', 'max:120'],
            'end_mode' => ['nullable', 'in:open,loop,point'],
            'end_lat' => ['nullable', 'required_if:end_mode,point', 'numeric', 'between:-90,90'],
            'end_lng' => ['nullable', 'required_if:end_mode,point', 'numeric', 'between:-180,180'],
            'end_label' => ['nullable', 'string', 'max:120'],
            'radius_km' => ['nullable', 'integer', 'min:1', 'max:30'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+60 days'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'use_weather' => ['nullable', 'boolean'],
            'with_lunch' => ['nullable', 'boolean'],
            'surprise' => ['nullable', 'boolean'],
            'accessible' => ['nullable', 'boolean'],
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
            'end_lat' => 'point d\'arrivée',
            'end_lng' => 'point d\'arrivée',
            'date' => 'date',
            'starts_at' => 'heure de départ',
        ];
    }

    public function messages(): array
    {
        return [
            'end_lat.required_if' => 'Indique une adresse d\'arrivée ou choisis « retour au départ ».',
            'end_lng.required_if' => 'Indique une adresse d\'arrivée ou choisis « retour au départ ».',
            'date.after_or_equal' => 'La date est déjà passée.',
            'date.before' => 'On ne planifie pas au-delà de deux mois (les horaires changent).',
        ];
    }
}
