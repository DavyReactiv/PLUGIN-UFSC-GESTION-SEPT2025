# Audit UFSC Gestion (plugin maître)

> Document de synthèse produit par l'audit technique. Les preuves détaillées (fichiers/lignes) sont listées dans la réponse d'audit fournie par l'assistant.

## Portée
- Plugin maître **UFSC Gestion** et interactions potentielles avec un plugin dépendant (« UFSC Licences Competitions »).
- Objectifs : cartographie fonctionnelle, inventaire technique, stabilité/qualité, sécurité, compatibilité, plan d’amélioration sans régression.

## Résumé exécutif (court)
- Le plugin concentre la logique de clubs/licences et expose des endpoints admin, REST, AJAX et WooCommerce.
- Plusieurs surfaces publiques sont protégées par nonces, mais quelques points de sortie (`GET` non échappé, téléchargement d’attestations via AJAX nopriv) doivent être sécurisés.
- La compatibilité avec un plugin satellite dépendra d’un **contrat de noms**, d’un **préfixage strict** et d’un **chargement conditionnel** pour éviter collisions.

## Livrables détaillés
Voir la réponse d’audit structurée (cartographie, inventaire, risques, sécurité, compatibilité, plan d’amélioration) remise avec les preuves (fichiers + lignes).

