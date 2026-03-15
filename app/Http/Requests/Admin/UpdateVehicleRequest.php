<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVehicleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        
        \Illuminate\Support\Facades\Log::debug('🔵 UpdateVehicleRequest::authorize appelé', [
            'has_user' => $user !== null,
            'user_id' => $user ? ($user->getAuthIdentifier() ?? 'N/A') : 'N/A',
            'user_email' => $user ? ($user->email ?? 'N/A') : 'N/A',
        ]);
        
        if (!$user) {
            \Illuminate\Support\Facades\Log::warning('🔴 UpdateVehicleRequest::authorize - Aucun utilisateur', [
                'url' => $this->url(),
            ]);
            return false;
        }
        
        // Vérifier via la méthode isAdmin() si elle existe (pour ApiUser)
        if (method_exists($user, 'isAdmin')) {
            $isAdmin = $user->isAdmin();
            \Illuminate\Support\Facades\Log::debug('🔵 UpdateVehicleRequest::authorize - Résultat isAdmin()', [
                'isAdmin' => $isAdmin,
                'user_role' => $user->role ?? 'N/A',
            ]);
            return $isAdmin;
        }
        
        // Fallback : vérifier l'attribut role
        $role = strtolower($user->role ?? 'owner');
        $authorized = $role === 'admin' || $role === 'administrator';
        
        \Illuminate\Support\Facades\Log::debug('🔵 UpdateVehicleRequest::authorize - Résultat fallback', [
            'role' => $role,
            'authorized' => $authorized,
        ]);
        
        return $authorized;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        \Illuminate\Support\Facades\Log::debug('🔵 UpdateVehicleRequest::prepareForValidation appelé', [
            'all_data_keys' => array_keys($this->all()),
            'has_type' => $this->has('type'),
            'type_value' => $this->input('type'),
            'transmission_value' => $this->input('transmission'),
            'features_value' => $this->input('features'),
            'features_type' => gettype($this->input('features')),
        ]);
        
        $dataToMerge = [];
        
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
            
            \Illuminate\Support\Facades\Log::debug('🔵 Normalisation type véhicule (update)', [
                'original' => $this->input('type'),
                'normalized' => $normalizedType,
            ]);
            
            $dataToMerge['type'] = $normalizedType;
        }
        
        // Normaliser la transmission (convertir "Manuelle" en "manuel", "Automatique" en "automatique")
        if ($this->has('transmission')) {
            $transmission = $this->input('transmission');
            $transmissionMap = [
                'Manuelle' => 'manuel',
                'manuelle' => 'manuel',
                'Automatique' => 'automatique',
                'automatique' => 'automatique',
                'Manual' => 'manuel',
                'manual' => 'manuel',
                'Automatic' => 'automatique',
                'automatic' => 'automatique',
            ];
            
            $normalizedTransmission = $transmissionMap[$transmission] ?? $transmission;
            
            \Illuminate\Support\Facades\Log::debug('🔵 Normalisation transmission (update)', [
                'original' => $transmission,
                'normalized' => $normalizedTransmission,
            ]);
            
            $dataToMerge['transmission'] = $normalizedTransmission;
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
            
            \Illuminate\Support\Facades\Log::debug('🔵 Normalisation fuel (update)', [
                'original' => $this->input('fuel'),
                'normalized' => $normalizedFuel,
            ]);
            
            $dataToMerge['fuel'] = $normalizedFuel;
        }
        
        // Normaliser features (convertir la chaîne JSON "[]" en tableau vide)
        if ($this->has('features')) {
            $features = $this->input('features');
            
            // Si c'est une chaîne JSON, la décoder
            if (is_string($features)) {
                if (empty(trim($features)) || $features === '[]' || $features === 'null') {
                    $features = [];
                } else {
                    $decoded = json_decode($features, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $features = $decoded;
                    } else {
                        // Si ce n'est pas du JSON valide, créer un tableau avec la valeur
                        $features = [$features];
                    }
                }
            } elseif (!is_array($features)) {
                // Si ce n'est ni une chaîne ni un tableau, créer un tableau vide
                $features = [];
            }
            
            \Illuminate\Support\Facades\Log::debug('🔵 Normalisation features (update)', [
                'original' => $this->input('features'),
                'original_type' => gettype($this->input('features')),
                'normalized' => $features,
                'normalized_type' => gettype($features),
            ]);
            
            $dataToMerge['features'] = $features;
        }
        
        if (!empty($dataToMerge)) {
            $this->merge($dataToMerge);
        }
    }
    
    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom du véhicule',
            'brand' => 'marque',
            'model' => 'modèle',
            'year' => 'année',
            'type' => 'type de véhicule',
            'seats' => 'nombre de places',
            'plateNumber' => 'numéro de plaque',
            'pricePerDay' => 'prix par jour',
        ];
    }
    
    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                \Illuminate\Support\Facades\Log::warning('🔴 UpdateVehicleRequest - Erreurs de validation', [
                    'errors' => $validator->errors()->toArray(),
                    'data' => $this->all(),
                ]);
            } else {
                \Illuminate\Support\Facades\Log::debug('✅ UpdateVehicleRequest - Validation réussie', [
                    'validated_keys' => array_keys($this->validated()),
                ]);
            }
        });
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
            'pricePerDay' => 'required|numeric|min:0',
            'color' => 'nullable|string|max:50',
            'transmission' => 'nullable|string|in:manuel,automatique,automatic',
            'fuel' => 'nullable|string|in:essence,diesel,electrique,hybride',
            'mileage' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:500',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string|url',
            'features' => 'nullable|array',
            'features.*' => 'nullable|string|max:255',
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
        ];
    }
}

