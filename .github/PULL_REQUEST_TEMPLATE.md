[🇫🇷 Français](#-français) · [🇬🇧 English](#-english)

## 🇫🇷 Français

### Résumé

<!-- Quoi, et surtout pourquoi. Issue liée : #… -->

### Vérifications

- [ ] Testé dans une vraie instance GLPI 11 et 12 (Docker), en naviguant comme un utilisateur
- [ ] Testé contre le faux serveur de la source (`tests/mock-*`), données fictives uniquement
- [ ] Aucun appel d'écriture vers Portainer, WUD, Harbor ou un registre
- [ ] `vendor/bin/phpstan analyse --no-progress` sans erreur
- [ ] `vendor/bin/phpcs` sans erreur
- [ ] `vendor/bin/phpunit` vert
- [ ] Traductions ajoutées dans toutes les langues
- [ ] `CHANGELOG.md` et, si pertinent, `ROADMAP.md` mis à jour

---

## 🇬🇧 English

### Summary

<!-- What, and mainly why. Linked issue: #… -->

### Checklist

- [ ] Tested in a real GLPI 11 and 12 instance (Docker), browsing as a user
- [ ] Tested against the source's mock server (`tests/mock-*`), fictitious data only
- [ ] No write call to Portainer, WUD, Harbor or a registry
- [ ] `vendor/bin/phpstan analyse --no-progress` clean
- [ ] `vendor/bin/phpcs` clean
- [ ] `vendor/bin/phpunit` green
- [ ] Translations added in every language
- [ ] `CHANGELOG.md` and, if relevant, `ROADMAP.md` updated
