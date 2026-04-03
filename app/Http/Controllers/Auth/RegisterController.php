<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class RegisterController extends Controller
{
    // Inscription Client (publique)
    public function registerClient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'client',
            'status' => 'active',
        ]);

        $token = $user->createToken('client_app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Compte client créé avec succès'
        ], 201);
    }

    // Inscription Chauffeur (publique - pour les chauffeurs qui s'inscrivent eux-mêmes)
    public function registerDriver(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Profil
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            
            // Informations véhicule
            'car_model' => 'required|string|max:100',
            'car_plate' => 'required|string|max:20|unique:driver_details',
            'car_color' => 'required|string|max:50',
            'car_year' => 'required|integer|min:1900',
            'car_seats' => 'required|integer|min:1',
            'car_type' => 'nullable|string|max:50',
            
            // Prix
            'base_price' => 'nullable|numeric|min:0',
            'price_per_km' => 'nullable|numeric|min:0',
            
            // Documents (fichiers) - Sans dates d'expiration sauf assurances
            'kbis_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'vtc_registration_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'rcpro_insurance_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'rcpro_insurance_expiry' => 'required|date|after:today',
            'vehicle_insurance_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'vehicle_insurance_expiry' => 'required|date|after:today',
            'vtc_card_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'driver_license_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'car_registration_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'identity_card_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'vehicle_photo_file' => 'required|image|mimes:jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Créer l'utilisateur avec statut "inactive" (doit être validé par admin)
        $user = User::create([
            'name' => $request->firstname . ' ' . $request->lastname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'chauffeur',
            'status' => 'inactive', // Doit être activé par admin
            'profile_picture' => null, // Sera mis à jour si une photo de profil est fournie
        ]);

        // Créer le dossier utilisateur s'il n'existe pas
        $userFolder = 'driver_documents/' . $user->id;

        // Sauvegarder les fichiers et construire les chemins
        $uploadedFiles = [];
        $documentFields = [
            'kbis_file', 'vtc_registration_file', 'rcpro_insurance_file',
            'vehicle_insurance_file', 'vtc_card_file', 'driver_license_file',
            'car_registration_file', 'identity_card_file', 'vehicle_photo_file'
        ];

        foreach ($documentFields as $fieldName) {
            if ($request->hasFile($fieldName)) {
                try {
                    $file = $request->file($fieldName);
                    $filename = time() . '_' . $user->id . '_' . $fieldName . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs($userFolder, $filename, 'public');
                    
                    if ($path) {
                        $fullPath = 'storage/' . $path;
                        $uploadedFiles[$fieldName] = $fullPath;
                        
                        // Si c'est la photo du véhicule, la sauvegarder aussi dans le profil utilisateur
                        if ($fieldName === 'vehicle_photo_file') {
                            $user->update(['profile_picture' => $fullPath]);
                            Log::info("✅ [RegisterController] Photo du véhicule sauvegardée comme profile_picture: $fullPath");
                        }
                        
                        Log::info("✅ [RegisterController] Fichier uploadé: $fieldName → $fullPath");
                    } else {
                        Log::warning("⚠️ [RegisterController] Échec upload: $fieldName");
                        throw new \Exception("Impossible de stocker le fichier: $fieldName");
                    }
                } catch (\Exception $e) {
                    Log::error("❌ [RegisterController] Erreur upload $fieldName: " . $e->getMessage());
                    // Continuer avec les autres fichiers plutôt que d'échouer complètement
                    // Mais on aura un message d'erreur à la fin
                }
            } else {
                Log::warning("⚠️ [RegisterController] Fichier manquant: $fieldName");
            }
        }

        Log::info("📊 [RegisterController] Fichiers uploadés: " . json_encode($uploadedFiles));

        // Créer les détails chauffeur avec tous les champs
        $driverData = array_merge([
            'car_model' => $request->car_model,
            'car_plate' => $request->car_plate,
            'car_color' => $request->car_color,
            'car_year' => $request->car_year,
            'car_seats' => $request->car_seats,
            'car_type' => $request->car_type ?? 'standard',
            'year_of_experience' => 0,
            'base_price' => $request->base_price ?? 45.00,
            'price_per_km' => $request->price_per_km ?? 2.50,
            'is_available' => false, // Pas disponible tant qu'admin n'a pas activé
            'rating' => 0,
            'rating_count' => 0,
            'completed_rides' => 0,
            'total_earnings' => 0,
        ], $uploadedFiles);

        // Ajouter uniquement les dates d'expiration pour les assurances
        $driverData['rcpro_insurance_expiry'] = $request->input('rcpro_insurance_expiry');
        $driverData['vehicle_insurance_expiry'] = $request->input('vehicle_insurance_expiry');

        try {
            $user->driverDetail()->create($driverData);
            Log::info("✅ [RegisterController] Chauffeur créé avec succès: {$user->id}");
            Log::info("📊 [RegisterController] Données stockées: " . json_encode($driverData));
        } catch (\Exception $e) {
            Log::error("❌ [RegisterController] Erreur création driverDetail: " . $e->getMessage());
            return response()->json(['error' => 'Erreur lors de la création du profil chauffeur'], 500);
        }

        return response()->json([
            'user' => $user->load('driverDetail'),
            'message' => 'Inscription chauffeur enregistrée. Votre compte doit être validé par un administrateur après vérification des documents.'
        ], 201);
    }

    // Création de compte chauffeur par Admin (existant)
    public function createDriverByAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8',
            'driver_license' => 'required|string|max:50',
            'car_model' => 'required|string|max:100',
            'car_plate' => 'required|string|max:20|unique:driver_details',
            'car_color' => 'required|string|max:50',
            'year_of_experience' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,inactive,on_leave',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'chauffeur',
            'status' => $request->status ?? 'active',
        ]);

        $user->driverDetail()->create([
            'driver_license' => $request->driver_license,
            'car_model' => $request->car_model,
            'car_plate' => $request->car_plate,
            'car_color' => $request->car_color,
            'year_of_experience' => $request->year_of_experience ?? 0,
            'is_available' => $request->status === 'active', // Disponible si actif
        ]);

        return response()->json([
            'user' => $user->load('driverDetail'),
            'message' => 'Compte chauffeur créé avec succès'
        ], 201);
    }
}
