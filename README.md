# Conteneurs pour GLPI

[🇬🇧 English](README.en.md)

Plugin GLPI qui donne une vue sur les applications en conteneurs, **en lecture seule** :

- **Portainer** : stacks et services Docker Swarm, nœuds du cluster ;
- **What's Up Docker (WUD)** : version déployée et dernière version disponible de chaque image ;
- **Harbor** : projets, dépôts, artefacts et résumé d'analyse de vulnérabilités des images.

Il répond à la question « qu'est-ce qui tourne, en quelle version, est-ce à jour, est-ce
vulnérable ? », que GLPI ne sait pas traiter seul : son onglet « Virtualisation » liste des
conteneurs isolés, sans stack, sans service, sans réplicas ni version.

> **État** : en développement, aucune version publiée. Le lot 1 (Portainer) est en cours ; WUD et
> Harbor suivent. Voir [ROADMAP.md](ROADMAP.md).

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
| WUD | Conteneurs suivis : tag et empreinte locaux, dernière version, type d'écart | Version déployée, dernière version, écart (correctif, mineure, majeure) par service |
| Registres (sans WUD) | Docker Hub et API v2 de Harbor | Même tableau des versions, calculé par le plugin |
| Harbor | Projets, dépôts, artefacts, résumé d'analyse de vulnérabilités | Vulnérabilités par image, images hors registre maîtrisé signalées |

## Compatibilité

- GLPI 11.0 et 12.x
- PHP 8.2 à 8.5 (GLPI 11), 8.3 à 8.5 (GLPI 12)
- Portainer CE ou BE (API 2.x), WUD 8.x, Harbor 2.x

Portainer CE n'a que des rôles grossiers : l'utilisateur en lecture seule y voit plus que
nécessaire. La documentation d'installation le précisera.

## Installation

Pas encore de version publiée. Pour un essai depuis les sources :

```sh
cd /var/www/glpi/plugins
git clone https://github.com/parime/glpi-containers.git containers
cd containers && composer install --no-dev
```

Puis **Configuration > Plugins**, installer et activer « Conteneurs ».

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
