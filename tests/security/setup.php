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
 * Jeu de données des contrôles de sécurité communs (parime/glpi-plugin-security), lancé avant la
 * désinstallation pour que le contrôle des restes porte sur de vraies données : une connexion,
 * un import complet depuis le jeu fictif du faux Portainer, une recherche enregistrée et des
 * préférences d'affichage personnelles sur les services. Données fictives uniquement.
 *
 * Usage (dossier de GLPI, utilisateur www-data) : php plugins/dockerinventory/tests/security/setup.php
 */

use GlpiPlugin\Dockerinventory\Connection;
use GlpiPlugin\Dockerinventory\Importer;
use GlpiPlugin\Dockerinventory\Portainer\PortainerClient;
use GlpiPlugin\Dockerinventory\Service;

require '/var/www/glpi/vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$GLOBALS['kernel'] = $kernel;
$kernel->boot();
Session::start();
if (!(new Auth())->login('glpi', 'glpi', false)) {
    fwrite(STDERR, "Connexion glpi/glpi impossible\n");
    exit(1);
}

$fixture = json_decode((string) file_get_contents(dirname(__DIR__) . '/mock-portainer/fixture.json'), true);
$connection = new Connection();
$id = (int) $connection->add([
    'name'        => 'Portainer fictif (sécurité)',
    'entities_id' => 0,
    'url'         => 'https://portainer.exemple.test:9443',
    'api_key'     => 'ptr_fictive_cle_securite',
]);
$connection->getFromDB($id);
$report = (new Importer(static fn (Connection $c): PortainerClient => new PortainerClient(
    'https://portainer.exemple.test:9443',
    'ptr_fictive_cle_securite',
    static function (string $url) use ($fixture): string {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($path === '/api/endpoints' || $path === '/api/stacks') {
            return (string) json_encode($fixture[$path === '/api/stacks' ? 'stacks' : 'endpoints']);
        }
        preg_match('#^/api/endpoints/(\d+)/docker/(\w+)$#', $path, $m);
        $endpoint = $fixture['docker'][$m[1] ?? ''] ?? ['swarm' => false];
        if (!$endpoint['swarm']) {
            throw new RuntimeException('HTTP 503 returned');
        }

        return (string) json_encode($endpoint[$m[2]]);
    }
)))->import($connection);

$userId = (int) Session::getLoginUserID();
(new SavedSearch())->add([
    'name'        => 'Services retirés (sécurité)',
    'type'        => SavedSearch::SEARCH,
    'itemtype'    => Service::class,
    'users_id'    => $userId,
    'entities_id' => 0,
    'query'       => 'criteria[0][field]=10&criteria[0][searchtype]=equals&criteria[0][value]=1',
]);
global $DB;
$DB->insert(DisplayPreference::getTable(), [
    'itemtype' => Service::class,
    'num'      => 14,
    'rank'     => 1,
    'users_id' => $userId,
] + ($DB->fieldExists(DisplayPreference::getTable(), 'interface') ? ['interface' => 'central'] : []));

echo sprintf("connexion %d, %d services importés\n", $id, $report['services']);
