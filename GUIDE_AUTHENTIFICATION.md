# 🔐 Guide d'authentification API DodoVroum

## Problème courant

Si vous rencontrez l'erreur **"Identifiants invalides" (401)** lors de la création d'utilisateur ou d'autres opérations, c'est que les identifiants dans votre fichier `.env` ne sont pas valides pour l'API de production.

## ✅ Solution rapide

### 1. Mettre à jour le fichier `.env`

Ouvrez votre fichier `.env` et mettez à jour ces lignes :

```env
DODOVROUM_API_URL=https://dodovroum.com/api
DODOVROUM_ADMIN_EMAIL=votre_email_admin_reel@dodovroum.com
DODOVROUM_ADMIN_PASSWORD=votre_mot_de_passe_admin_reel
```

⚠️ **Important** : Les identifiants par défaut (`admin@dodovroum.com` / `admin123`) ne fonctionnent **QUE** pour un environnement de développement local, pas pour la production.

### 2. Vider le cache

```bash
php artisan config:clear
php artisan cache:clear
```

### 3. Tester la connexion

**Avec les identifiants du `.env` :**
```bash
php artisan api:test-connection
```

**Avec des identifiants spécifiques (pour tester sans modifier le `.env`) :**
```bash
php artisan api:test-connection --email=votre_email@dodovroum.com --password=votre_mot_de_passe
```

**Pour voir le token complet :**
```bash
php artisan api:test-connection --show-token
```

### 4. Vérifier le résultat

Si la commande affiche :
- ✅ **Authentification réussie** → Vous pouvez maintenant créer des utilisateurs
- ❌ **Échec de l'authentification** → Vérifiez vos identifiants dans le `.env`

## 🔍 Diagnostic

### Vérifier les identifiants utilisés

La commande de test affiche :
- L'URL de l'API utilisée
- L'email utilisé pour l'authentification
- Le nombre d'utilisateurs récupérés (pour confirmer que l'API répond)

### Vérifier les logs

Si l'authentification échoue, consultez `storage/logs/laravel.log` pour voir :
- Le message d'erreur exact de l'API
- L'URL de l'endpoint appelé
- Le statut HTTP retourné

## 📝 Exemple de sortie réussie

```
🔐 Test de connexion à l'API DodoVroum

📡 URL de l'API: https://dodovroum.com/api
📧 Email: votre_email@dodovroum.com
🔑 Mot de passe: ********

🗑️  Cache du token vidé

⏳ Tentative d'authentification...
🧪 Test d'une requête API (cela va authentifier automatiquement)...

✅ Authentification réussie !

📊 Nombre d'utilisateurs récupérés: 15
🎫 Token (tronqué): eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
💡 Utilisez --show-token pour afficher le token complet

📋 Informations du token:
   ⏰ Expire le: 2025-12-30 11:58:22
   📧 Email: votre_email@dodovroum.com
   👤 Rôle: ADMIN
```

## 🚨 Erreurs courantes

### "Identifiants invalides" (401)
- **Cause** : Les identifiants dans le `.env` sont incorrects
- **Solution** : Mettez à jour `DODOVROUM_ADMIN_EMAIL` et `DODOVROUM_ADMIN_PASSWORD` avec les vrais identifiants

### "Impossible de se connecter à l'API"
- **Cause** : L'URL de l'API est incorrecte ou l'API n'est pas accessible
- **Solution** : Vérifiez `DODOVROUM_API_URL` dans le `.env`

### Token expiré
- **Cause** : Le token en cache a expiré (durée de vie : 55 minutes)
- **Solution** : La commande de test vide automatiquement le cache et génère un nouveau token

## 💡 Astuce

Pour tester rapidement avec différents identifiants sans modifier le `.env` :

```bash
php artisan api:test-connection --email=test@dodovroum.com --password=test123
```

Si ça fonctionne, mettez ces identifiants dans votre `.env` !

