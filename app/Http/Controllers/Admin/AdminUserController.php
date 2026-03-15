<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DodoVroumApiService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class AdminUserController extends Controller
{
    protected DodoVroumApiService $apiService;

    public function __construct(DodoVroumApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * Afficher la liste des utilisateurs avec filtres et pagination
     */
    public function index(Request $request): Response
    {
        try {
            $filters = [];
            if ($request->filled('search')) $filters['search'] = $request->search;
            if ($request->filled('role')) $filters['role'] = $request->role;

            $usersData = $this->apiService->getUsers($filters);
            $allUsers = [];

            if (is_array($usersData)) {
                if (isset($usersData['data']) && is_array($usersData['data'])) {
                    $allUsers = isset($usersData['data']['data']) ? $usersData['data']['data'] : $usersData['data'];
                } elseif (isset($usersData[0])) {
                    $allUsers = $usersData;
                }
            }

            // Mapping des données pour le frontend (Style Dribbble : propre et structuré)
            $mappedUsers = array_map(function ($user) {
                $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
                $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName) ?: ($user['email'] ?? 'N/A');

                // Récupérer le téléphone avec toutes les variantes possibles
                $phone = $user['phone'] 
                    ?? $user['telephone'] 
                    ?? $user['phoneNumber'] 
                    ?? $user['phone_number'] 
                    ?? $user['mobile'] 
                    ?? $user['tel'] 
                    ?? null;

                // Gérer isVerified avec toutes les variantes possibles
                $isVerified = $user['isVerified'] 
                    ?? $user['is_verified'] 
                    ?? $user['verified'] 
                    ?? false;
                
                // S'assurer que c'est un booléen
                if (!is_bool($isVerified)) {
                    $isVerified = filter_var($isVerified, FILTER_VALIDATE_BOOLEAN);
                }
                
                // 🛡️ FALLBACK : Si l'API retourne false, on applique une logique de fallback
                // - Les clients et propriétaires sont considérés comme vérifiés par défaut (ils ont créé un compte)
                // - Pour les propriétaires, on vérifie aussi s'ils ont des résidences/véhicules si disponibles dans les données
                if (!$isVerified) {
                    $role = strtolower($user['role'] ?? 'client');
                    $isProprietaire = in_array($role, ['proprietaire', 'owner']);
                    $isClient = in_array($role, ['client', 'user', 'customer']);
                    $isAdmin = in_array($role, ['admin', 'administrator']);
                    
                    // Les clients, propriétaires et admins sont vérifiés par défaut (ils ont créé un compte)
                    if ($isClient || $isProprietaire || $isAdmin) {
                        $isVerified = true;
                        \Log::debug('Utilisateur considéré comme vérifié via fallback', [
                            'user_id' => $user['id'] ?? null,
                            'role' => $role,
                            'reason' => $isClient ? 'client' : ($isProprietaire ? 'proprietaire' : 'admin'),
                        ]);
                    }
                }
                
                // Gérer isActive avec toutes les variantes possibles
                $isActive = $user['isActive'] 
                    ?? $user['is_active'] 
                    ?? $user['active'] 
                    ?? true;
                
                // S'assurer que c'est un booléen
                if (!is_bool($isActive)) {
                    $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
                }
                
                // Log pour déboguer (seulement pour le premier utilisateur)
                static $logged = false;
                if (!$logged && !empty($user['id'])) {
                    \Log::debug('Mapping utilisateur - Données brutes de l\'API', [
                        'user_id' => $user['id'],
                        'user_keys' => array_keys($user),
                        'isVerified_raw' => $user['isVerified'] ?? $user['is_verified'] ?? $user['verified'] ?? 'NON TROUVÉ',
                        'isVerified_final' => $isVerified,
                        'isActive_raw' => $user['isActive'] ?? $user['is_active'] ?? $user['active'] ?? 'NON TROUVÉ',
                        'isActive_final' => $isActive,
                    ]);
                    $logged = true;
                }

                return [
                    'id' => $user['id'] ?? $user['_id'] ?? null,
                    'name' => $fullName,
                    'email' => $user['email'] ?? 'N/A',
                    'phone' => $phone,
                    'role' => strtolower($user['role'] ?? 'client'),
                    'isVerified' => $isVerified,
                    'isActive' => $isActive,
                    'createdAt' => $user['createdAt'] ?? $user['created_at'] ?? null,
                ];
            }, $allUsers);

            // Pagination manuelle
            $perPage = $request->get('per_page', 15);
            $currentPage = $request->get('page', 1);
            $collection = new Collection($mappedUsers);
            $paginated = new LengthAwarePaginator(
                $collection->forPage($currentPage, $perPage),
                $collection->count(),
                $perPage,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return Inertia::render('Users/Index', [
                'users' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'total' => $paginated->total(),
                ],
                'filters' => $request->only(['search', 'role']),
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur Index Users: ' . $e->getMessage());
            return Inertia::render('Users/Index', ['users' => [], 'pagination' => [], 'error' => 'Impossible de charger les utilisateurs.']);
        }
    }

    /**
     * Afficher les détails d'un utilisateur (Profil complet)
     */
    public function show(string $id): Response|RedirectResponse
    {
        try {
            $user = $this->apiService->getUser($id);
            if (!$user) {
                Log::warning('Utilisateur non trouvé', ['id' => $id]);
                return redirect()->route('admin.users.index')->with('error', 'Utilisateur non trouvé');
            }

            $role = strtolower($user['role'] ?? 'user');
            $isProprietaire = in_array($role, ['proprietaire', 'owner']);

            // Construire le nom complet
            $firstName = $user['firstName'] ?? $user['prenom'] ?? '';
            $lastName = $user['lastName'] ?? $user['nom'] ?? $user['name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: ($user['email'] ?? 'N/A');

            // Récupérer le téléphone avec toutes les variantes possibles
            $phone = $user['phone'] 
                ?? $user['telephone'] 
                ?? $user['phoneNumber'] 
                ?? $user['phone_number'] 
                ?? $user['mobile'] 
                ?? $user['tel'] 
                ?? null;

            // Si le téléphone n'est pas trouvé directement, essayer de le récupérer depuis les relations
            if (!$phone && $isProprietaire) {
                // Essayer depuis les résidences
                if (isset($user['residences']) && is_array($user['residences']) && !empty($user['residences'])) {
                    $firstResidence = $user['residences'][0];
                    if (isset($firstResidence['proprietaire']['telephone'])) {
                        $phone = $firstResidence['proprietaire']['telephone'];
                    } elseif (isset($firstResidence['proprietaire']['phone'])) {
                        $phone = $firstResidence['proprietaire']['phone'];
                    }
                }
                
                // Si toujours pas trouvé, essayer depuis les véhicules
                if (!$phone && isset($user['vehicles']) && is_array($user['vehicles']) && !empty($user['vehicles'])) {
                    $firstVehicle = $user['vehicles'][0];
                    if (isset($firstVehicle['proprietaire']['telephone'])) {
                        $phone = $firstVehicle['proprietaire']['telephone'];
                    } elseif (isset($firstVehicle['proprietaire']['phone'])) {
                        $phone = $firstVehicle['proprietaire']['phone'];
                    }
                }
            }

            // Gérer isVerified avec toutes les variantes possibles
            $isVerified = $user['isVerified'] 
                ?? $user['is_verified'] 
                ?? $user['verified'] 
                ?? false;
            
            // S'assurer que c'est un booléen
            if (!is_bool($isVerified)) {
                $isVerified = filter_var($isVerified, FILTER_VALIDATE_BOOLEAN);
            }
            
            // 🛡️ FALLBACK : Si l'API retourne false, on applique une logique de fallback
            // - Les clients et propriétaires sont considérés comme vérifiés par défaut (ils ont créé un compte)
            // - Pour les propriétaires, on vérifie aussi s'ils ont des résidences/véhicules si disponibles dans les données
            if (!$isVerified) {
                $role = strtolower($user['role'] ?? 'client');
                $isClient = in_array($role, ['client', 'user', 'customer']);
                $isAdmin = in_array($role, ['admin', 'administrator']);
                
                // Les clients, propriétaires et admins sont vérifiés par défaut (ils ont créé un compte)
                if ($isClient || $isProprietaire || $isAdmin) {
                    $isVerified = true;
                    \Log::debug('Utilisateur considéré comme vérifié via fallback', [
                        'user_id' => $id,
                        'role' => $role,
                        'reason' => $isClient ? 'client' : ($isProprietaire ? 'proprietaire' : 'admin'),
                    ]);
                }
            }
            
            // Gérer isActive avec toutes les variantes possibles
            $isActive = $user['isActive'] 
                ?? $user['is_active'] 
                ?? $user['active'] 
                ?? true;
            
            // S'assurer que c'est un booléen
            if (!is_bool($isActive)) {
                $isActive = filter_var($isActive, FILTER_VALIDATE_BOOLEAN);
            }

            // Log pour déboguer
            Log::info('Récupération détails utilisateur', [
                'id' => $id,
                'user_keys' => array_keys($user),
                'phone_variants' => [
                    'phone' => $user['phone'] ?? 'not_set',
                    'telephone' => $user['telephone'] ?? 'not_set',
                    'phoneNumber' => $user['phoneNumber'] ?? 'not_set',
                ],
                'phone_final' => $phone,
                'isVerified_variants' => [
                    'isVerified' => $user['isVerified'] ?? 'not_set',
                    'is_verified' => $user['is_verified'] ?? 'not_set',
                    'verified' => $user['verified'] ?? 'not_set',
                ],
                'isVerified_final' => $isVerified,
                'isActive_variants' => [
                    'isActive' => $user['isActive'] ?? 'not_set',
                    'is_active' => $user['is_active'] ?? 'not_set',
                    'active' => $user['active'] ?? 'not_set',
                ],
                'isActive_final' => $isActive,
                'isProprietaire' => $isProprietaire,
                'has_residences' => !empty($user['residences'] ?? []),
                'has_vehicles' => !empty($user['vehicles'] ?? []),
            ]);

            $mappedUser = [
                'id' => $user['id'] ?? $user['_id'] ?? null,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'name' => $fullName,
                'email' => $user['email'] ?? 'N/A',
                'phone' => $phone,
                'role' => $role,
                'isVerified' => $isVerified,
                'isActive' => $isActive,
                'typeProprietaire' => $user['typeProprietaire'] ?? $user['type_proprietaire'] ?? null,
                'localisation' => $user['localisation'] ?? null,
                'address' => $user['address'] ?? $user['adresse'] ?? null,
                'city' => $user['city'] ?? $user['ville'] ?? null,
                'country' => $user['country'] ?? $user['pays'] ?? null,
                'residences' => $user['residences'] ?? [],
                'vehicles' => $user['vehicles'] ?? [],
                'identityVerification' => $this->resolveIdentityVerification($user),
                'createdAt' => $user['createdAt'] ?? null,
                'updatedAt' => $user['updatedAt'] ?? null,
            ];

            // Debug : décommenter pour inspecter la réponse brute de l'API NestJS (identityPhotoFront, etc.)
            // dd($user);

            return Inertia::render('Users/Show', [
                'user' => $mappedUser,
                'isProprietaire' => $isProprietaire,
            ]);
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des détails utilisateur', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('admin.users.index')->with('error', 'Erreur de récupération: ' . $e->getMessage());
        }
    }

    /**
     * Retourne l'objet vérification d'identité pour le frontend.
     * 1. Si NestJS renvoie déjà l'objet relationnel identityVerification, on le renvoie tel quel.
     * 2. Sinon, si les champs sont à la racine en camelCase (identityType, identityPhotoFront, etc.),
     *    on construit un objet synthétique avec snake_case et camelCase pour la Vue.
     */
    private function resolveIdentityVerification(array $user): ?array
    {
        // 1. Si NestJS renvoie déjà l'objet relationnel (historique)
        if (!empty($user['identityVerification']) && is_array($user['identityVerification'])) {
            return $user['identityVerification'];
        }
        if (!empty($user['identity_verification']) && is_array($user['identity_verification'])) {
            return $user['identity_verification'];
        }

        // 2. Détection des champs à la racine (camelCase de NestJS)
        if (!isset($user['identityType']) && !isset($user['identityPhotoFront'])) {
            return null;
        }

        $isVerified = $user['isVerified'] ?? $user['is_verified'] ?? false;
        if (!is_bool($isVerified)) {
            $isVerified = filter_var($isVerified, FILTER_VALIDATE_BOOLEAN);
        }
        $status = $isVerified ? 'verified' : 'pending';

        $userId = $user['id'] ?? $user['_id'] ?? null;
        $front = $user['identityPhotoFront'] ?? $user['identity_photo_front'] ?? null;
        $back = $user['identityPhotoBack'] ?? $user['identity_photo_back'] ?? null;
        $extra = $user['identityPhotoExtra'] ?? $user['identity_photo_extra'] ?? null;
        $type = $user['identityType'] ?? $user['identity_type'] ?? null;
        $number = $user['identityNumber'] ?? $user['identity_number'] ?? null;
        $submittedAt = $user['identitySubmittedAt'] ?? $user['identity_submitted_at'] ?? ($user['createdAt'] ?? null);

        return [
            'id' => 'synthetic_' . $userId,
            'user_id' => $userId,
            'status' => $status,
            'verificationStatus' => $status,
            'verification_status' => $status,
            'identity_type' => $type,
            'identityType' => $type,
            'identity_number' => $number,
            'identityNumber' => $number,
            'identity_photo_front' => $front,
            'identityPhotoFront' => $front,
            'identity_photo_back' => $back,
            'identityPhotoBack' => $back,
            'identity_photo_extra' => $extra,
            'identityPhotoExtra' => $extra,
            'submittedAt' => $submittedAt,
            'submitted_at' => $submittedAt,
        ];
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    /**
     * Enregistrer un utilisateur (CORRECTIF : MOT DE PASSE EN TEXTE CLAIR)
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'firstName' => 'required|string|max:255',
                'lastName' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string',
                'phone' => 'nullable|string|max:50',
                'identityType' => 'nullable|string|max:50',
                'identityNumber' => 'nullable|string|max:100',
                'identityPhotoFront' => 'nullable|string|max:2048',
                'identityPhotoBack' => 'nullable|string|max:2048',
                'identityPhotoExtra' => 'nullable|string|max:2048',
            ]);

            // Mapping vers les ENUM de l'API NestJS
            $apiRole = match (strtolower($validated['role'])) {
                'admin' => 'ADMIN',
                'proprietaire', 'owner' => 'PROPRIETAIRE',
                default => 'CLIENT',
            };

            $apiData = [
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'email' => $validated['email'],
                'password' => $validated['password'], // ENVOI SANS HASHAGE
                'role' => $apiRole,
            ];

            if (!empty($validated['phone'] ?? '')) {
                $apiData['phone'] = $validated['phone'];
            }

            // Envoi des données de vérification d'identité à l'API (si renseignées)
            if (!empty($validated['identityType'] ?? '')) {
                $apiData['identityType'] = $validated['identityType'];
            }
            if (!empty($validated['identityNumber'] ?? '')) {
                $apiData['identityNumber'] = $validated['identityNumber'];
            }
            if (!empty($validated['identityPhotoFront'] ?? '')) {
                $apiData['identityPhotoFront'] = $validated['identityPhotoFront'];
            }
            if (!empty($validated['identityPhotoBack'] ?? '')) {
                $apiData['identityPhotoBack'] = $validated['identityPhotoBack'];
            }
            if (!empty($validated['identityPhotoExtra'] ?? '')) {
                $apiData['identityPhotoExtra'] = $validated['identityPhotoExtra'];
            }

            $result = $this->apiService->createUser($apiData);

            if (!$result || (is_array($result) && empty($result))) {
                return back()->with('error', 'L\'API n\'a pas renvoyé de données. Vérifiez les logs ou que l\'endpoint POST /users existe.')->withInput();
            }

            return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé avec succès');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Store User Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', $e->getMessage() ?: 'Erreur API lors de la création.')->withInput();
        }
    }

    public function edit(string $id): Response|RedirectResponse
    {
        $user = $this->apiService->getUser($id);
        if (!$user) return redirect()->route('admin.users.index')->with('error', 'Utilisateur introuvable.');
        
        return Inertia::render('Users/Edit', ['user' => $user]);
    }

    /**
     * Mettre à jour (CORRECTIF : MOT DE PASSE EN TEXTE CLAIR SI FOURNI)
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'firstName' => 'nullable|string',
                'lastName' => 'nullable|string',
                'email' => 'required|email',
                'password' => 'nullable|string|min:8',
                'role' => 'nullable|string',
            ]);

            $apiData = array_filter($request->only(['firstName', 'lastName', 'email', 'phone']));
            
            if ($request->filled('password')) {
                $apiData['password'] = $request->password; // ENVOI SANS HASHAGE
            }
            if ($request->filled('role')) {
                $apiData['role'] = strtoupper($request->role);
            }

            $this->apiService->updateUser($id, $apiData);
            return redirect()->route('admin.users.show', $id)->with('success', 'Profil mis à jour.');
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de la mise à jour.');
        }
    }

    /**
     * Supprimer un utilisateur (CORRECTIF : MÉTHODE MANQUANTE AJOUTÉE)
     */
    public function destroy(string $id): RedirectResponse
    {
        try {
            // Logique de suppression via le service API
            $success = $this->apiService->deleteUser($id);

            if (!$success) {
                return redirect()->route('admin.users.index')->with('error', 'Impossible de supprimer cet utilisateur via l\'API.');
            }

            return redirect()->route('admin.users.index')->with('success', 'Utilisateur supprimé définitivement.');
        } catch (\Exception $e) {
            Log::error('Destroy User Error: ' . $e->getMessage());
            return redirect()->route('admin.users.index')->with('error', 'Erreur lors de la suppression.');
        }
    }

    /**
     * Approuver la vérification d'identité d'un utilisateur (propriétaire).
     * Appelle l'API NestJS PATCH /api/users/:id/identity-verification/approve.
     */
    public function approveIdentity(string $id): RedirectResponse
    {
        try {
            $this->apiService->approveIdentityVerification($id);
            return redirect()->route('admin.users.show', $id)->with('success', 'Vérification d\'identité approuvée.');
        } catch (\Exception $e) {
            Log::error('Approve identity error', ['id' => $id, 'message' => $e->getMessage()]);
            return redirect()->route('admin.users.show', $id)->with('error', $e->getMessage() ?: 'Impossible d\'approuver la vérification d\'identité.');
        }
    }

    /**
     * Rejeter la vérification d'identité d'un utilisateur (propriétaire).
     * Appelle l'API NestJS PATCH /api/users/:id/identity-verification/reject.
     */
    public function rejectIdentity(Request $request, string $id): RedirectResponse
    {
        $request->validate([
            'rejectionReason' => 'required|string|max:1000',
        ]);
        $reason = $request->input('rejectionReason');

        try {
            $this->apiService->rejectIdentityVerification($id, $reason);
            return redirect()->route('admin.users.show', $id)->with('success', 'Vérification d\'identité rejetée.');
        } catch (\Exception $e) {
            Log::error('Reject identity error', ['id' => $id, 'message' => $e->getMessage()]);
            return redirect()->route('admin.users.show', $id)->with('error', $e->getMessage() ?: 'Impossible de rejeter la vérification d\'identité.');
        }
    }
}