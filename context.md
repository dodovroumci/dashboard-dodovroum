Context: Dashboard DodoVroum (Management System)
📌 État Actuel & Architecture
Le dashboard est l'outil de gestion centralisé pour l'écosystème DodoVroum.

Stack : Next.js 15 (App Router), Tailwind CSS, Shadcn/UI.

Backend : NestJS (API REST).

ORM : Prisma (mysql).

State Management : TanStack Query v5.

🔐 1. Gestion des Rôles & Accès (RBAC)
Le dashboard gère deux types d'utilisateurs avec des droits strictement isolés :

Super-Administrateur (DodoVroum) :

Vision Globale : Accès à toutes les résidences, véhicules et utilisateurs de la plateforme.

Modération : Validation des comptes propriétaires et des annonces.

Finances : Vue sur le chiffre d'affaires total et gestion des commissions.

Propriétaire (Partner) :

Isolation des données : Ne voit et ne gère que ses propres biens (proprietaireId filtering).

Gestion Opérationnelle : Ajout/Modification de ses résidences et véhicules.

Pack Builder : Création d'offres combinées (uniquement avec ses propres ressources).

Confirmation des réservations : Seul l’administrateur et le propriétaire (de la réservation concernée) peuvent approuver, rejeter ou confirmer le départ (checkout) d’une réservation. Côté dashboard Laravel : routes admin protégées par le middleware `admin`, routes owner par `owner` + vérification proprietaireId dans OwnerBookingController.

🎯 2. Objectifs de Finalisation (Scope Lock)
A. Le "Pack Builder" Multi-Propriétaire
[ ] Formulaire intelligent : Créer une offre combinée en liant une Residence_ID et une Vehicle_ID.

[ ] Contrainte métier : Un propriétaire ne peut créer un pack qu'avec les biens dont il est le propriétaire.

[ ] Pricing : Calcul du prix réduit pour le pack et validation via Zod.

B. Gestion des Stocks & Planning
[ ] Inventory Sync : Interface pour bloquer des dates manuellement.

[ ] Statut Temps Réel : Synchronisation immédiate avec l'application Flutter (via API NestJS).

C. Validation des Paiements
[ ] Suivi Mobile Money : Interface de monitoring des transactions (Orange, MTN, Wave).

[ ] Confirmation : Possibilité pour l'Admin/Propriétaire de marquer une réservation comme "Payée" après vérification manuelle si nécessaire.

🛠 3. Règles de Développement pour Cursor

📋 Convention de Nommage : proprietaireId vs ownerId
IMPORTANT : Utiliser TOUJOURS `proprietaireId` (standard métier français) au lieu de `ownerId` (standard technique Prisma) dans tous les composants Vue/React pour maintenir la cohérence Clean Architecture.

- ✅ Utiliser : `proprietaireId` (domaine métier)
- ❌ Éviter : `ownerId` (détail technique Prisma)
- Exception : L'API backend peut utiliser `ownerId` en interne, mais le frontend doit toujours utiliser `proprietaireId` dans les props, états, et validations.

Cette séparation garantit :
- Indépendance du frontend vis-à-vis de l'ORM
- Cohérence avec le domaine métier français
- Facilité de migration future si changement d'ORM
Isolation de la donnée : Chaque requête API doit inclure ou filtrer par proprietaireId (sauf pour le Super-Admin).

Design System : * Inspiration Dribbble (SaaS Dashboard).

Utiliser les composants DataTable de Shadcn/UI pour toutes les listes.

Cartes de statistiques en haut de page avec des icônes Lucide.

Code Quality : * Fournir des fichiers complets.

Utiliser Server Components par défaut pour le SEO/Performance, et Client Components pour les formulaires.

Localisation : Devise en FCFA, Timezone GMT (Abidjan).

🚫 4. Hors Scope (Ne pas développer)
Modification des thèmes (Dark mode non prioritaire).

Chat temps réel (Utiliser WhatsApp Business en attendant).

Système d'enchères ou de prix dynamiques complexes.

⚠️ Critiques & Risques (Anticipation de bug)
Fuite de données : Une faille de sécurité pourrait permettre à un Propriétaire A de voir les revenus d'un Propriétaire B. Solution : Toujours vérifier la propriété côté serveur (NestJS) et ne pas se fier uniquement à l'ID envoyé par le client.

Concurrence : Si l'Admin modifie un pack pendant qu'un client le réserve sur le mobile. Solution : Implémenter un système de versioning ou de lock temporaire.