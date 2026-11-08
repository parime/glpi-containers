# Feuille de route

Conception d'origine : [glpi-iso27001-management#179](https://github.com/parime/glpi-iso27001-management/issues/179).
Chaque lot fini et testé part en version, sans attendre le suivant.

## Lot 1 : Portainer (en cours)

- [#1](https://github.com/parime/glpi-containers/issues/1) Import Portainer : stacks en Applications,
  services, nœuds rattachés aux ordinateurs, services retirés.

## Lot 2 : WUD et registres

Objectif : pour chaque service, version déployée, dernière version disponible et écart.

| Issue | Sujet |
| --- | --- |
| [#2](https://github.com/parime/glpi-containers/issues/2) | Connexion WUD et lecture de `/api/containers` |
| [#3](https://github.com/parime/glpi-containers/issues/3) | Rattachement des conteneurs WUD aux services Swarm |
| [#4](https://github.com/parime/glpi-containers/issues/4) | Comparaison des versions (correctif, mineure, majeure, `latest` non suivable) |
| [#5](https://github.com/parime/glpi-containers/issues/5) | Repli sans WUD : Docker Hub et API v2 de Harbor |
| [#6](https://github.com/parime/glpi-containers/issues/6) | Tableau des versions : onglet du service et vue d'ensemble |
| [#7](https://github.com/parime/glpi-containers/issues/7) | Tickets de mise à jour selon des seuils |
| [#8](https://github.com/parime/glpi-containers/issues/8) | Faux WUD et jeu de données fictif |

Ordre conseillé : #8 et #2 (on peut tester), puis #3 et #4, puis #6, enfin #5 et #7.

## Lot 3 : Harbor

- [#9](https://github.com/parime/glpi-containers/issues/9) Projets, artefacts, analyses de
  vulnérabilités, lien avec la corrélation des vulnérabilités de grcmanager.

## Intégration

- [#10](https://github.com/parime/glpi-containers/issues/10) Preuves grcmanager (A.5.9, A.8.8, A.8.9,
  A.8.32) et étape de l'assistant de Configuration GLPI Auto.

## Hors périmètre

- Redéployer, redémarrer ou mettre à jour un service : le plugin reste en lecture seule.
- Kubernetes : à étudier s'il y a une demande, avec une source dédiée.
