# Architecture

Plugin GLPI `containers` (dossier `plugins/containers`), espace de noms `GlpiPlugin\Containers`.

## Arborescence

Les éléments marqués *(lot 2)* ou *(lot 3)* sont prévus et n'existent pas encore.

```text
containers/
├── setup.php                  version, compatibilité GLPI 11 et 12, hooks
├── hook.php                   installation, mise à jour, désinstallation
├── composer.json
├── front/                     pages (une par écran)
│   ├── connection.php         liste des connexions (Portainer, WUD, Harbor)
│   ├── connection.form.php
│   ├── service.php            liste des services importés
│   ├── service.form.php
│   └── version.php            tableau des versions par service              (lot 2)
├── src/
│   ├── Compat/                écarts entre GLPI 11 et 12 (classes de base, client HTTP)
│   ├── Install/Installer.php  schéma (tables glpi_plugin_containers_*)
│   ├── Connection.php         connexion à une source : type, URL, clé chiffrée
│   ├── Service.php            service Swarm importé
│   ├── Node.php               nœud Swarm, rattaché à un ordinateur
│   ├── Importer.php           import planifié (tâche automatique, toutes les heures)
│   ├── Profile.php            droits
│   ├── Portainer/             client, contexte réseau, correspondance API → GLPI
│   ├── Wud/                                                                  (lot 2)
│   │   ├── WudClient.php      lecture de /api/containers, authentification basique
│   │   ├── WudContext.php     contexte réseau (réseaux privés autorisés sous GLPI 12)
│   │   ├── WudException.php
│   │   └── WudMapper.php      conteneur WUD → service (empreinte, image, tag)
│   ├── Registry/              repli sans WUD : Docker Hub, API v2 de Harbor   (lot 2)
│   │   ├── RegistryClient.php
│   │   └── TagLister.php
│   ├── Version/                                                              (lot 2)
│   │   ├── VersionComparator.php  écart correctif, mineure, majeure ; `latest` non suivable
│   │   └── ImageVersion.php       version déployée et dernière version d'un service
│   ├── Ticket/                                                               (lot 2)
│   │   └── UpdateTicket.php   un ticket par service au-delà des seuils, suivi si la situation change
│   └── Harbor/                projets, dépôts, artefacts, analyses           (lot 3)
├── templates/                 Twig uniquement (aucun HTML dans le PHP)
├── locales/                   fr_FR, en_GB, de_DE, es_ES, it_IT, pl_PL, pt_BR
└── tests/
    ├── Unit/                  sans base de données
    ├── mock-portainer/        faux Portainer (Python) et jeu de données fictif
    └── mock-wud/              faux WUD et jeu de données fictif               (lot 2)
```

## Tables

| Table | Contenu |
| --- | --- |
| `glpi_plugin_containers_connections` | Source (portainer, wud, harbor), URL, clé chiffrée, entité, dernier import |
| `glpi_plugin_containers_services` | Service Swarm : stack (Application), image, tag, empreinte, réplicas, état « retiré » |
| `glpi_plugin_containers_nodes` | Nœud Swarm ↔ ordinateur GLPI |
| `glpi_plugin_containers_imageversions` *(lot 2)* | Service, tag et empreinte déployés, dernière version, type d'écart, source (wud ou registre), date de vérification |

## WUD (lot 2)

- Lecture seule de l'API de WUD (`GET /api/containers`) ; les déclencheurs de WUD ne sont jamais
  appelés.
- Pour chaque conteneur suivi : image, tag et empreinte locaux, dernière version trouvée par WUD,
  type d'écart. Le plugin ne recalcule pas ce que WUD a déjà calculé.
- Rattachement au service : par l'empreinte de l'image, puis par le nom du service Swarm porté par
  le conteneur, puis par image et tag. Un conteneur sans correspondance est listé à part.
- Sans WUD : le plugin interroge lui-même Docker Hub ou l'API v2 de Harbor pour la liste des tags.
- Le tag `latest` (ou tout tag non versionné) est signalé comme non suivable de façon fiable.

## Règles communes

- Aucune action d'écriture vers Portainer, WUD, Harbor ou un registre.
- Aucun changement automatique de statut, priorité, affectation ou droits dans GLPI.
- Pas de SQL brut hors de l'installateur ; Twig pour tout l'affichage.
- Appels HTTP par le client de GLPI ; sous GLPI 12, les adresses privées ne sont joignables que si
  le contexte du plugin est autorisé (`GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS`).

## Tests

- `vendor/bin/phpunit` : tests unitaires (correspondances, comparaison de versions, seuils).
- Faux serveurs Python dans `tests/mock-*` : jeux de données fictifs, aucune adresse ni nom réel.
- Test dans une vraie instance GLPI 11 et 12 avant chaque version, en naviguant comme un
  utilisateur.
