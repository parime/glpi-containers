# Architecture

Plugin GLPI « Inventaire Docker » : clé `dockerinventory` (dossier `plugins/dockerinventory`), espace de
noms `GlpiPlugin\Dockerinventory`.

## Arborescence

Les éléments marqués *(lot 2)* ou *(lot 3)* sont prévus et n'existent pas encore.

```text
dockerinventory/
├── setup.php                  version, compatibilité GLPI 11 et 12, hooks
├── hook.php                   installation, mise à jour, désinstallation
├── composer.json
├── front/                     pages (une par écran)
│   ├── connection.php         liste des connexions Portainer (droit Configuration en modification)
│   ├── connection.form.php    formulaire, « Tester la connexion », « Importer maintenant »
│   ├── service.php            liste des services importés (recherche GLPI)
│   ├── service.form.php       fiche d'un service, en lecture seule
│   └── version.php            tableau des versions par service              (lot 2)
├── src/
│   ├── Compat/
│   │   ├── GlpiVersion.php    GLPI 11 ou 12
│   │   ├── Http.php           GET par le client HTTP de GLPI, sans redirection
│   │   └── Base/              classes parentes : propriétés typées (GLPI 12) ou non (GLPI 11)
│   ├── Install/Installer.php  tables, droit, tâche automatique, préférences d'affichage ; désinstallation
│   ├── Connection.php         connexion Portainer : URL, clé chiffrée (GLPIKey), environnement des
│   │                          applications créées, dernier compte rendu ; tâche automatique horaire
│   ├── Importer.php           import : stacks, services, nœuds, tâches en cours, retraits
│   ├── Service.php            service Swarm (lecture seule) ; onglet « Services » des applications
│   ├── Node.php               nœud Swarm ; onglet « Services du nœud » des ordinateurs
│   ├── Profile.php            onglet « Inventaire Docker » des profils (droit plugin_dockerinventory_service)
│   ├── Portainer/
│   │   ├── PortainerClient.php     lecture seule : /api/endpoints, /api/stacks, /docker/{services,
│   │   │                           nodes,tasks,networks}, en-tête X-API-Key, transport injectable
│   │   ├── PortainerContext.php    contexte réseau (réseaux privés autorisés sous GLPI 12)
│   │   ├── PortainerException.php  codes d'erreur stables, jamais le texte d'origine ni la clé
│   │   ├── PortainerMapper.php     réponse Docker → valeurs enregistrées (fonctions pures)
│   │   └── UrlPolicy.php           HTTPS obligatoire sauf autorisation, ni identifiants ni requête
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
├── locales/                   fr_FR, en_GB, de_DE, es_ES, it_IT, pl_PL, pt_BR (+ variantes régionales)
└── tests/
    ├── Unit/                  sans base de données
    ├── Integration/           import complet contre la vraie base GLPI (transport en mémoire)
    ├── security/setup.php     jeu de données des contrôles de sécurité communs
    ├── mock-portainer/        faux Portainer (Python) et jeu de données fictif
    └── mock-wud/              faux WUD et jeu de données fictif               (lot 2)
```

## Tables

| Table | Contenu |
| --- | --- |
| `glpi_plugin_dockerinventory_connections` | Connexion Portainer : URL, clé chiffrée, HTTP autorisé ou non, entité, environnement des applications créées, dernier import et son compte rendu |
| `glpi_plugin_dockerinventory_stacks` | Stack Swarm de Portainer ↔ Application GLPI, état « retiré » |
| `glpi_plugin_dockerinventory_services` | Service Swarm : stack, Application, image, tag, empreinte, réplicas voulus et en cours, ports, réseaux, noms des secrets, état « retiré » |
| `glpi_plugin_dockerinventory_nodes` | Nœud Swarm : nom d'hôte, rôle, état, version de Docker, ordinateur GLPI rattaché |
| `glpi_plugin_dockerinventory_services_nodes` | Tâches en cours d'un service sur chaque nœud (recalculé à chaque import) |
| `glpi_plugin_dockerinventory_imageversions` *(lot 2)* | Service, tag et empreinte déployés, dernière version, type d'écart, source (wud ou registre), date de vérification |

## Portainer (lot 1)

- Lecture seule : uniquement des requêtes GET, en-tête `X-API-Key`. Clé chiffrée avec la clé de
  GLPI (déclarée en champ sécurisé : rechiffrée par `glpi:security:change_key`), jamais renvoyée au
  navigateur, absente de l'API REST de GLPI (`$undisclosedFields`), des journaux et des messages.
- HTTPS obligatoire, sauf case « Autoriser HTTP » ; aucune redirection suivie (la clé ne part
  jamais vers un autre serveur).
- Environnements lus : types Docker (local, agent, agent Edge). Un environnement Docker sans Swarm
  n'a aucun service ; Kubernetes et Azure sont ignorés.
- Stack Swarm → Application (Appliance) : celle déjà liée, sinon une application du même nom dans
  l'entité de la connexion, sinon une nouvelle. **C'est la seule écriture hors des tables du
  plugin.** Une application existante n'est jamais modifiée ; l'environnement (production,
  préproduction...) choisi sur la connexion n'est appliqué qu'aux applications créées. Le plugin ne
  crée jamais d'environnement (`ApplianceEnvironment`).
- Nœud → ordinateur dont le nom est le nom d'hôte complet ou court, dans l'entité de la connexion
  et ses sous-entités, s'il est unique. Aucun ordinateur n'est créé ni modifié.
- Service, stack ou nœud absent d'un environnement lu avec succès (ou d'un environnement disparu)
  → « retiré », jamais supprimé ; il redevient actif s'il réapparaît. Un environnement en erreur ne
  retire rien (import « partiel »).
- Droits : connexions réservées au droit Configuration en modification ; lecture des services par
  le droit `plugin_dockerinventory_service` (Lire), donné à l'installation aux profils qui
  administrent la configuration. Onglets affichés seulement avec ce droit.

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

- `vendor/bin/phpunit` (dans une instance GLPI où le plugin est installé) : tests unitaires
  (correspondances, adresses, client) et d'intégration (import complet, idempotence, retraits).
- Faux serveurs Python dans `tests/mock-*` : jeux de données fictifs, aucune adresse ni nom réel.
- Test dans une vraie instance GLPI 11 et 12 avant chaque version, en naviguant comme un
  utilisateur.
