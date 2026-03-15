<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreResidenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        if (!$user) {
            return false;
        }
        
        // Vérifier si l'utilisateur est admin (support ApiUser et User)
        if (method_exists($user, 'isAdmin')) {
            return $user->isAdmin();
        }
        
        // Fallback: vérifier le rôle directement
        $role = strtolower($user->role ?? '');
        return in_array($role, ['admin', 'administrator', 'superadmin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'typeResidence' => 'required|string|in:villa,appartement,maison,studio',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'country' => 'nullable|string|max:255',
            'pricePerNight' => 'required|numeric|min:0',
            'bedrooms' => 'required|integer|min:0',
            'bathrooms' => 'required|integer|min:0',
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string|url',
            'amenities' => 'nullable|array',
            'amenities.*' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'isActive' => 'nullable|boolean',
            'isVerified' => 'nullable|boolean',
            'proprietaireId' => 'required|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Le titre de la résidence est obligatoire.',
            'typeResidence.required' => 'Le type de résidence est obligatoire.',
            'typeResidence.in' => 'Le type de résidence doit être : villa, appartement, maison ou studio.',
            'address.required' => 'L\'adresse est obligatoire.',
            'city.required' => 'La ville est obligatoire.',
            'pricePerNight.required' => 'Le prix par nuit est obligatoire.',
            'pricePerNight.numeric' => 'Le prix par nuit doit être un nombre.',
            'pricePerNight.min' => 'Le prix par nuit doit être positif.',
            'bedrooms.required' => 'Le nombre de chambres est obligatoire.',
            'bathrooms.required' => 'Le nombre de salles de bain est obligatoire.',
            'capacity.required' => 'La capacité est obligatoire.',
            'capacity.min' => 'La capacité doit être d\'au moins 1 personne.',
            'proprietaireId.required' => 'Le propriétaire est obligatoire.',
            'latitude.between' => 'La latitude doit être entre -90 et 90.',
            'longitude.between' => 'La longitude doit être entre -180 et 180.',
        ];
    }
}

