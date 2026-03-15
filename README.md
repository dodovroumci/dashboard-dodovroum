# Dashboard Admin DodoVroum

Dashboard d'administration pour gérer les résidences, véhicules, offres combinées et réservations de DodoVroum.

## 🚀 Technologies

- **Laravel 12** (Backend)
- **Inertia.js** (Bridge Laravel ↔ Vue)
- **Vue 3** (Frontend)
- **Tailwind CSS** (Styling)
- **TypeScript** (Type safety)

## 📋 Prérequis

- PHP 8.2+
- Composer
- Node.js 18+
- Serveur API DodoVroum (port 3000)

## ⚙️ Installation

1. **Installer les dépendances PHP**
```bash
composer install
```

2. **Installer les dépendances Node**
```bash
npm install
```

3. **Configurer l'environnement**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configurer l'URL de l'API DodoVroum et les credentials admin**

Ajoutez dans votre fichier `.env` :
```env
# URL de l'application (utilisez HTTPS en production)
APP_URL=https://votre-domaine.com
APP_ENV=production

# Cookies sécurisés (HTTPS only) - activé automatiquement en production
# SESSION_SECURE_COOKIE=true

# Pour la production
DODOVROUM_API_URL=https://dodovroum.com/api
DODOVROUM_ADMIN_EMAIL=votre_email_admin@dodovroum.com
DODOVROUM_ADMIN_PASSWORD=votre_mot_de_passe_admin

# Pour le développement local (si vous avez l'API en local)
# APP_URL=http://localhost:8000
# APP_ENV=local
# DODOVROUM_API_URL=http://localhost:3000/api
# DODOVROUM_ADMIN_EMAIL=admin@dodovroum.com
# DODOVROUM_ADMIN_PASSWORD=admin123
```

⚠️ **Important** : 
- Les identifiants par défaut (`admin@dodovroum.com` / `admin123`) ne fonctionnent que pour un environnement de développement local. Pour la production, vous devez utiliser les identifiants réels de votre API.
- En production, configurez `APP_URL` avec HTTPS (ex: `https://votre-domaine.com`) pour activer le forçage HTTPS automatique.

5. **Créer un utilisateur admin**
```bash
php artisan db:seed --class=AdminUserSeeder
```

Par défaut, un admin est créé avec :
- Email: `admin@dodovroum.com`
- Password: `password`

## 🏃 Lancer le projet

1. **Démarrer le serveur Laravel**
```bash
php artisan serve
```

2. **Démarrer Vite (dans un autre terminal)**
```bash
npm run dev
```

3. **Accéder au dashboard**

- URL: `http://localhost:8000/admin/dashboard`
- Login rapide (dev): `http://localhost:8000/dev/admin-login`

## 📡 Connexion à l'API

Le dashboard se connecte automatiquement à l'API DodoVroum avec authentification Bearer Token.

Le service API :
- Se connecte automatiquement avec les credentials admin configurés
- Cache le token d'accès pendant 55 minutes
- Renouvelle automatiquement le token en cas d'expiration (401)

### Tester la connexion API

Pour tester que vos identifiants fonctionnent correctement :

```bash
php artisan api:test-connection
```

Vous pouvez aussi tester avec des identifiants spécifiques :

```bash
php artisan api:test-connection --email=votre_email@dodovroum.com --password=votre_mot_de_passe
```

Cette commande va :
- ✅ Tester l'authentification avec l'API
- ✅ Afficher le token obtenu
- ✅ Faire une requête de test pour vérifier que tout fonctionne

### Endpoints utilisés

- `GET /api/admin/stats` - Statistiques du dashboard
- `GET /api/admin/bookings/recent` - Réservations récentes
- `GET /api/admin/bookings` - Toutes les réservations
- `GET /api/admin/residences` - Toutes les résidences
- `GET /api/admin/vehicles` - Tous les véhicules
- `GET /api/admin/combo-offers` - Offres combinées
- `GET /api/admin/users` - Utilisateurs

### Format des données attendues

**Stats:**
```json
{
  "total_bookings": 42,
  "occupation_rate": 78,
  "vehicle_usage": "64 courses",
  "revenue": "4580000"
}
```

**Bookings:**
```json
[
  {
    "id": 1,
    "customer_name": "Assa Koné",
    "property_name": "Villa Cocody",
    "start_date": "2024-12-12",
    "end_date": "2024-12-15",
    "status": "confirmed"
  }
]
```

## 🏗️ Structure du projet

```
app/
  Http/
    Controllers/
      Admin/          # Contrôleurs admin
    Middleware/        # Middleware (Admin, Inertia)
  Services/           # Services API (DodoVroumApiService)
resources/
  js/
    admin/
      Components/      # Composants Vue réutilisables
      Pages/           # Pages Inertia
      AppAdmin.ts      # Point d'entrée admin
  css/
    admin.css          # Styles admin
routes/
  web.php             # Routes web
```

## 🔐 Sécurité

- Middleware `admin` pour protéger les routes admin
- Vérification `is_admin` dans la table `users`
- Authentification Laravel standard
- **HTTPS forcé en production** : Le dashboard redirige automatiquement HTTP vers HTTPS en production
- **Cookies sécurisés** : Les cookies de session sont automatiquement sécurisés (HTTPS only) en production

## 📝 Notes

- En cas d'erreur API, le dashboard affiche des valeurs par défaut (0)
- Les logs d'erreur sont dans `storage/logs/laravel.log`
- Le service API gère automatiquement les timeouts (10s)
