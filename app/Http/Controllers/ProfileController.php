<?php

namespace App\Http\Controllers;

use App\Services\DodoVroumApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ProfileController extends Controller
{
    public function __construct(
        protected DodoVroumApiService $apiService
    ) {}

    /**
     * Afficher le profil de l'utilisateur connecté
     */
    public function show(): Response|RedirectResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        $userId = $user->getAuthIdentifier();
        
        try {
            // On tente de récupérer les données fraîches de l'API
            $userData = $this->apiService->getUser($userId);
            
            // Si l'API ne répond pas, on utilise la session
            if (!$userData) {
                $userData = session('api_user', []);
            }

            // Normalisation du rôle pour le frontend (Style Dribbble : Badge propre)
            $rawRole = $userData['role'] ?? $user->role ?? 'CLIENT';
            $displayRole = strtoupper($rawRole);

            $mappedUser = [
                'id' => $userData['id'] ?? $userData['_id'] ?? $userId,
                'email' => $userData['email'] ?? $user->email ?? '',
                'name' => trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? '')),
                'firstName' => $userData['firstName'] ?? $userData['prenom'] ?? '',
                'lastName' => $userData['lastName'] ?? $userData['nom'] ?? '',
                'phone' => $userData['phone'] ?? $userData['telephone'] ?? '',
                'role' => $displayRole,
                'address' => $userData['address'] ?? $userData['adresse'] ?? '',
                'city' => $userData['city'] ?? $userData['ville'] ?? '',
                'country' => $userData['country'] ?? $userData['pays'] ?? '',
                'avatar' => $userData['avatar'] ?? "https://ui-avatars.com/api/?name=" . urlencode($user->name ?? 'User'),
                'createdAt' => $userData['createdAt'] ?? $userData['created_at'] ?? null,
            ];

            return Inertia::render('Profile/Show', [
                'user' => $mappedUser,
                'auth_role' => $displayRole
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur Profile Show: ' . $e->getMessage());
            return Inertia::render('Profile/Show', [
                'user' => [
                    'id' => $userId,
                    'email' => $user->email ?? '',
                    'name' => $user->name ?? '',
                    'firstName' => '',
                    'lastName' => '',
                    'phone' => '',
                    'address' => '',
                    'city' => '',
                    'country' => '',
                    'role' => 'CLIENT',
                ],
                'error' => 'Erreur de synchronisation avec l\'API.',
            ]);
        }
    }

    /**
     * Mettre à jour le profil
     */
    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user) return redirect()->route('login');

        $validated = $request->validate([
            'firstName' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
        ]);

        try {
            // On envoie les deux formats pour être sûr que NestJS comprenne (selon votre version d'API)
            $apiData = [
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'prenom' => $request->firstName,
                'nom' => $request->lastName,
                'phone' => $request->phone,
                'telephone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'country' => $request->country,
            ];

            $updatedUser = $this->apiService->updateUser($user->getAuthIdentifier(), array_filter($apiData));

            if ($updatedUser) {
                // Mise à jour de la session locale pour refléter les changements sans recharger
                session(['api_user' => array_merge(session('api_user', []), $updatedUser)]);
                return redirect()->back()->with('success', 'Profil mis à jour !');
            }

            return back()->with('error', 'L\'API n\'a pas pu mettre à jour vos données.');

        } catch (\Exception $e) {
            Log::error('Update Profile Error: ' . $e->getMessage());
            return back()->with('error', 'Une erreur est survenue.');
        }
    }
}