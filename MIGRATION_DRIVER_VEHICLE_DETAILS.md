# Migration: Ajout des champs de voiture avancés pour les chauffeurs

## Résumé des changements

Cette migration ajoute des champs supplémentaires à la table `driver_details` pour stocker plus d'informations sur le véhicule des chauffeurs.

## Champs ajoutés

### Champs de véhicule
- **car_year** (int): Année de fabrication du véhicule (1990 - année actuelle + 1)
- **car_registration** (string): Numéro d'immatriculation/Carte grise
- **car_seats** (int): Nombre de places assises (défaut: 4)
- **car_type** (string): Type de voiture (berline, SUV, monospace, coupé, cabriolet, break)
- **car_insurance** (string): Numéro de police d'assurance
- **car_insurance_expiry** (date): Date d'expiration de l'assurance
- **car_inspection_expiry** (date): Date d'expiration du contrôle technique

### Champs de permis
- **driver_license_expiry** (date): Date d'expiration du permis de conduire

## Fichiers modifiés

### Backend (Laravel)
1. **app/Models/DriverDetail.php**
   - Mise à jour du tableau `$fillable` avec les nouveaux champs

2. **app/Http/Controllers/Chauffeur/ProfileController.php**
   - Fonction `update()` refactorisée pour :
     - Valider tous les nouveaux champs
     - Séparer les données utilisateur des données driver_details
     - Mettre à jour la table `driver_details` au lieu de `users`
   - Les champs car_* ne sont plus stockés dans la table `users`

### Frontend (Flutter)
1. **lib/features/auth/data/models/user_model.dart**
   - Ajout des nouveaux champs à la classe `DriverDetailData`
   - Mise à jour du constructeur avec tous les nouveaux paramètres

## Validation

Les validations suivantes ont été ajoutées au controller:
- `car_year`: entier entre 1990 et (année actuelle + 1)
- `car_seats`: entier entre 1 et 10
- `car_type`: doit être l'une des valeurs prédéfinies (berline, SUV, monospace, coupé, cabriolet, break)
- Les dates doivent être au format date valide

## Instructions de mise en place

1. **Exécuter la migration Laravel**:
```bash
php artisan migrate
```

2. **Régénérer le code Dart** (si build_runner est utilisé):
```bash
flutter pub run build_runner build --delete-conflicting-outputs
```

3. **Tester l'endpoint d'update du profil chauffeur**:
```bash
POST /api/driver/profile
Content-Type: application/json

{
  "car_year": 2022,
  "car_registration": "AB-123-CD",
  "car_seats": 4,
  "car_type": "berline",
  "car_insurance": "POL123456",
  "car_insurance_expiry": "2025-12-31",
  "car_inspection_expiry": "2026-06-15",
  "driver_license_expiry": "2030-01-15"
}
```

## Avantages

- ✅ Informations de véhicule plus complètes
- ✅ Traçabilité des dates d'expiration (assurance, contrôle technique, permis)
- ✅ Classification des types de véhicules
- ✅ Données cohérentes entre la base de données et l'application mobile
- ✅ Meilleure gestion administrative

## Notes importantes

- Les anciens champs `car_model`, `car_plate`, `car_color` et `year_of_experience` restent dans la table `driver_details`
- Les champs dans la table `users` (car_model, car_plate, car_color, year_of_experience) sont dépréciés et seront supprimés dans une version future
- Utiliser le nouveau endpoint pour mettre à jour tous les champs du véhicule
