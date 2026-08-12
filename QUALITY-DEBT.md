# Dette qualité historique

Le contrôle PHPCS versionné bloque les nouvelles erreurs de sécurité WPCS sur les services métier et WooCommerce modifiés. Le style historique du plugin n'est pas masqué par un baseline généré : un audit complet au 12 août 2026 relève principalement des indentations mixtes, plusieurs instructions sur une ligne et des alignements de tableaux.

Ces écarts de forme ne sont pas corrigés en masse dans ce correctif afin d'éviter une réécriture hors périmètre. Ils devront être résorbés fichier par fichier, puis les fichiers conformes seront ajoutés au contrôle `WordPress-Core` complet.
