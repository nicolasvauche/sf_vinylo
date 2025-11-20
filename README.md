# Vinylo

**Votre collection, votre son, au bon moment.**

Vinylo est une application web et PWA destinée aux passionnés de vinyles.  
Elle permet d’organiser sa collection, de redécouvrir ses propres disques sous un nouveau jour  
et de profiter d’un assistant d’écoute intelligent qui recommande le bon disque au bon moment.

Deux modules composent l’expérience :

- **Vinylo Vault** — Votre collection, propre, claire, mise en valeur.
- **Vinylo Flow** — Votre assistant d’écoute, basé sur vos habitudes, vos moods et vos moments.

---

# Description

Vinylo offre une manière moderne et intuitive de gérer, explorer et vivre sa collection de vinyles.  
L’application propose un catalogue partagé sans doublons, un système d’état intuitif mais fiable,  
des suggestions d’écoute en fonction de l’ambiance, et des playlists personnelles pour tous les moments importants.

---

# Technologies utilisées

- Symfony 7
- Symfony UX (Stimulus, Live Components, Asset Mapper)
- API Discogs
- PWA (manifest + offline)
- Base de données relationnelle
- Algorithmes de recommandation
- Tests automatisés

---

# Pré-requis

- PHP 8.3+
- Composer
- Serveur web local (optionnel)
- Compte Discogs API

---

# Procédure d’installation

```console
git clone [url de ce repository]
cd sf_vinylo
composer install
```

```console
cp .env .env.local
```

Configurer ensuite :

- la base de données
- les clés Discogs
- les paramètres PWA

```console
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixttures:load
```

```console
php bin/console sass:build
symfony serve -d
```

Accès à l'application : http://localhost:8000

---

# Acteurs et fonctionnalités

## Utilisateur

- Créer un compte
- Gérer son profil
- Ajouter ses vinyles via Discogs
- Visualiser sa collection dans Vault
- Gérer l’état et la valeur de ses disques
- Créer des playlists personnelles
- Obtenir des suggestions via Flow
- Partager des playlists
- Comparer ses goûts avec d’autres utilisateurs
- Obtenir des idées cadeaux personnalisées

---

## Vinylo Vault — Module Collection

- Ajout simplifié depuis Discogs
- Organisation claire des vinyles
- Gestion intuitive de l’état
- Estimation de la valeur
- Tri et filtres avancés
- Statistiques de collection

---

## Vinylo Flow — Module Assistant

- Suggestions selon humeur, météo, heure, ambiance
- Recommandations contextuelles (travail, détente, soirée…)
- Redécouvertes
- Mode anti-routine
- Prise en compte des formats (LP/EP/45T)
- Playlists automatiques basées sur un mood

---

# Auteur

**Nicolas Vauché**  
[hello@nicolasvauche.net](hello@nicolasvauche.net)

---

# Licence GPL

Ce projet est distribué sous **GNU GPL v3**.

Vous pouvez utiliser, modifier et redistribuer le code, à condition de conserver la même licence pour toute version
dérivée.
