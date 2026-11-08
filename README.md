# Inventaire Docker pour GLPI

[🇬🇧 English](README.en.md)

Plugin GLPI qui donne une vue sur les applications en conteneurs, **en lecture seule** :

- **Portainer** : stacks et services Docker Swarm, nœuds du cluster ;
- **What's Up Docker (WUD)** : version déployée et dernière version disponible de chaque image ;
- **Harbor** : projets, dépôts, artefacts et résumé d'analyse de vulnérabilités des images.

Il répond à la question « qu'est-ce qui tourne, en quelle version, est-ce à jour, est-ce
vulnérable ? », que GLPI ne sait pas traiter seul : son onglet « Virtualisation » liste des
conteneurs isolés, sans stack, sans service, sans réplicas ni version.

> **État** : version 0.1.0 publiée avec le lot 1 (Portainer). Le cœur du lot 2 (What's Up Docker :
> versions des images) est prêt pour la prochaine version ; registres sans WUD, tickets et Harbor
> suivent. Voir [ROADMAP.md](ROADMAP.md).

## Principes

- **Lecture seule.** Le plugin ne redéploie, ne redémarre et ne supprime jamais rien. La mise à jour
  automatique de WUD n'est pas utilisée.
- **Rien n'est modifié automatiquement dans GLPI non plus.** Un service disparu est marqué
  « retiré », pas supprimé. Les écarts de version donnent lieu à un ticket proposé selon des seuils
  choisis, jamais à un changement de statut, de priorité ou d'affectation.
- **Comptes aux droits minimaux.** Utilisateur dédié en lecture seule côté Portainer, WUD et Harbor ;
  clés d'API chiffrées avec la clé de GLPI ; noms des secrets Docker importés, jamais leur contenu.
- **Données fictives** dans les tests, les captures et la documentation.

## Ce que fait le plugin

| Source | Import | Dans GLPI |
| --- | --- | --- |
| Portainer | Stacks, services (image, tag, empreinte, réplicas, ports publiés, réseaux, noms des secrets), nœuds | Stack = Application native (analyse d'impact) ; fiche Service ; nœuds rattachés aux ordinateurs inventoriés |
| WUD | Conteneurs suivis : tag et empreinte locaux, dernière version, type d'écart | Version déployée, dernière version, écart (correctif, mineure, majeure, empreinte seule, non suivable) par service ; conteneurs sans service listés à part |
| Registres (sans WUD) | Docker Hub et API v2 de Harbor | Même tableau des versions, calculé par le plugin |
| Harbor | Projets, dépôts, artefacts, résumé d'analyse de vulnérabilités | Vulnérabilités par image, images hors registre maîtrisé signalées |

## Compatibilité

- GLPI 11.0 et 12.x
- PHP 8.2 à 8.5 (GLPI 11), 8.3 à 8.5 (GLPI 12)
- Portainer CE ou BE (API 2.x), WUD 8.x, Harbor 2.x

Portainer CE n'a que des rôles grossiers : l'utilisateur en lecture seule y voit plus que
nécessaire. La documentation d'installation le précisera.

## Installation

Téléchargez l'archive `glpi-docker-inventory-X.Y.Z.zip` de la [dernière version](https://github.com/parime/glpi-docker-inventory/releases/latest) (son
empreinte SHA-256 est publiée à côté), puis décompressez-la dans le dossier `plugins` de GLPI :

```sh
cd /var/www/glpi/plugins
unzip glpi-docker-inventory-X.Y.Z.zip   # crée le dossier dockerinventory/
```

Puis **Configuration > Plugins**, installer et activer « Inventaire Docker ».

## Utilisation (lot 1 : Portainer)

1. **Dans Portainer**, créer un utilisateur dédié aux droits minimaux (lecture seule sur les
   environnements à inventorier) et un jeton d'accès pour lui (*My account > Access tokens*).
2. **Dans GLPI**, *Configuration > Connexions Docker* (ou le nom du plugin dans *Configuration >
   Plugins*) : « Ajouter », choisir **Portainer**, puis saisir l'adresse de Portainer
   (`https://portainer.exemple.test:9443`) et la clé. Choisir l'environnement (Production, Préproduction...) à donner aux applications que
   l'import créera. « Tester la connexion », puis « Importer maintenant ».
3. L'import tourne ensuite **toutes les heures** (*Configuration > Actions automatiques*,
   `portainerimport`).

Ce qui apparaît :

- *Parc > Services de conteneurs* : tous les services, avec image, tag, empreinte, réplicas, état ;
- sur chaque **Application** créée pour une stack, l'onglet « Services » ;
- sur chaque **ordinateur** dont le nom correspond à un nœud du Swarm, l'onglet « Services du nœud ».

Ce que le plugin écrit **hors de ses propres tables** : uniquement la création, une fois, de
l'Application d'une stack quand aucune application de ce nom n'existe dans l'entité de la
connexion. Une application existante, un ordinateur ou un environnement ne sont jamais modifiés ni
créés. Le droit de lecture des services se donne dans *Administration > Profils*, onglet
« Inventaire Docker » ; les connexions sont réservées au droit *Configuration* en modification.

## Utilisation (lot 2 : What's Up Docker)

[What's Up Docker](https://github.com/getwud/wud) (WUD) surveille les images des conteneurs et
connaît, pour chacune, la dernière version publiée. Le plugin lit ce résultat **sans rien modifier
dans WUD** (aucun déclencheur, aucune mise à jour) et le rattache aux services importés de
Portainer.

1. **Importer d'abord Portainer** : les conteneurs vus par WUD sont rattachés aux services Swarm
   importés (par l'empreinte de l'image, puis par le nom du service Swarm, puis par image et tag).
2. **Dans GLPI**, *Configuration > Connexions Docker* : « Ajouter », choisir **What's Up Docker**,
   saisir l'adresse de WUD (`https://wud.exemple.test:3000`) et, si WUD demande une
   authentification, l'utilisateur et le mot de passe (chiffré avec la clé de GLPI, jamais
   réaffiché). « Tester la connexion » affiche la version de WUD et le nombre de conteneurs suivis,
   puis « Lire WUD maintenant ».
3. La lecture tourne ensuite **toutes les heures** (*Configuration > Actions automatiques*,
   `wudimport`).

Ce qui apparaît :

- *Parc > Versions des images* : pour chaque service, version déployée, dernière version et écart
  (badges colorés : version majeure, mineure, correctif, empreinte seule, non suivable, à jour),
  filtres par écart et par environnement de l'application, conteneurs sans service listés à part ;
  « Recherche avancée et export » ouvre la recherche GLPI sur les mêmes colonnes ;
- sur chaque **service** et chaque **application**, l'onglet « Versions ».

Règles de lecture :

- l'écart calculé par WUD est repris tel quel ; le plugin ne le recalcule que si WUD ne le donne pas ;
- un tag mobile (`latest`, `stable`...) ou sans numéro de version est « non suivable » : seule
  l'empreinte dit si l'image a changé ;
- plusieurs conteneurs d'un même service donnent une seule ligne ; des réplicas dans des versions
  différentes (mise à jour en cours ou bloquée) sont signalés ;
- un conteneur sans service correspondant (hors Swarm, ou plusieurs services possibles) n'est pas
  ignoré : il est listé à part avec la raison.

> La correspondance avec l'API de WUD (`/api/containers`, `/api/app`) suit la documentation
> publique de WUD 8.x et un faux WUD (`tests/mock-wud`) ; elle reste à valider sur une vraie
> instance WUD 8.x ([#2](https://github.com/parime/glpi-docker-inventory/issues/2)).

Le droit de lecture des services (*Administration > Profils*, onglet « Inventaire Docker ») donne
aussi accès aux versions.

**Portainer ou WUD sur un réseau privé (GLPI 12).** GLPI 12 refuse par défaut les appels vers une
adresse privée. Pour les autoriser pour Portainer et WUD seulement, ajouter dans
`config/local_define.php` :

```php
define('GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS', [
    \GlpiPlugin\Dockerinventory\Portainer\PortainerContext::class,
    \GlpiPlugin\Dockerinventory\Wud\WudContext::class,
]);
```

## Liens avec les autres plugins

- **GRC Manager** ([glpi-iso27001-management](https://github.com/parime/glpi-iso27001-management)) :
  preuves pour les mesures A.5.9 (inventaire), A.8.8 (vulnérabilités techniques), A.8.9
  (configuration) et A.8.32 (changements) ; vulnérabilités des images Harbor dans la corrélation
  des vulnérabilités.
- **Configuration GLPI Auto** ([Configuration-glpi-auto](https://github.com/parime/Configuration-glpi-auto)) :
  étape de l'assistant pour déclarer les connexions Portainer, WUD et Harbor.

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md) : arborescence, classes, tests
- [ROADMAP.md](ROADMAP.md) : lots et issues
- [CHANGELOG.md](CHANGELOG.md)

## Licence

GPL-3.0-or-later, voir [LICENSE](LICENSE).
