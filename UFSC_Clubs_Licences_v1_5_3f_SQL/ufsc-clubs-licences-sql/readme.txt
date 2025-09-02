=== UFSC – Clubs & Licences (SQL) ===
Contributors: Davy – Studio REACTIV (pour l'UFSC)
Stable tag: 1.5.3a
Requires at least: 6.0
Tested up to: 6.6
License: GPLv2 or later

Plugin SQL-first pour l'UFSC : mapping complet vers vos tables `clubs` et `licences`, formulaires complets (admin & front), badges de statut, exports CSV, mini-dashboard.

== Installation ==
1. Téléversez le ZIP dans Extensions > Ajouter > Téléverser.
2. Activez.
3. Allez dans **UFSC – Données (SQL) > Réglages** et pointez *Table Clubs* et *Table Licences* vers vos tables.

== Shortcodes ==
- [ufsc_sql_my_club] : carte récap du club lié au `responsable_id` connecté.
- [ufsc_sql_licence_form] : formulaire complet de demande de licence (statut par défaut *en_attente*).

== Notes ==
- Les booléens sont stockés **1 = oui / 0 = non**.
- Les statuts utilisés: *en_attente*, *valide*, *a_regler*, *desactive*.
