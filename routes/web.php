<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminResidenceController;
use App\Http\Controllers\Admin\AdminVehicleController;
use App\Http\Controllers\Admin\AdminComboOfferController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\ImageUploadController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        
        // Utiliser isAdmin() et isOwner() pour une détection fiable du rôle
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user && method_exists($user, 'isOwner') && $user->isOwner()) {
            return redirect()->route('owner.dashboard');
        }
        
        // Fallback: vérifier directement le rôle si les méthodes n'existent pas
        $role = strtolower($user->role ?? 'owner');
        if ($role === 'admin' || $role === 'administrator') {
            return redirect()->route('admin.dashboard');
        }
        if (in_array($role, ['owner', 'proprietaire', 'propriétaire'])) {
            return redirect()->route('owner.dashboard');
        }
    }
    
    // Rediriger vers la page de login
    return redirect()->route('login');
});

// Routes d'authentification
Route::middleware('guest')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Auth\LoginController::class, 'show'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Auth\LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [\App\Http\Controllers\Auth\LoginController::class, 'destroy'])->name('logout')->middleware('auth');

// Route pour le profil (accessible à tous les utilisateurs authentifiés)
Route::middleware('auth')->group(function () {
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
        Route::get('/revenue', [\App\Http\Controllers\Admin\AdminRevenueController::class, 'index'])->name('revenue');
        Route::resource('residences', AdminResidenceController::class);
        Route::get('/residences/{id}/check-bookings', [AdminResidenceController::class, 'checkBookings'])->name('residences.check-bookings');
        Route::resource('vehicles', AdminVehicleController::class);
        Route::get('/vehicles/{id}/check-bookings', [AdminVehicleController::class, 'checkBookings'])->name('vehicles.check-bookings');
        // Route spécifique AVANT la route resource pour éviter les conflits
        Route::get('/combo-offers/owner-properties', [AdminComboOfferController::class, 'getOwnerProperties'])->name('combo-offers.owner-properties');
        Route::get('/combo-offers/{id}/check-bookings', [AdminComboOfferController::class, 'checkBookings'])->name('combo-offers.check-bookings');
        Route::resource('combo-offers', AdminComboOfferController::class);
        Route::resource('users', AdminUserController::class);
        // Routes pour la vérification d'identité (avant la route resource pour éviter les conflits)
        Route::patch('/users/{id}/identity/approve', [AdminUserController::class, 'approveIdentity'])->name('users.identity.approve');
        Route::patch('/users/{id}/identity/reject', [AdminUserController::class, 'rejectIdentity'])->name('users.identity.reject');
        // Routes pour l'approbation/rejet des réservations (avant la route resource pour éviter les conflits)
        Route::patch('/bookings/{id}/approve', [AdminBookingController::class, 'approve'])->name('bookings.approve');
        Route::patch('/bookings/{id}/reject', [AdminBookingController::class, 'reject'])->name('bookings.reject');
        // Routes pour les transitions de statut du cycle de vie des réservations
        Route::patch('/bookings/{id}/confirm-key-retrieval', [AdminBookingController::class, 'confirmKeyRetrieval'])->name('bookings.confirm-key-retrieval');
        Route::patch('/bookings/{id}/confirm-owner-key-handover', [AdminBookingController::class, 'confirmOwnerKeyHandover'])->name('bookings.confirm-owner-key-handover');
        Route::patch('/bookings/{id}/confirm-checkout', [AdminBookingController::class, 'confirmCheckOut'])->name('bookings.confirm-checkout');
        Route::patch('/bookings/{id}/mark-as-paid', [AdminBookingController::class, 'markAsPaid'])->name('bookings.mark-as-paid');
        Route::resource('bookings', AdminBookingController::class)->only(['index', 'show', 'destroy']);
        Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings');
        Route::post('/settings/test-connection', [AdminSettingsController::class, 'testConnection'])->name('settings.test-connection');
        Route::post('/settings/clear-cache', [AdminSettingsController::class, 'clearCache'])->name('settings.clear-cache');
        Route::post('/images/upload', [ImageUploadController::class, 'upload'])->name('images.upload');
        Route::delete('/images/delete', [ImageUploadController::class, 'delete'])->name('images.delete');
    });

// Routes pour les propriétaires
Route::middleware(['auth', 'owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/dashboard', \App\Http\Controllers\Owner\OwnerDashboardController::class)->name('dashboard');
        Route::get('/revenue', [\App\Http\Controllers\Owner\OwnerRevenueController::class, 'index'])->name('revenue');
        Route::resource('residences', \App\Http\Controllers\Owner\OwnerResidenceController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::resource('vehicles', \App\Http\Controllers\Owner\OwnerVehicleController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);
        // Routes pour l'approbation/rejet des réservations (avant la route resource pour éviter les conflits)
        Route::patch('/bookings/{id}/approve', [\App\Http\Controllers\Owner\OwnerBookingController::class, 'approve'])->name('bookings.approve');
        Route::patch('/bookings/{id}/reject', [\App\Http\Controllers\Owner\OwnerBookingController::class, 'reject'])->name('bookings.reject');
        Route::patch('/bookings/{id}/confirm-checkout', [\App\Http\Controllers\Owner\OwnerBookingController::class, 'confirmCheckOut'])->name('bookings.confirm-checkout');
        Route::resource('bookings', \App\Http\Controllers\Owner\OwnerBookingController::class)->only(['index', 'show']);
        // Avant le resource pour que "owner-properties" ne soit pas capturé comme {id}
        Route::get('/combo-offers/owner-properties', [\App\Http\Controllers\Owner\OwnerComboOfferController::class, 'getOwnerProperties'])->name('owner.combo-offers.owner-properties');
        Route::resource('combo-offers', \App\Http\Controllers\Owner\OwnerComboOfferController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);
        Route::post('/images/upload', [ImageUploadController::class, 'upload'])->name('images.upload');
        
        // Routes pour la gestion des dates bloquées
        Route::get('/residences/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerResidenceController::class, 'getBlockedDates'])->name('residences.blocked-dates');
        Route::post('/residences/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerResidenceController::class, 'blockDate'])->name('residences.block-date');
        Route::delete('/residences/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerResidenceController::class, 'unblockDate'])->name('residences.unblock-date');
        Route::patch('/residences/{id}/toggle-active', [\App\Http\Controllers\Owner\OwnerResidenceController::class, 'toggleActive'])->name('residences.toggle-active');
        
        Route::get('/vehicles/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerVehicleController::class, 'getBlockedDates'])->name('vehicles.blocked-dates');
        Route::post('/vehicles/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerVehicleController::class, 'blockDate'])->name('vehicles.block-date');
        Route::delete('/vehicles/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerVehicleController::class, 'unblockDate'])->name('vehicles.unblock-date');
        
        Route::get('/combo-offers/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerComboOfferController::class, 'getBlockedDates'])->name('combo-offers.blocked-dates');
        Route::post('/combo-offers/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerComboOfferController::class, 'blockDate'])->name('combo-offers.block-date');
        Route::delete('/combo-offers/{id}/blocked-dates', [\App\Http\Controllers\Owner\OwnerComboOfferController::class, 'unblockDate'])->name('combo-offers.unblock-date');
    });

if (app()->environment('local')) {
    // Route de test pour vérifier l'API
    Route::get('/dev/test-api', function () {
        $apiService = app(\App\Services\DodoVroumApiService::class);
        
        $result = [
            'test_residences' => $apiService->getResidences(),
            'test_vehicles' => $apiService->getVehicles(),
            'test_stats' => $apiService->getDashboardStats(),
        ];
        
        return response()->json($result, 200, [], JSON_PRETTY_PRINT);
    })->middleware('auth');
}
