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

declare(strict_types=1);

namespace GlpiPlugin\Dockerinventory\Registry;

use GlpiPlugin\Dockerinventory\Wud\WudMapper;

/**
 * Registre d'une image de service : Docker Hub, l'un des Harbor déclarés (par l'hôte du registre
 * dans la référence de l'image), ou inconnu. Fonctions pures, sans GLPI.
 *
 * @phpstan-type HarborTarget array{id: int, host: string, entities: list<int>}
 * @phpstan-type Resolved array{kind: string, host: string, repository: string, connection_id: int}
 */
final class RegistryResolver
{
    public const KIND_UNKNOWN = 'unknown';

    /**
     * @param list<HarborTarget> $harbors Connexions Harbor actives : hôte du registre, entités couvertes.
     * @return Resolved
     */
    public static function resolve(string $image, int $entityId, array $harbors): array
    {
        $key = WudMapper::referenceKey($image);
        $slash = strpos($key, '/');
        $host = $slash === false ? '' : substr($key, 0, $slash);
        $repository = $slash === false ? $key : substr($key, $slash + 1);
        if ($host === 'docker.io') {
            return [
                'kind'          => RegistryClient::KIND_DOCKER_HUB,
                'host'          => $host,
                'repository'    => $repository,
                'connection_id' => 0,
            ];
        }
        foreach ($harbors as $harbor) {
            if ($harbor['host'] === $host && in_array($entityId, $harbor['entities'], true)) {
                return [
                    'kind'          => RegistryClient::KIND_HARBOR,
                    'host'          => $host,
                    'repository'    => $repository,
                    'connection_id' => $harbor['id'],
                ];
            }
        }

        return ['kind' => self::KIND_UNKNOWN, 'host' => $host, 'repository' => $repository, 'connection_id' => 0];
    }

    /**
     * Hôte du registre tel qu'il apparaît dans les images (« harbor.exemple.test »,
     * « registry.exemple.test:5000 ») : celui saisi sur la connexion, sinon celui de son adresse.
     */
    public static function harborHost(string $url, string $registryHost): string
    {
        $registryHost = strtolower(trim($registryHost));
        if ($registryHost !== '') {
            $parsed = parse_url(str_contains($registryHost, '://') ? $registryHost : 'https://' . $registryHost);
        } else {
            $parsed = parse_url($url);
        }
        if (!is_array($parsed) || !isset($parsed['host'])) {
            return '';
        }

        return strtolower($parsed['host']) . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    }
}
