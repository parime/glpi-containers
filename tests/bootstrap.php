<?php

/**
 * -------------------------------------------------------------------------
 * Docker Inventory plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-docker-inventory
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

/**
 * Amorçage PHPUnit dans une vraie instance GLPI (plugin installé et activé) : CI officielle GLPI
 * (glpi-project/plugin-ci-workflows), job GLPI 12, ou instance de développement. Même recette que
 * le plugin jumeau Configuration-glpi-auto : noyau GLPI démarré comme bin/console, `$kernel` rendu
 * global (le code historique de GLPI fait `global $kernel`), puis session du super-administrateur.
 */

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

if (!defined('TU_USER')) {
    define('TU_USER', 'glpi');
}

$kernel = new \Glpi\Kernel\Kernel();
$GLOBALS['kernel'] = $kernel;
$kernel->boot();

require_once dirname(__DIR__) . '/vendor/autoload.php';

Session::start();
if (!(new Auth())->login('glpi', 'glpi', false)) {
    throw new RuntimeException('Connexion du compte « glpi » impossible pour les tests.');
}
