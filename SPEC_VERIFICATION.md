# Vérification du cahier des charges

Évaluation rapide des contrôleurs et entités actuels par rapport aux spécifications fournies.

## Constats principaux

- **Auth incomplète** : le token renvoyé dans l'API join est un placeholder et aucun flux JWT ou vérification d'équipe n'est implémenté.
- **Logique métier manquante** : pas de gestion des timeouts de choix, d'auto-attribution (AUTO_TIMEOUT / AUTO_LATE), ni du moteur d'effets immédiats/différés.
- **Validation et contraintes** : pas de contrôle anti-doublon côté API pour les scans acceptés par manche/équipe ou pour la taille maximale des équipes, au-delà des contraintes Doctrine.
- **Arbitrage et journalisation** : pas de journal détaillé des actions admin, des scans rejetés, ni de traçabilité complète requise.
- **Gestion GPS et radius** : aucune vérification de distance ou des statuts OUT_OF_RADIUS/INVALID_QR.
- **UI/temps réel** : endpoints de live et d'état ne calculent pas les timers restants et ne fournissent qu'une partie des informations attendues.

## Références code

- Token mobile non implémenté dans `/api/mobile/auth/join`.【F:src/Controller/MobileGameSessionController.php†L38-L75】
- Aucune logique de timeout/auto-pick/EV ou application d'effets dans les endpoints mobile (scan/pick).【F:src/Controller/MobileGameSessionController.php†L141-L206】
- Les endpoints admin ne gèrent pas la clôture automatique, les validations fortes, ni les actions d'arbitrage avancées (invalidate scan, assign-future, adjust-ev détaillé).【F:src/Controller/AdminGameSessionController.php†L122-L187】
