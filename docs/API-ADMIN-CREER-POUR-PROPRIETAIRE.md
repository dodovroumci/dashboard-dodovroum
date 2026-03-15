# Création de résidence par l’admin pour un propriétaire

## Problème

Quand un **administrateur** crée une résidence depuis le dashboard en choisissant un **propriétaire**, le dashboard envoie `proprietaireId` (ou `ownerId`) pour que la résidence soit attribuée à ce propriétaire.  
Actuellement l’API NestJS répond **400** avec le message :  
`"property ownerId should not exist"`  
et la résidence ne peut pas être créée au nom du propriétaire.

## Comportement attendu

- **Propriétaire** qui crée une résidence (JWT = propriétaire) : l’API utilise le JWT, **ne pas** accepter `ownerId`/`proprietaireId` dans le body (comportement actuel).
- **Admin** qui crée une résidence pour un propriétaire (JWT = admin) : l’API doit **accepter** un champ `proprietaireId` ou `ownerId` dans le body et attribuer la résidence à cet utilisateur (et non à l’admin).

## Modifications à faire côté API NestJS

### 1. DTO de création (CreateResidenceDto ou équivalent)

- Rendre le champ **optionnel** :
  - `proprietaireId?: string` ou `ownerId?: string`
- Ou créer un DTO dédié pour l’admin (ex. `AdminCreateResidenceDto`) qui inclut `proprietaireId`.

### 2. Contrôleur / service de création

Avant de créer la résidence en base :

- Si l’utilisateur connecté a le **rôle admin** (ou `isAdmin`) **et** que le body contient `proprietaireId` (ou `ownerId`) :
  - utiliser cet ID comme `proprietaireId` / `ownerId` de la résidence.
- Sinon (utilisateur non admin, ou champ absent) :
  - utiliser l’ID de l’utilisateur connecté (JWT) comme propriétaire, comme aujourd’hui.
  - pour un propriétaire, continuer à **rejeter** ou ignorer `ownerId`/`proprietaireId` dans le body si vous voulez éviter qu’un propriétaire s’attribue une résidence à un autre.

### 3. Exemple de logique (pseudo-code)

```ts
// Dans le service ou le contrôleur de création
const userId = req.user.id;
const isAdmin = req.user.role === 'admin' || req.user.isAdmin;
const proprietaireIdFromBody = body.proprietaireId ?? body.ownerId;

const ownerIdForResidence = (isAdmin && proprietaireIdFromBody)
  ? proprietaireIdFromBody
  : userId;

// Créer la résidence avec ownerIdForResidence
```

### 4. Validation optionnelle

- Vérifier que `proprietaireId` (si présent) correspond à un utilisateur existant avec le rôle propriétaire.
- Retourner **400** ou **403** si l’admin envoie un ID invalide ou non autorisé.

## Côté dashboard (déjà en place)

- Le dashboard envoie déjà `proprietaireId` et `ownerId` lorsque l’admin choisit un propriétaire à la création.
- Dès que l’API acceptera ce cas (admin + `proprietaireId`), la création au nom du propriétaire fonctionnera sans changement supplémentaire côté dashboard.
