<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\RideController as AdminRideController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Chauffeur\RideController as ChauffeurRideController;
use App\Http\Controllers\Chauffeur\ProfileController as ChauffeurProfileController;
use App\Http\Controllers\Chauffeur\EarningsController as ChauffeurEarningsController;
use App\Http\Controllers\Client\RideController as ClientRideController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Driver\AvailabilityController;
use App\Http\Controllers\Driver\LocationController;
use App\Http\Controllers\Vehicle\VehicleController;
use App\Http\Controllers\Payment\StripeController;
use App\Http\Controllers\Payment\DepositController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// Routes de contact (publiques)
Route::prefix('contact')->group(function () {
    Route::post('/', [App\Http\Controllers\Api\ContactController::class, 'sendMessage']);
});

// Routes de support (publiques)
Route::prefix('support')->group(function () {
    Route::post('/ticket', [App\Http\Controllers\Api\SupportController::class, 'submitTicket']);
});

Route::prefix('invest')->group(function () {
    Route::post('/', [App\Http\Controllers\Api\InvestController::class, 'submit']);
    Route::get('/{id}/status', [App\Http\Controllers\Api\InvestController::class, 'checkStatus']);
});




// Routes publiques (sans authentification)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register/client', [RegisterController::class, 'registerClient']);
    Route::post('/register/driver', [RegisterController::class, 'registerDriver']); // Nouvelle route d'inscription chauffeur publique
});

// ============================================
// ROUTES PUBLIQUES (SANS AUTHENTIFICATION)
// ============================================
Route::prefix('advertisements')->group(function () {
    Route::get('/active', [\App\Http\Controllers\Admin\AdvertisementController::class, 'getActive']); // Récupérer les annonces actives pour le frontend
});

// Paramètres publics (pour afficher les tarifs aux clients)
Route::prefix('settings')->group(function () {
    Route::get('/', [SettingController::class, 'index']); // Récupérer tous les paramètres (tarifs, général, etc.)
});

// Véhicules disponibles (public - pour recherche de véhicules)
Route::prefix('vehicles')->group(function () {
    Route::get('/available', [VehicleController::class, 'getAvailableByType']); // GET /api/vehicles/available?car_type=berline
    Route::get('/types', [VehicleController::class, 'getAvailableTypes']); // GET /api/vehicles/types
    Route::get('/{vehicleId}', [VehicleController::class, 'show']); // GET /api/vehicles/{vehicleId}
});

// Routes protégées par authentification Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // ============================================
    // ROUTES DRIVER (Récupérer les chauffeurs disponibles)
    // ============================================
    Route::prefix('driver')->group(function () {
        Route::get('/available', [AvailabilityController::class, 'getAvailable']);

        // 🔴 NOUVEAU: Routes de géolocalisation
        Route::post('/location', [LocationController::class, 'updateLocation']); // POST /api/driver/location - Mettre à jour position
        Route::get('/location', [LocationController::class, 'getLocation']); // GET /api/driver/location - Récupérer sa position
        Route::post('/location/disable', [LocationController::class, 'disableLocation']); // POST /api/driver/location/disable - Désactiver suivi
    });

    // Routes publiques pour voir les positions des chauffeurs
    Route::prefix('drivers')->group(function () {
        Route::get('/locations', [LocationController::class, 'getAllDriversLocations']); // GET /api/drivers/locations - Voir toutes les positions (public)
    });

    // ============================================
    // ROUTES CLIENT
    // ============================================
    Route::prefix('client')->middleware('role:client')->group(function () {

        // Profile Client
        Route::get('/profile', [ClientProfileController::class, 'show']);
        Route::put('/profile', [ClientProfileController::class, 'update']);
        Route::post('/profile/picture', [ClientProfileController::class, 'uploadProfilePicture']); // Uploader photo de profil
        Route::get('/stats', [ClientProfileController::class, 'stats']); // 🔴 NOUVEAU: Récupérer les stats du client

        // Gestion des courses Client
        Route::prefix('rides')->group(function () {
            Route::post('/', [ClientRideController::class, 'storeSimple']); // Créer une course (format simplifié)
            Route::get('/', [ClientRideController::class, 'index']); // Liste des courses
            Route::get('/{ride}', [ClientRideController::class, 'show']); // Détails d'une course
            Route::post('/{ride}/cancel', [ClientRideController::class, 'cancel']); // Annuler une course
            Route::get('/{ride}/track', [ClientRideController::class, 'track']); // Suivre une course

            // 🔴 NOUVEAU: Routes pour le paiement du solde (90%)
            Route::post('/{ride}/balance-payment', [ClientRideController::class, 'payBalance']); // Initier le paiement du solde
            Route::post('/{ride}/balance-payment-confirm', [ClientRideController::class, 'confirmBalancePayment']); // Confirmer le paiement du solde
            Route::get('/{ride}/balance-payment-status', [ClientRideController::class, 'getBalancePaymentStatus']); // Récupérer le statut du solde
        });
    });

    // ============================================
    // ROUTES CHAUFFEUR
    // ============================================
    Route::prefix('chauffeur')->middleware('role:chauffeur')->group(function () {

        // Profile Chauffeur
        Route::get('/profile', [ChauffeurProfileController::class, 'show']);
        Route::put('/profile', [ChauffeurProfileController::class, 'update']);
        Route::post('/profile/picture', [ChauffeurProfileController::class, 'uploadProfilePicture']); // Uploader photo de profil
        Route::put('/availability', [ChauffeurProfileController::class, 'updateAvailability']);

        // Gestion des courses Chauffeur
        Route::prefix('rides')->group(function () {
            Route::get('/available', [ChauffeurRideController::class, 'available']); // Courses disponibles
            Route::get('/', [ChauffeurRideController::class, 'index']); // Historique des courses
            Route::get('/{ride}', [ChauffeurRideController::class, 'show']); // Détails d'une course
            Route::post('/{ride}/accept', [ChauffeurRideController::class, 'accept']); // Accepter une course
            Route::post('/{ride}/refuse', [ChauffeurRideController::class, 'refuse']); // Refuser une course
            Route::post('/{ride}/start', [ChauffeurRideController::class, 'start']); // Démarrer une course
            Route::post('/{ride}/complete', [ChauffeurRideController::class, 'complete']); // Terminer une course
            Route::post('/{ride}/cancel', [ChauffeurRideController::class, 'cancel']); // Annuler une course
        });

        // Statistiques Chauffeur
        Route::get('/stats', [ChauffeurProfileController::class, 'stats']);

        // 💳 NOUVEAU: Infos de paiement
        Route::get('/payment-info', [ChauffeurProfileController::class, 'getPaymentInfo']);
        Route::get('/payment-history', [ChauffeurProfileController::class, 'paymentHistory']); // 💳 Historique des paiements
        Route::get('/payment-stats', [ChauffeurProfileController::class, 'paymentStats']); // 💳 Stats de paiement

        // Gains Chauffeur
        Route::get('/earnings', [ChauffeurEarningsController::class, 'earnings']);
    });

    // ============================================
    // ROUTES ADMIN
    // ============================================
    Route::prefix('admin')->middleware('role:admin')->group(function () {

        // Gestion des utilisateurs
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminUserController::class, 'index']); // Liste tous les utilisateurs
            Route::get('/clients', [AdminUserController::class, 'clients']); // Liste des clients
            Route::get('/drivers', [AdminUserController::class, 'drivers']); // Liste des chauffeurs
            Route::post('/drivers', [RegisterController::class, 'createDriverByAdmin']); // Créer un chauffeur (par admin)
            Route::get('/{user}', [AdminUserController::class, 'show']); // Détails d'un utilisateur
            Route::put('/{user}', [AdminUserController::class, 'update']); // Modifier un utilisateur
            Route::put('/{user}/status', [AdminUserController::class, 'updateStatus']); // Changer statut
            Route::put('/{user}/role', [AdminUserController::class, 'updateRole']); // Changer rôle
            Route::delete('/{user}', [AdminUserController::class, 'destroy']); // Supprimer utilisateur
            Route::get('/{user}/rides', [AdminUserController::class, 'userRides']); // Historique courses utilisateur
            Route::post('/{user}/pay', [AdminUserController::class, 'payDriver']); // 💳 Payer un chauffeur (ancienne méthode)
            Route::get('/{user}/payment-initiate', [AdminUserController::class, 'initiateDriverPayment']); // 💳 Initier paiement
            Route::post('/{user}/payment-confirm', [AdminUserController::class, 'confirmDriverPayment']); // 💳 Confirmer paiement
        });

        Route::prefix('investments')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\InvestController::class, 'index']);
        Route::get('/stats', [App\Http\Controllers\Admin\InvestController::class, 'stats']);
        Route::get('/export', [App\Http\Controllers\Admin\InvestController::class, 'export']);
        Route::get('/{id}', [App\Http\Controllers\Admin\InvestController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Admin\InvestController::class, 'update']);
        Route::post('/{id}/contacted', [App\Http\Controllers\Admin\InvestController::class, 'markAsContacted']);
        Route::post('/{id}/archive', [App\Http\Controllers\Admin\InvestController::class, 'archive']);
        Route::delete('/{id}', [App\Http\Controllers\Admin\InvestController::class, 'destroy']);
    });



        // Gestion des courses
        Route::prefix('rides')->group(function () {
            Route::get('/', [AdminRideController::class, 'index']); // Liste toutes les courses
            Route::get('/stats', [AdminRideController::class, 'stats']); // Statistiques courses
            Route::get('/{ride}', [AdminRideController::class, 'show']); // Détails d'une course
            Route::put('/{ride}', [AdminRideController::class, 'update']); // Modifier une course
            Route::post('/{ride}/assign', [AdminRideController::class, 'assign']); // 🔴 NOUVEAU: Assigner un chauffeur
        });

        // 🔴 NOUVEAU: Gestion des annonces publicitaires
        Route::prefix('advertisements')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\AdvertisementController::class, 'index']); // Liste toutes les annonces
            Route::post('/', [\App\Http\Controllers\Admin\AdvertisementController::class, 'store']); // Créer une annonce
            Route::get('/{advertisement}', [\App\Http\Controllers\Admin\AdvertisementController::class, 'show']); // Détails d'une annonce
            Route::put('/{advertisement}', [\App\Http\Controllers\Admin\AdvertisementController::class, 'update']); // Modifier une annonce
            Route::delete('/{advertisement}', [\App\Http\Controllers\Admin\AdvertisementController::class, 'destroy']); // Supprimer une annonce
            Route::post('/{advertisement}/toggle', [\App\Http\Controllers\Admin\AdvertisementController::class, 'toggleActive']); // Activer/Désactiver
            Route::post('/update-order', [\App\Http\Controllers\Admin\AdvertisementController::class, 'updateOrder']); // Mettre à jour l'ordre
        });

        // Statistiques & Tableaux de bord
        Route::prefix('stats')->group(function () {
            Route::get('/overview', [StatsController::class, 'overview']); // Vue d'ensemble
            Route::get('/revenue', [StatsController::class, 'revenue']); // Statistiques revenus
            Route::get('/rides', [StatsController::class, 'rides']); // Statistiques courses
            Route::get('/drivers', [StatsController::class, 'drivers']); // Statistiques chauffeurs
        });

        // Paramètres & Configuration
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingController::class, 'index']); // Tous les paramètres
            Route::get('/{group}', [SettingController::class, 'byGroup']); // Paramètres par groupe
            Route::post('/', [SettingController::class, 'store']); // Créer un paramètre
            Route::put('/{setting}', [SettingController::class, 'update']); // Modifier un paramètre
            Route::put('/tarifs/update', [SettingController::class, 'updateTarifs']); // Mettre à jour les tarifs
            Route::put('/general/update', [SettingController::class, 'updateGeneral']); // Mettre à jour les paramètres généraux
            Route::put('/payments/update', [SettingController::class, 'updatePayments']); // Mettre à jour les paramètres de paiement
            Route::put('/notifications/update', [SettingController::class, 'updateNotifications']); // Mettre à jour les paramètres de notifications
        });

        // Gestion des inscriptions chauffeurs en attente
        Route::prefix('pending-drivers')->group(function () {
            Route::get('/', [AdminUserController::class, 'pendingDrivers']); // Liste des chauffeurs en attente
            Route::post('/{user}/approve', [AdminUserController::class, 'approveDriver']); // Approuver un chauffeur
            Route::post('/{user}/reject', [AdminUserController::class, 'rejectDriver']); // Rejeter un chauffeur
        });

        // Gestion des messages de contact
        Route::prefix('contact')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\ContactController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\Admin\ContactController::class, 'stats']);
            Route::get('/{id}', [App\Http\Controllers\Admin\ContactController::class, 'show']);
            Route::put('/{id}/read', [App\Http\Controllers\Admin\ContactController::class, 'markAsRead']);
            Route::put('/{id}/processed', [App\Http\Controllers\Admin\ContactController::class, 'markAsProcessed']);
            Route::put('/{id}/archive', [App\Http\Controllers\Admin\ContactController::class, 'archive']);
            Route::post('/{id}/reply', [App\Http\Controllers\Admin\ContactController::class, 'reply']);
            Route::delete('/{id}', [App\Http\Controllers\Admin\ContactController::class, 'destroy']);
        });

        // Gestion des tickets de support
        Route::prefix('support')->group(function () {
            Route::get('/', [App\Http\Controllers\Admin\SupportController::class, 'index']);
            Route::get('/stats', [App\Http\Controllers\Admin\SupportController::class, 'stats']);
            Route::get('/{id}', [App\Http\Controllers\Admin\SupportController::class, 'show']);
            Route::put('/{id}/status', [App\Http\Controllers\Admin\SupportController::class, 'updateStatus']);
            Route::post('/{id}/reply', [App\Http\Controllers\Admin\SupportController::class, 'reply']);
            Route::get('/{id}/download', [App\Http\Controllers\Admin\SupportController::class, 'downloadAttachment']);
            Route::delete('/{id}', [App\Http\Controllers\Admin\SupportController::class, 'destroy']);
        });
    });

    // ============================================
    // ROUTES PAIEMENT STRIPE - 🔴 PROTÉGÉES
    // ============================================
    Route::prefix('payment')->group(function () {
        Route::post('/intent', [StripeController::class, 'createPaymentIntent']); // Créer un Payment Intent
        Route::post('/confirm', [StripeController::class, 'confirmPayment']); // Confirmer le paiement
    });

    // ============================================
    // ROUTES ACOMPTE (DÉPÔT) - 10% OBLIGATOIRE - 🔴 PROTÉGÉES
    // ============================================
    Route::prefix('deposit')->middleware('role:client')->group(function () {
        Route::post('/initiate', [DepositController::class, 'initiateDeposit']); // Initier le paiement d'acompte
        Route::post('/confirm', [DepositController::class, 'confirmDeposit']); // Confirmer le paiement d'acompte
        Route::post('/refund', [DepositController::class, 'refundDeposit']); // Rembourser l'acompte
    });
});

// Route de test API (sans authentification)
Route::get('/test', function () {
    return response()->json([
        'message' => 'API SIMCAR VTC fonctionnelle',
        'version' => '1.0.0',
        'status' => 'active',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Route de santé de l'API
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => 'ok',
        'timestamp' => now()->toDateTimeString(),
    ]);
});

// Fallback pour routes API non trouvées
Route::fallback(function () {
    return response()->json([
        'message' => 'Route API non trouvée',
        'status' => 404,
    ], 404);
});

// Route webhook Stripe (SANS authentification)
Route::post('/webhooks/stripe', [StripeController::class, 'handleWebhook']);
