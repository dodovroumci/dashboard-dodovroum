# DELETE /api/vehicles/:id — Erreur 500 côté API

## Contexte

Lorsqu’un propriétaire supprime un véhicule depuis le dashboard (espace propriétaire), le dashboard envoie :

- **Méthode :** `DELETE`
- **URL :** `https://api.dodovroum.com/api/vehicles/:id`
- **Headers :** `Authorization: Bearer <token>`, `Accept: application/json`

L’API NestJS répond actuellement **500 Internal server error** avec un body du type :

```json
{"statusCode":500,"message":"Internal server error"}
```

Le dashboard gère cette réponse (redirection avec message d’erreur), mais la suppression ne s’effectue pas.

## Côté dashboard (Laravel)

- Vérification que le véhicule existe et appartient au propriétaire (proprietaireId).
- Appel à l’API `DELETE /api/vehicles/:id`.
- En cas de 500, affichage d’un message explicite : *« La suppression a échoué côté serveur. Le véhicule est peut-être lié à des réservations ou offres combinées. »*

## À corriger côté API (NestJS)

1. **Contraintes / relations**  
   Un véhicule peut être lié à des réservations, des offres combinées, des dates bloquées, etc. Vérifier :
   - Les contraintes de clé étrangère (Prisma / BDD).
   - Si une suppression en cascade est prévue ou si l’endpoint doit refuser la suppression et renvoyer un message clair (ex. 400 ou 409) quand des réservations/offres existent.

2. **Logs backend**  
   Consulter les logs NestJS au moment du `DELETE` pour voir l’exception exacte (Prisma, validation, etc.).

3. **Réponse en cas d’impossibilité de suppression**  
   Idéalement, en cas de véhicule lié à des données :
   - **Status :** 400 ou 409
   - **Body :** message explicite, ex.  
     `{"statusCode":400,"message":"Impossible de supprimer : ce véhicule est utilisé par des réservations ou offres."}`  
   Le dashboard pourra alors afficher ce message à l’utilisateur.

## Exemple de véhicule concerné (logs)

- **ID :** `cmm0icars000w138lrd13in11`
- **Requête :** `DELETE https://api.dodovroum.com/api/vehicles/cmm0icars000w138lrd13in11`
- **Réponse :** 500, `{"statusCode":500,"message":"Internal server error"}`
