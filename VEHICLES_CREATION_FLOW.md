# Flux de Création des Véhicules - Communication avec l'API

## 📋 Vue d'ensemble

Ce document explique comment les véhicules sont créés dans le dashboard et comment la communication avec l'API DodoVroum fonctionne.

---

## 🔄 Flux Complet (Frontend → Backend → API)

### 1️⃣ **Frontend : Formulaire Vue** (`resources/js/admin/Pages/Vehicles/Create.vue`)

#### Étape 1 : Initialisation du formulaire
```vue
const form = useForm({
  name: '',
  brand: '',
  model: '',
  year: new Date().getFullYear(),
  type: '',
  seats: 5,
  plateNumber: '',
  pricePerDay: null,
  color: '',
  transmission: '',
  fuel: '',
  mileage: 0,
  description: '',
  images: [],
  features: [],
  proprietaireId: '', // ⚠️ Standard métier (pas ownerId)
});
```

#### Étape 2 : Validation côté client
- Vérification des champs obligatoires
- Génération automatique du nom si vide : `brand + model + year`
- Normalisation du type de véhicule (mapping vers valeurs acceptées)

#### Étape 3 : Préparation des données
```javascript
// Normalisation du type
const typeMap = {
  'berline': 'berline',
  'suv': 'suv',
  '4x4': '4x4',
  'utilitaire': 'utilitaire',
  'moto': 'moto',
};

// Conversion plateNumber → licensePlate pour l'API
const dataForApi = {
  ...form.data(),
  type: typeMap[form.type.toLowerCase()].toUpperCase(), // API attend CAR, SUV, etc.
  licensePlate: form.plateNumber, // API attend licensePlate
};
```

#### Étape 4 : Envoi via Inertia.js
```javascript
form.post(route('admin.vehicles.store'), {
  onSuccess: () => { /* redirection */ },
  onError: (errors) => { /* gestion erreurs */ },
});
```

---

### 2️⃣ **Backend Laravel : Contrôleur** (`app/Http/Controllers/Admin/AdminVehicleController.php`)

#### Étape 1 : Validation avec FormRequest
```php
// StoreVehicleRequest valide :
- name, brand, model, year (requis)
- type (in: berline, suv, 4x4, utilitaire, moto)
- seats (min: 1, max: 50)
- plateNumber (requis)
- pricePerDay (min: 1)
- proprietaireId (requis)
```

#### Étape 2 : Normalisation dans `prepareForValidation()`
```php
// Normalise le type avant validation
$typeMap = [
  'berline' => 'berline',
  'suv' => 'suv',
  '4x4' => '4x4',
  'utilitaire' => 'utilitaire',
  'moto' => 'moto',
];
```

#### Étape 3 : Appel au Service
```php
$vehicle = $this->vehicleService->create($validated);
```

---

### 3️⃣ **Service Layer : VehicleService** (`app/Services/DodoVroumApi/VehicleService.php`)

#### Étape 1 : Troncature de la description
```php
if (mb_strlen($data['description']) > 500) {
    $data['description'] = mb_substr($data['description'], 0, 500);
}
```

#### Étape 2 : Mapping vers format API
```php
$dataForApi = VehicleMapper::toApi($data);
```

#### Étape 3 : Gestion de proprietaireId/ownerId selon le contexte
```php
// ⚠️ IMPORTANT : Gestion conditionnelle selon le rôle de l'utilisateur
$user = Auth::user();
$isAdmin = $user && (
    (method_exists($user, 'isAdmin') && $user->isAdmin()) ||
    ($user->role ?? 'owner') === 'admin'
);

if ($isAdmin && isset($data['proprietaireId']) && !empty($data['proprietaireId'])) {
    // Admin crée un véhicule pour un autre propriétaire : envoyer ownerId à l'API
    // L'API NestJS attend ownerId (pas proprietaireId) pour faire le connect
    $dataForApi['ownerId'] = $data['proprietaireId'];
} else {
    // Propriétaire normal ou admin sans proprietaireId : l'API détermine depuis le token
    unset($dataForApi['ownerId']);
    unset($dataForApi['proprietaireId']);
}
```

#### Étape 4 : Nettoyage des données
```php
// Filtrer les images/features vides
// Filtrer les valeurs null (sauf champs autorisés)
```

#### Étape 5 : Envoi à l'API
```php
$result = $this->post('vehicles', $dataForApi);
```

---

### 4️⃣ **Mapper : VehicleMapper** (`app/Services/DodoVroumApi/Mappers/VehicleMapper.php`)

#### Transformation des données

**Format Frontend (français)** → **Format API (anglais)**

```php
// Types : berline → CAR, suv → SUV, moto → MOTORCYCLE
$typeMap = [
    'berline' => 'CAR',
    'suv' => 'SUV',
    '4x4' => 'SUV',
    'utilitaire' => 'VAN',
    'moto' => 'MOTORCYCLE',
];

// Transmission : manuel → manual, automatique → automatic
$transmissionMap = [
    'manuel' => 'manual',
    'automatique' => 'automatic',
];

// Carburant : essence → petrol, diesel → diesel, electrique → electric
$fuelMap = [
    'essence' => 'petrol',
    'diesel' => 'diesel',
    'electrique' => 'electric',
    'hybride' => 'hybrid',
];

// Champs mappés (selon schéma Prisma NestJS)
[
    // ⚠️ PAS de 'title' - NestJS utilise brand + model séparés
    'brand' => $data['brand'],
    'model' => $data['model'],
    'year' => $data['year'],
    'type' => 'CAR', // berline → CAR (MAJUSCULES selon enum Prisma)
    'capacity' => $data['seats'], // ⚠️ NestJS attend 'capacity' (pas 'seats')
    'licensePlate' => $data['plateNumber'], // plateNumber → licensePlate
    'pricePerDay' => $data['pricePerDay'],
    'transmission' => 'manual', // manuel → manual
    'fuelType' => 'petrol', // ⚠️ NestJS attend 'fuelType' (pas 'fuel') : essence → petrol
    // ...
]
```

---

### 5️⃣ **BaseApiService : Communication HTTP** (`app/Services/DodoVroumApi/BaseApiService.php`)

#### Étape 1 : Authentification
```php
// Récupération du token (avec cache)
$token = $this->getAccessToken(); // Cache 1h
```

#### Étape 2 : Requête POST
```php
protected function post(string $endpoint, array $data = []): array
{
    $token = $this->getAccessToken();
    $url = "{$this->baseUrl}/{$endpoint}";
    
    $response = Http::timeout(30)
        ->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])
        ->post($url, $data);
    
    // Gestion des erreurs 401 (token expiré) avec retry
    if ($response->status() === 401) {
        $this->forgetTokenCache();
        $token = $this->getAccessToken();
        $response = Http::withHeaders([...])->post($url, $data);
    }
    
    return $response->json();
}
```

#### Étape 3 : Gestion des erreurs
- **400** : Données invalides (ex: proprietaireId envoyé)
- **401** : Token expiré → renouvellement automatique
- **403** : Accès refusé
- **500** : Erreur serveur API

---

## 🔑 Points Clés

### ✅ Ce qui est envoyé à l'API

```json
{
  // ⚠️ PAS de "title" - NestJS utilise brand + model séparés
  "brand": "BMW",
  "model": "X5",
  "year": 2023,
  "type": "CAR", // ⚠️ MAJUSCULES (enum Prisma)
  "capacity": 5, // ⚠️ NestJS attend "capacity" (pas "seats")
  "licensePlate": "AB 123 CD", // ⚠️ licensePlate (pas plateNumber)
  "pricePerDay": 25000,
  "transmission": "automatic", // ⚠️ anglais (manual/automatic)
  "fuelType": "petrol", // ⚠️ NestJS attend "fuelType" (pas "fuel") : petrol/diesel/electric/hybrid
  "color": "Noir",
  "mileage": 0,
  "description": "...",
  "images": [],
  "features": [],
  // ⚠️ ownerId : envoyé SEULEMENT si admin crée pour un autre propriétaire
  // Sinon, déterminé depuis le token
  "ownerId": "cmkr9ku2k0001q6bocja8kwa4" // ⚠️ Uniquement si admin + proprietaireId fourni
}
```

### ❌ Ce qui n'est PAS envoyé (sauf exceptions)

- `proprietaireId` / `ownerId` → 
  - ❌ Supprimé si utilisateur normal (déterminé depuis le token)
  - ✅ Envoyé comme `ownerId` si admin crée pour un autre propriétaire
- `title` → ❌ Pas de champ title, utiliser `brand` + `model` séparés
- `seats` → ❌ Utiliser `capacity` à la place
- `fuel` → ❌ Utiliser `fuelType` à la place
- Champs null (sauf autorisés : color, transmission, fuelType, mileage, description, images, features)
- Images/features vides

### 🔐 Authentification

- **Token** : Récupéré via `/auth/login` avec email/password admin
- **Cache** : Token mis en cache 1h (évite les appels répétés)
- **Renouvellement** : Automatique si 401 (token expiré)

### 📝 Normalisations

1. **Types** : `berline` → `CAR` (majuscules selon enum Prisma)
2. **Transmission** : `manuel` → `manual`, `automatique` → `automatic`
3. **Carburant** : `essence` → `petrol`, `diesel` → `diesel`, `electrique` → `electric`, `hybride` → `hybrid`
4. **Champs** : 
   - `plateNumber` → `licensePlate`
   - `seats` → `capacity` ⚠️
   - `fuel` → `fuelType` ⚠️
   - Supprimer `title` (utiliser `brand` + `model` séparés) ⚠️
5. **Description** : Tronquée à 500 caractères max
6. **Propriétaire** :
   - Admin avec `proprietaireId` → Envoyer `ownerId` à l'API
   - Sinon → Supprimé (déterminé depuis le token)

---

## 🐛 Gestion des Erreurs

### Erreurs courantes

1. **400 - proprietaireId should not exist** (si utilisateur normal)
   - Cause : Envoi de `proprietaireId` par un utilisateur non-admin
   - Solution : Supprimer avant envoi (fait automatiquement)
   - Note : Si admin, `ownerId` est envoyé correctement

2. **400 - type must be one of: CAR, SUV, MOTORCYCLE...**
   - Cause : Type en minuscules ou valeur incorrecte
   - Solution : Mapping vers majuscules dans `VehicleMapper::toApi()`

3. **400 - licensePlate should not be empty**
   - Cause : `plateNumber` non mappé vers `licensePlate`
   - Solution : Mapping dans `VehicleMapper::toApi()`

4. **400 - Unknown field 'title'** (si Prisma n'a pas ce champ)
   - Cause : Envoi de `title` alors que Prisma attend `brand` + `model`
   - Solution : Supprimer `title`, utiliser `brand` et `model` séparés

5. **400 - Unknown field 'seats'** (si Prisma attend 'capacity')
   - Cause : Envoi de `seats` au lieu de `capacity`
   - Solution : Mapping `seats` → `capacity` dans `VehicleMapper::toApi()`

6. **400 - Unknown field 'fuel'** (si Prisma attend 'fuelType')
   - Cause : Envoi de `fuel` au lieu de `fuelType`
   - Solution : Mapping `fuel` → `fuelType` dans `VehicleMapper::toApi()`

---

## 📊 Schéma de Communication

```
┌─────────────────┐
│  Vue Component  │
│  Create.vue     │
└────────┬────────┘
         │ form.post()
         ▼
┌─────────────────┐
│  Laravel Route  │
│  vehicles.store │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Controller     │
│  AdminVehicle   │
│  Controller     │
└────────┬────────┘
         │ validate()
         ▼
┌─────────────────┐
│  FormRequest    │
│  StoreVehicle   │
│  Request        │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  VehicleService │
│  create()       │
└────────┬────────┘
         │ VehicleMapper::toApi()
         ▼
┌─────────────────┐
│  VehicleMapper  │
│  toApi()        │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  BaseApiService │
│  post()         │
└────────┬────────┘
         │ HTTP POST + Bearer Token
         ▼
┌─────────────────┐
│  API DodoVroum  │
│  POST /vehicles │
└─────────────────┘
```

---

## 🔍 Logs et Debugging

Les logs sont disponibles dans `storage/logs/laravel.log` :

- `Création de véhicule - données envoyées à l'API` : Données finales envoyées
- `Véhicule créé avec succès` : Succès avec ID retourné
- `API DodoVroum POST error` : Erreurs API avec détails

---

## 🔄 Mise à Jour (Update) - Même Logique que Create

### Cohérence CRUD

La méthode `update()` dans `VehicleService` utilise **exactement la même logique** que `create()` :

1. ✅ **Même mapper** : `VehicleMapper::toApi()` (garantit les mêmes transformations)
2. ✅ **Même gestion de proprietaireId** : Admin peut changer le propriétaire via `ownerId`
3. ✅ **Même filtrage** : Description tronquée, images/features vides supprimées, valeurs null filtrées
4. ✅ **Mêmes champs** : `fuelType`, `capacity`, pas de `title`, types en MAJUSCULES

```php
// VehicleService::update() - Même structure que create()
$dataForApi = VehicleMapper::toApi($data); // ✅ Même mapper
// ... même gestion proprietaireId/ownerId ...
// ... même filtrage ...
$this->patch("vehicles/{$id}", $dataForApi);
```

**Avantage** : Garantit que les données envoyées lors de la création et de la mise à jour sont **100% cohérentes** avec le schéma Prisma NestJS.

### ⚠️ Vérification Type Casting

Les types sont **toujours en MAJUSCULES** grâce à `strtoupper()` dans le mapper :

```php
// VehicleMapper::toApi()
$type = strtolower(trim($type));
$type = $typeMap[$type] ?? strtoupper($type); // ✅ Force MAJUSCULES si non mappé
// Résultat : 'CAR', 'SUV', 'MOTORCYCLE', etc. (compatible avec enum Prisma)
```

---

## ✅ Checklist de Création

- [ ] Formulaire rempli avec tous les champs requis
- [ ] Nom généré automatiquement si vide
- [ ] Type normalisé (berline → CAR) et en MAJUSCULES
- [ ] Validation Laravel passée
- [ ] Mapping vers format API effectué (`fuelType`, `capacity`, pas de `title`)
- [ ] proprietaireId géré selon contexte (admin ou non)
- [ ] Token d'authentification valide
- [ ] Requête POST envoyée à `/api/vehicles`
- [ ] Réponse API traitée

## ✅ Checklist de Mise à Jour

- [ ] Même logique que création (même mapper, même filtrage)
- [ ] Types en MAJUSCULES vérifiés
- [ ] Champs mappés correctement (`fuelType`, `capacity`)
- [ ] proprietaireId géré selon contexte (admin ou non)
- [ ] Requête PATCH envoyée à `/api/vehicles/{id}`

