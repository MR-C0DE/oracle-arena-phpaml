# Changelog

## 0.5.0-beta.8 — 2026-10-03

- référence Framework `0.3.0-beta.6` ;
- ajoute des vues d’erreur personnalisables pour les réponses 404, 500 et les
  autres statuts HTTP ;
- fournit une feuille de style responsive dédiée aux erreurs ;
- déclare `src/views/errors` comme emplacement configurable des pages d’erreur.

## 0.5.0-beta.7 — 2026-09-26

- unifie le projet classique sous `src/` pour les contrôleurs, modèles, vues,
  middlewares, services et routes ;
- retire les dossiers racine `app/` et `routes/` des nouveaux projets ;
- référence Framework `0.3.0-beta.5` et adapte Composer, PHPStan, le rechargement
  automatique, les tests et la documentation à cette structure.

## 0.5.0-beta.6 — 2026-09-12

- adapte le test du point d’entrée au contrat AML View, où le frontend est
  monté par `FileApplication` et le contrôleur historique expose la santé JSON.

## 0.5.0-beta.5 — 2026-09-12

- rend le test de la page d’accueil compatible avec les projets classiques et
  avec leur conversion officielle en application AML View.

## 0.5.0-beta.4 — 2026-09-12

- impose `public/` comme seule racine web et refuse les traversées de chemin,
  y compris leurs formes encodées et doublement encodées ;
- vérifie en CI que le framework installé est strictement identique à ses
  sources ;
- ajoute un test HTTP réel du projet généré et référence Framework
  `0.3.0-beta.4`.

## 0.5.0-beta.3 — 2026-08-26

- déplace la composition de PHPAML Data dans le point d’entrée de
  l’application afin de préserver l’indépendance du Framework ;
- initialise tous les modules optionnels via le mécanisme générique de
  `bootstrappers` ;
- requiert PHPAML Framework `0.3.0-beta.3`.

## 0.5.0-beta.2 — 2026-08-23

- adopte la licence MIT pour le modèle officiel et les nouveaux projets
  générés à partir de celui-ci ;
- requiert PHPAML Framework `0.3.0-beta.2`.

## 0.5.0-beta.1 — 2026-08-21

- remplace `configs/app.php` par la configuration déclarative dans
  `phpaml.json` et `.env` ;
- ajoute `routes/WebApp.php` pour les applications classiques ;
- réserve `runtime/config/app.php` à la configuration générée ;
- prépare une base commune aux projets classiques, AML View et API ;
- requiert PHPAML Framework `0.3.0-beta.1`.

## 0.4.0-beta.5 — 2026-08-19

- retire le dossier `database/` de la racine du starter ;
- les migrations et seeders sont désormais créés à la demande sous
  `runtime/database/` par PHPAML Data.

## 0.4.0-beta.4 — 2026-08-18

- Framework `0.2.1-beta.1` requis pour le pipeline AML View ;
- correction de l’initialisation sans vues MVC et du rendu déclaratif via le
  pipeline HTTP principal ;
- suppression des avertissements `views_path` et de la fausse réponse 404 à
  l’ouverture d’un nouveau projet AML View.

## 0.4.0-beta.3 — 2026-08-17

- nouvelle page d’accueil moderne inspirée des grandes plateformes web ;
- anglais par défaut et français secondaire avec préférence mémorisée ;
- nouveau logo PHPAML transparent sans symbole frontal ;
- serveur local présenté sur `127.0.0.1:8910` ;
- interface responsive simplifiée et tests de rendu actualisés.

## 0.4.0-beta.2 — 2026-08-14

- structure `runtime/` et manifeste `phpaml.json` ;
- page de démarrage PHPAML et actualisation automatique.
