# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions
[SemVer](https://semver.org/lang/fr/).

## [Unreleased]

## [0.1.0] - 2026-11-08

### Added

- Lot 1 : import Portainer en lecture seule ([#1](https://github.com/parime/glpi-docker-inventory/issues/1)).
  - Connexions Portainer (Configuration > Connexions Portainer) : clé d'API chiffrée avec la clé de
    GLPI et jamais réaffichée, HTTPS obligatoire sauf autorisation explicite, bouton « Tester la
    connexion ».
  - Import toutes les heures (tâche automatique) et bouton « Importer maintenant » : stacks Swarm en
    Applications GLPI (avec l'environnement choisi, seulement à la création), services (image, tag,
    empreinte, réplicas voulus et en cours, ports publiés, réseaux, noms des secrets), nœuds
    rattachés aux ordinateurs par nom d'hôte.
  - Service, stack ou nœud disparu marqué « retiré », jamais supprimé.
  - Liste des services (Parc > Services de conteneurs), fiche en lecture seule, onglet « Services »
    des applications, onglet « Services du nœud » des ordinateurs, droit de lecture dans les profils.
  - Traductions : français, anglais, allemand, espagnol, italien, portugais (Brésil), polonais.
  - CI : CI officielle GLPI 11, installation et tests sur GLPI 12, chargement des classes sur PHP
    8.2 à 8.5, traductions complètes, contrôles de sécurité communs.
- Chaîne de publication : tag automatique à chaque nouvelle version de `setup.php`, archive
  `glpi-docker-inventory-X.Y.Z.zip` testée sur une vraie instance GLPI, somme SHA-256, attestation
  de provenance et PR de mise à jour du catalogue de la marketplace (`dockerinventory.xml`).
- Contrôles de sécurité communs v1.6.1 (actionlint 1.7.12).
- Documentation du dépôt : README (français, anglais), ARCHITECTURE, ROADMAP, modèles d'issues et
  de PR.
- Nom du plugin : « Inventaire Docker » (dépôt glpi-docker-inventory, clé dockerinventory), plus parlant
  que « containers ».
