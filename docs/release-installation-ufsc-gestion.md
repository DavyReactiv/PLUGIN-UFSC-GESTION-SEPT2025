# Installation release UFSC Gestion

Cette note accompagne le ZIP `PLUGIN-UFSC-GESTION-SEPT2025-main-release.zip` destiné aux installations WordPress qui chargent le plugin depuis le dossier :

`wp-content/plugins/PLUGIN-UFSC-GESTION-SEPT2025-main/`

## Procédure recommandée

1. **Ne pas écraser l'ancien dossier par-dessus** si une erreur fatale PHP a déjà eu lieu.
2. Renommer l'ancien dossier serveur :
   `PLUGIN-UFSC-GESTION-SEPT2025-main`
   en :
   `PLUGIN-UFSC-GESTION-SEPT2025-main-OLD`.
3. Installer ou extraire le ZIP propre :
   `PLUGIN-UFSC-GESTION-SEPT2025-main-release.zip`.
4. Vérifier après extraction que le fichier serveur :
   `wp-content/plugins/PLUGIN-UFSC-GESTION-SEPT2025-main/includes/admin/class-sql-admin.php`
   contient une seule déclaration de la méthode `is_licence_id_debug_enabled`.
5. Vérifier que le fichier serveur :
   `wp-content/plugins/PLUGIN-UFSC-GESTION-SEPT2025-main/class-sql-admin.php`
   reste uniquement un loader et charge la classe réelle avec `require_once`.
6. Purger les caches WordPress, serveur et OPcache si disponibles.
7. Activer le plugin dans WordPress.
8. En cas d'erreur, consulter immédiatement :
   `wp-content/debug.log`.

## Contrôles rapides côté serveur

Depuis le dossier du plugin extrait, les commandes suivantes doivent confirmer l'absence de doublon :

```bash
rg -n "is_licence_id_debug_enabled" . --glob "*.php" --glob "!vendor/**"
rg -n "class UFSC_SQL_Admin" . --glob "*.php" --glob "!vendor/**"
php -l includes/admin/class-sql-admin.php
php -l class-sql-admin.php
php -l ufsc-clubs-licences-sql.php
```

Résultat attendu : une seule déclaration réelle de `class UFSC_SQL_Admin` dans `includes/admin/class-sql-admin.php` et une seule déclaration de la méthode `is_licence_id_debug_enabled`.
