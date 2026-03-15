# Approbation d’une réservation (PATCH /bookings/:id/approve)

## Contexte

Le dashboard Laravel appelle **PATCH `/api/bookings/:id/approve`** quand un admin ou un propriétaire clique sur « Approuver » pour une réservation. Après redirection vers la liste, le dashboard affiche **« Confirmée »** uniquement si l’API renvoie la réservation avec **`ownerConfirmedAt`** renseigné.

## Comportement actuel observé

- Le dashboard envoie bien la requête et reçoit une réponse **200** (« Réservation approuvée avec succès »).
- Lors du rechargement de la liste (GET des réservations), les réservations reviennent **sans** `ownerConfirmedAt`.
- Résultat : la ligne reste affichée en **« En attente »** et le compteur **Confirmées** reste à 0.

Donc l’endpoint **approve** côté NestJS ne met probablement **pas à jour** la réservation en base (ou ne renvoie pas les champs attendus).

## Comportement attendu côté API NestJS

Lors de **PATCH `/api/bookings/:id/approve`** (avec un JWT admin ou propriétaire autorisé) :

1. **Mettre à jour la réservation** en base avec au moins :
   - **`ownerConfirmedAt`** = date/heure actuelle (ISO 8601, ex. `new Date().toISOString()`).
   - Optionnellement **`status`** = `"confirmed"` (ou `"confirmee"`) si votre schéma utilise ce champ.

2. **Retourner** la réservation mise à jour (ou au moins un 200/204).

3. Lors du **GET** des réservations (liste ou détail), la réservation approuvée doit inclure **`ownerConfirmedAt`** dans la réponse JSON.

## Champs utilisés par le dashboard

| Champ API            | Rôle pour l’affichage                                      |
|----------------------|------------------------------------------------------------|
| `ownerConfirmedAt`   | **Requis** pour afficher le statut « Confirmée » et compter dans « Confirmées » / revenus. |
| `status`             | Utilisé en complément ; sans `ownerConfirmedAt`, « confirmed » est affiché comme « En attente ». |

## Exemple de mise à jour (côté NestJS)

```ts
// Dans le service ou contrôleur d’approbation
await this.prisma.booking.update({
  where: { id: bookingId },
  data: {
    ownerConfirmedAt: new Date(),
    status: 'confirmed',  // si votre schéma Prisma a ce champ
  },
});
```

Vérifier que le modèle Prisma `Booking` comporte bien un champ **`ownerConfirmedAt`** (DateTime ou équivalent) et qu’il est exposé dans les réponses API (GET bookings, GET bookings/:id).

---

## Format de réponse recommandé (optionnel)

Pour faciliter la logique côté Dashboard (et une évolution sans rechargement complet), `formatBookingResponse` peut exposer explicitement :

| Champ               | Type    | Description |
|---------------------|--------|-------------|
| `ownerConfirmedAt`  | string \| null | Date ISO de l’approbation ; **preuve temporelle** pour « Confirmée ». |
| `isPendingApproval` | boolean | `!booking.ownerConfirmedAt && booking.status === 'PENDING'` — affichage du bouton Approuver. |
| `isConfirmed`       | boolean | `!!booking.ownerConfirmedAt` — réservation validée par admin/proprio. |
| `canBeManaged`      | boolean | `status !== 'CANCELLED' && status !== 'COMPLETED'` — actions encore possibles. |

Le Dashboard Laravel utilise déjà **`ownerConfirmedAt`** comme pivot pour le statut et **`isPendingApproval`** quand il est présent dans la liste. Les champs `isConfirmed` et `canBeManaged` restent optionnels pour de futures évolutions (ex. mise à jour partielle sans rechargement).

---

## Prochaines étapes

1. **Test de bout en bout** : Créer une réservation test depuis l’app, puis l’approuver depuis le Dashboard — vérifier que « Confirmées » et les stats se mettent à jour après rechargement.
2. **Temps réel (optionnel)** : WebSockets (ex. Socket.io) pour notifier le Dashboard des nouvelles réservations ou changements de statut sans rafraîchir la page.
