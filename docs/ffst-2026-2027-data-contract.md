# Contrat de données FFST 2026-2027

Ce document fixe les règles de compatibilité entre UFSC Gestion et les documents officiels FFST 2026-2027.

## Principes

- Le compte club est la source de vérité pour l'affiliation.
- Les nouveaux champs FFST sont additifs et non bloquants pour les clubs existants.
- Une information absente est signalée ; elle n'est jamais inventée.
- Les exports lisent les données existantes et ne modifient ni club, ni licence, ni commande, ni saison historique.
- Les modèles officiels sont toujours traités sur une copie de travail.
- Un document peut être régénéré à tout moment à partir de l'état courant du dossier et de la saison sélectionnée.

## Profil club / affiliation

Champs canoniques existants conservés : nom, adresse, complément d'adresse, code postal, ville, pays, téléphone, e-mail, site, déclaration/RNA, dirigeants et documents.

Compléments FFST 2026-2027 :

- N° affiliation FFST
- agrément Jeunesse et Sports : numéro + date
- salle d'entraînement : adresse + complément + code postal + ville
- disciplines FFST + codes disciplines
- correspondant FFST : nom + prénom + téléphone + e-mail
- signataire : nom + prénom + qualité
- président / secrétaire / trésorier / entraîneur : adresse détaillée
- père / mère uniquement lorsque ces mentions sont nécessaires au document officiel pour une naissance à l'étranger

## Complétude

Le profil reste utilisable même si le dossier FFST est incomplet. L'interface affiche un pourcentage et la liste des informations à compléter. La complétude est informative et ne doit pas empêcher la sauvegarde du compte club.

## Exports attendus

1. Affiliation / réaffiliation FFST : modèle officiel prérempli.
2. Licences dirigeants : bordereau officiel prérempli depuis les licences et fonctions du club.
3. Licences pratiquants : bordereau officiel prérempli, avec séparation par discipline lorsque le modèle FFST le demande.
4. Matrice de contrôle UFSC/FFST : export structuré facilitant le contrôle et le diagnostic des données manquantes.

## Sécurité / historique

Aucune migration destructive. Les nouvelles colonnes acceptent NULL. Aucun recalcul automatique des anciennes saisons. Toute évolution du modèle FFST doit passer par le mapping d'export et non par une réécriture des données historiques.
