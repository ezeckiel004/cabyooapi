<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class AdvertisementController extends Controller
{
    /**
     * Récupérer toutes les annonces (actives et inactives)
     */
    public function index()
    {
        try {
            $advertisements = Advertisement::orderBy('order', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $advertisements,
                'count' => $advertisements->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des annonces: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Récupérer les annonces actives (pour le frontend client/driver)
     */
    public function getActive()
    {
        try {
            $now = now();
            
            $advertisements = Advertisement::where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('start_date')
                        ->orWhere('start_date', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $now);
                })
                ->orderBy('order', 'asc')
                ->get()
                ->map(function ($ad) {
                    return [
                        'id' => $ad->id,
                        'title' => $ad->title,
                        'description' => $ad->description,
                        'image' => $ad->image ? $ad->image : null,
                        'badge' => $ad->badge,
                        'badgeColor' => $ad->badge_color,
                        'link' => $ad->link,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $advertisements,
                'count' => $advertisements->count(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des annonces actives: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer une nouvelle annonce
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // max 5MB
                'badge' => 'required|string|max:50',
                'badge_color' => 'required|regex:/^#[A-Fa-f0-9]{6}$/', // Validation hex color
                'link' => 'required|url',
                'is_active' => 'boolean',
                'order' => 'integer|min:0',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Gérer l'upload de l'image
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $path = $file->store('advertisements', 'public');
                $validated['image'] = $path;
            }

            $advertisement = Advertisement::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Annonce créée avec succès',
                'data' => $advertisement,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de l\'annonce: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher une annonce spécifique
     */
    public function show(Advertisement $advertisement)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $advertisement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Annonce non trouvée',
            ], 404);
        }
    }

    /**
     * Mettre à jour une annonce
     */
    public function update(Request $request, Advertisement $advertisement)
    {
        try {
            $validated = $request->validate([
                'title' => 'string|max:255',
                'description' => 'string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
                'badge' => 'string|max:50',
                'badge_color' => 'regex:/^#[A-Fa-f0-9]{6}$/',
                'link' => 'url',
                'is_active' => 'boolean',
                'order' => 'integer|min:0',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            // Gérer le remplacement de l'image
            if ($request->hasFile('image')) {
                // Supprimer l'ancienne image si elle existe
                if ($advertisement->image) {
                    Storage::disk('public')->delete($advertisement->image);
                }
                
                $file = $request->file('image');
                $path = $file->store('advertisements', 'public');
                $validated['image'] = $path;
            }

            $advertisement->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Annonce mise à jour avec succès',
                'data' => $advertisement,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'annonce: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer une annonce
     */
    public function destroy(Advertisement $advertisement)
    {
        try {
            // Supprimer l'image si elle existe
            if ($advertisement->image) {
                Storage::disk('public')->delete($advertisement->image);
            }

            $advertisement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Annonce supprimée avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'annonce: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Changer le statut d'une annonce (actif/inactif)
     */
    public function toggleActive(Advertisement $advertisement)
    {
        try {
            $advertisement->update([
                'is_active' => !$advertisement->is_active,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statut de l\'annonce mis à jour',
                'data' => $advertisement,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour l'ordre d'affichage des annonces
     */
    public function updateOrder(Request $request)
    {
        try {
            $validated = $request->validate([
                'advertisements' => 'required|array',
                'advertisements.*.id' => 'required|exists:advertisements,id',
                'advertisements.*.order' => 'required|integer|min:0',
            ]);

            foreach ($validated['advertisements'] as $item) {
                Advertisement::find($item['id'])->update([
                    'order' => $item['order'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ordre des annonces mis à jour',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'ordre: ' . $e->getMessage(),
            ], 500);
        }
    }
}
