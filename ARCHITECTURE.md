# Architecture

Plugin GLPI « Inventaire Docker » : clé `dockerinventory` (dossier `plugins/dockerinventory`), espace de
noms `GlpiPlugin\Dockerinventory`.

## Arborescence

Les éléments marqués *(prévu)* n'existent pas encore.

```text
dockerinventory/
├── setup.php                  version, compatibilité GLPI 11 et 12, hooks
├── hook.php                   installation, mise à jour, désinstallation
├── composer.json
├── front/                     pages (une par écran)
│   ├── connection.php         liste des connexions Portainer et WUD (droit Configuration en modification)
│   ├── connection.form.php    choix du type, formulaire, « Tester la connexion », « Importer maintenant »
│   ├── service.php            liste des services importés (recherche GLPI)
│   ├── service.form.php       fiche d'un service, en lecture seule
│   ├── version.php            « Versions des images » : compteurs, filtres, badges (Twig)
│   └── imageversion.php       recherche GLPI sur les versions (vues enregistrées, exports)
├── src/
│   ├── Compat/
│   │   ├── GlpiVersion.php    GLPI 11 ou 12
│   │   ├── Http.php           GET par le client HTTP de GLPI, sans redirection
│   │   └── Base/              classes parentes : propriétés typées (GLPI 12) ou non (GLPI 11)
│   ├── Install/Installer.php  tables, droit, tâche automatique, préférences d'affichage ; désinstallation
│   ├── Connection.php         connexion Portainer ou WUD (type figé à la création) : URL, clé ou mot de
│   │                          passe chiffré (GLPIKey), utilisateur WUD, environnement des applications
│   │                          créées, dernier compte rendu ; tâches automatiques horaires
│   ├── Importer.php           import Portainer : stacks, services, nœuds, tâches en cours, retraits
│   ├── ImageVersion.php       version d'image d'un service (ou d'un conteneur sans service) ; onglet
│   │                          « Versions » des services et des applications, page et recherche
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
│   ├── Wud/
│   │   ├── WudClient.php      lecture seule de /api/app et /api/containers, authentification basique
│   │   ├── WudContext.php     contexte réseau (réseaux privés autorisés sous GLPI 12)
│   │   ├── WudException.php   codes d'erreur stables, jamais le texte d'origine ni le mot de passe
│   │   ├── WudMapper.php      conteneur WUD → valeurs lues (lecture défensive), clés d'image
│   │   └── WudImporter.php    lecture d'une connexion WUD → table des versions
│   ├── Version/
│   │   ├── VersionComparator.php  écart : majeure, mineure, correctif, préversion, empreinte seule,
│   │   │                          non suivable (`latest`...), non vérifié ; calcul de WUD repris
│   │   └── VersionLinker.php      conteneurs → services (empreinte, nom, image et tag), une ligne
│   │                              par service, réplicas désynchronisés
│   ├── Registry/              repli sans WUD : Docker Hub, API v2 de Harbor    (prévu, #5)
│   ├── Ticket/                tickets de mise à jour selon des seuils           (prévu, #7)
│   └── Harbor/                projets, dépôts, artefacts, analyses           (prévu, lot 3)
├── templates/                 Twig uniquement (aucun HTML dans le PHP)
├── locales/                   fr_FR, en_GB, de_DE, es_ES, it_IT, pl_PL, pt_BR (+ variantes régionales)
└── tests/
    ├── Unit/                  sans base de données
    ├── Integration/           import complet contre la vraie base GLPI (transport en mémoire)
    ├── security/setup.php     jeu de données des contrôles de sécurité communs
    ├── mock-portainer/        faux Portainer (Python) et jeu de données fictif
    └── mock-wud/              faux WUD (Python, format WUD 8.x) et jeu de données fictif
```

## Tables

| Table | Contenu |
| --- | --- |
| `glpi_plugin_dockerinventory_connections` | Connexion Portainer ou WUD (`type`) : URL, clé ou mot de passe chiffré, utilisateur (WUD), HTTP autorisé ou non, entité, environnement des applications créées (Portainer), dernier import et son compte rendu |
| `glpi_plugin_dockerinventory_stacks` | Stack Swarm de Portainer ↔ Application GLPI, état « retiré » |
| `glpi_plugin_dockerinventory_services` | Service Swarm : stack, Application, image, tag, empreinte, réplicas voulus et en cours, ports, réseaux, noms des secrets, état « retiré » |
| `glpi_plugin_dockerinventory_nodes` | Nœud Swarm : nom d'hôte, rôle, état, version de Docker, ordinateur GLPI rattaché |
| `glpi_plugin_dockerinventory_services_nodes` | Tâches en cours d'un service sur chaque nœud (recalculé à chaque import) |
| `glpi_plugin_dockerinventory_imageversions` | Une ligne par service suivi (ou par conteneur sans service, `plugin_dockerinventory_services_id` = 0) et par connexion WUD : conteneurs, image et registre, tag et empreinte déployés, dernière version, écart, réplicas désynchronisés, méthode de rattachement (ou raison de l'absence), source, date de vérification. Recalculée à chaque lecture de WUD |

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

- Lecture seule de l'API de WUD : `GET /api/app` (version, affichée au test et sur la connexion) et
  `GET /api/containers` ; les déclencheurs de WUD ne sont jamais appelés. Authentification basique
  facultative, mot de passe chiffré dans la colonne `api_key` (champ sécurisé), jamais réaffiché.
- Champs lus par conteneur (documentation publique de WUD 8.x, **à valider sur une vraie instance**,
  #2) : `id`, `name`, `watcher`, `image.{registry.{name, url}, name, tag.{value, semver},
  digest.{watch, value, repo}}`, `result.{tag, digest}`, `updateAvailable`, `updateKind.{kind,
  semverDiff, localValue, remoteValue}`, `labels`, `error.message`. Champ absent toléré, champ
  inconnu ignoré.
- Écart : celui de WUD (`updateKind`) est repris tel quel ; le plugin ne le calcule que si WUD ne le
  donne pas. Tag mobile (`latest`, `stable`...) ou non versionné : « non suivable », quelle que soit
  l'empreinte. Pas encore de résultat (ou erreur de registre) : « non vérifié ».
- Rattachement au service (services actifs de l'entité de la connexion WUD et de ses sous-entités) :
  par l'empreinte de l'image (celle que Swarm épingle), puis par le nom du service Swarm
  (`com.docker.swarm.service.name`, ou nom de tâche `service.emplacement.tâche`), puis par image et
  tag. À chaque étape, plusieurs services possibles sont départagés par les critères suivants ;
  s'il en reste plusieurs, le conteneur est listé « plusieurs services possibles » plutôt que
  rattaché au mauvais. Conteneurs Compose : « hors Swarm ».
- Une ligne par service : le conteneur qui correspond au service (même empreinte, sinon même tag)
  donne les valeurs ; des conteneurs dans des versions différentes sont signalés « réplicas
  désynchronisés » avec le détail.
- WUD injoignable ou en erreur : rien n'est modifié dans la table des versions.
- Sans WUD : le plugin interrogera lui-même Docker Hub ou l'API v2 de Harbor (prévu, #5).

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
