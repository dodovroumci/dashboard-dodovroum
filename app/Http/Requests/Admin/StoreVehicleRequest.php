<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreVehicleRequest extends FormRequest
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
        
        // Fallback pour les autres cas
        return ($user->role ?? 'owner') === 'admin' || ($user->is_admin ?? false);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Normaliser le type de véhicule en minuscules
        if ($this->has('type')) {
            $type = strtolower(trim($this->input('type')));
            
            // Mapper les variantes possibles vers les valeurs acceptées
            // Aligné avec l'API NestJS
            $typeMap = [
                'berline' => 'berline',
                'suv' => 'suv',
                '4x4' => '4x4',
                '4 x 4' => '4x4',
                'utilitaire' => 'utilitaire',
                'moto' => 'moto',
                'motorcycle' => 'moto',
                'citadine' => 'citadine', // ✅ Accepté directement
                'luxe' => 'luxe', // ✅ Accepté directement
                'sedan' => 'berline',
                'car' => 'berline',
            ];
            
            $normalizedType = $typeMap[$type] ?? $type;
            
            \Illuminate\Support\Facades\Log::debug('Normalisation type véhicule', [
                'original' => $this->input('type'),
                'normalized' => $normalizedType,
            ]);
            
            $this->merge([
                'type' => $normalizedType,
            ]);
        }
        
        // Normaliser le carburant (fuel)
        if ($this->has('fuel')) {
            $fuel = strtolower(trim($this->input('fuel')));
            
            // Mapper les variantes possibles vers les valeurs acceptées
            $fuelMap = [
                'petrol' => 'essence', // petrol (anglais) -> essence (français)
                'gasoline' => 'essence', // gasoline (anglais) -> essence (français)
                'essence' => 'essence',
                'diesel' => 'diesel',
                'electric' => 'electrique',
                'electrique' => 'electrique',
                'hybrid' => 'hybride',
                'hybride' => 'hybride',
            ];
            
            $normalizedFuel = $fuelMap[$fuel] ?? $fuel;
            
            \Illuminate\Support\Facades\Log::debug('Normalisation fuel', [
                'original' => $this->input('fuel'),
                'normalized' => $normalizedFuel,
            ]);
            
            $this->merge([
                'fuel' => $normalizedFuel,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'type' => 'required|string|in:berline,suv,4x4,utilitaire,moto,citadine,luxe',
            'seats' => 'required|integer|min:1|max:50',
            'plateNumber' => 'required|string|max:20',
            'pricePerDay' => 'required|numeric|min:1',
            'color' => 'nullable|string|max:50',
            'transmission' => 'nullable|string|in:manuel,automatique,manuel,automatic',
            'fuel' => 'nullable|string|in:essence,diesel,electrique,hybride',
            'mileage' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string|url',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
            'proprietaireId' => 'required|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du véhicule est obligatoire.',
            'brand.required' => 'La marque est obligatoire.',
            'model.required' => 'Le modèle est obligatoire.',
            'year.required' => 'L\'année est obligatoire.',
            'year.integer' => 'L\'année doit être un nombre.',
            'year.min' => 'L\'année doit être supérieure à 1900.',
            'year.max' => 'L\'année ne peut pas être dans le futur.',
            'type.required' => 'Le type de véhicule est obligatoire.',
            'type.in' => 'Le type de véhicule doit être : berline, suv, 4x4, utilitaire, moto, citadine ou luxe.',
            'seats.required' => 'Le nombre de places est obligatoire.',
            'seats.integer' => 'Le nombre de places doit être un nombre.',
            'seats.min' => 'Le nombre de places doit être d\'au moins 1.',
            'seats.max' => 'Le nombre de places ne peut pas dépasser 50.',
            'plateNumber.required' => 'Le numéro de plaque est obligatoire.',
            'pricePerDay.required' => 'Le prix par jour est obligatoire.',
            'pricePerDay.numeric' => 'Le prix par jour doit être un nombre.',
            'pricePerDay.min' => 'Le prix par jour doit être positif.',
            'proprietaireId.required' => 'Le propriétaire est obligatoire.',
        ];
    }
}

