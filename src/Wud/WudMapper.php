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

namespace GlpiPlugin\Dockerinventory\Wud;

/**
 * Traduction d'un conteneur renvoyé par GET /api/containers de WUD en valeurs à rattacher et à
 * enregistrer. Fonctions pures, sans GLPI : testées sans base de données.
 *
 * Champs lus (documentation publique de WUD 8.x ; correspondance à confirmer sur une vraie
 * instance) : id, name, watcher, image.{registry.{name, url}, name, tag.{value, semver},
 * digest.{watch, value, repo}}, result.{tag, digest}, updateAvailable, updateKind.{kind,
 * semverDiff, localValue, remoteValue}, labels, error.message. Lecture défensive : un champ absent
 * donne une valeur vide, un champ inconnu est ignoré.
 *
 * `method` (rattachement) et `reason` (non rattaché) sont ajoutés par VersionLinker.
 *
 * @phpstan-type WudContainer array{uid: string, name: string, watcher: string, registry_name: string,
 *     registry_url: string, image: string, image_key: string, tag: string, semver: bool|null, digest: string,
 *     latest_tag: string, latest_digest: string, has_result: bool, update_available: bool|null,
 *     update_kind: string, semver_diff: string, service_name: string, is_swarm: bool|null, error: string,
 *     method?: string, reason?: string}
 */
final class WudMapper
{
    /** Étiquette posée par Docker Swarm sur chaque conteneur d'un service. */
    public const SERVICE_LABEL = 'com.docker.swarm.service.name';

    /** Étiquette posée par Docker Compose : conteneur hors Swarm. */
    public const COMPOSE_LABEL = 'com.docker.compose.project';

    /** Noms du registre Docker Hub, ramenés à « docker.io ». */
    public const DOCKER_HUB_HOSTS = [
        'docker.io',
        'index.docker.io',
        'registry-1.docker.io',
        'registry.hub.docker.com',
    ];

    /**
     * @param array<string, mixed> $container
     * @return WudContainer|null Null sans identifiant ni nom.
     */
    public static function mapContainer(array $container): ?array
    {
        $uid = self::str($container['id'] ?? null);
        $name = ltrim(self::str($container['name'] ?? null), '/');
        if ($uid === '' && $name === '') {
            return null;
        }
        $image = self::arr($container['image'] ?? null);
        $registry = self::arr($image['registry'] ?? null);
        $tag = self::arr($image['tag'] ?? null);
        $digest = self::arr($image['digest'] ?? null);
        $result = $container['result'] ?? null;
        $result = is_array($result) ? $result : null;
        $kind = self::arr($container['updateKind'] ?? null);
        $labels = self::arr($container['labels'] ?? null);
        $error = self::arr($container['error'] ?? null);

        $registryName = self::str($registry['name'] ?? null);
        $registryUrl = self::str($registry['url'] ?? null);
        $imageName = self::str($image['name'] ?? null);
        $semver = $tag['semver'] ?? null;
        $update = $container['updateAvailable'] ?? null;
        $kindName = strtolower(self::str($kind['kind'] ?? null));
        $serviceName = self::str($labels[self::SERVICE_LABEL] ?? null);
        $fromTaskName = self::serviceFromTaskName($name);

        if ($serviceName !== '' || isset($labels['com.docker.swarm.task.id'])) {
            $isSwarm = true;
        } elseif (isset($labels[self::COMPOSE_LABEL])) {
            $isSwarm = false;
        } elseif ($fromTaskName !== '') {
            $isSwarm = true;
        } else {
            // Ni étiquettes ni nom de tâche Swarm : on ne sait pas.
            $isSwarm = $labels === [] ? null : false;
        }

        return [
            'uid'              => $uid !== '' ? $uid : $name,
            'name'             => $name !== '' ? $name : $uid,
            'watcher'          => self::str($container['watcher'] ?? null),
            'registry_name'    => $registryName,
            'registry_url'     => $registryUrl,
            'image'            => $imageName,
            'image_key'        => self::wudImageKey($registryName, $registryUrl, $imageName),
            'tag'              => self::str($tag['value'] ?? null),
            'semver'           => is_bool($semver) ? $semver : null,
            // Empreinte du dépôt (celle que Swarm épingle dans le service), sinon celle de l'image.
            'digest'           => self::str($digest['repo'] ?? null) ?: self::str($digest['value'] ?? null),
            'latest_tag'       => self::str($result['tag'] ?? null),
            'latest_digest'    => self::str($result['digest'] ?? null),
            'has_result'       => $result !== null,
            'update_available' => is_bool($update) ? $update : null,
            'update_kind'      => in_array($kindName, ['tag', 'digest'], true) ? $kindName : '',
            'semver_diff'      => strtolower(self::str($kind['semverDiff'] ?? null)),
            'service_name'     => $serviceName !== '' ? $serviceName : $fromTaskName,
            'is_swarm'         => $isSwarm,
            'error'            => self::str($error['message'] ?? null),
        ];
    }

    /**
     * Nom du service d'après le nom d'un conteneur de tâche Swarm : « service.emplacement.tâche »
     * (réplique) ou « service.nœud.tâche » (service global). Chaîne vide sinon.
     */
    public static function serviceFromTaskName(string $name): string
    {
        if (preg_match('/^([A-Za-z0-9][A-Za-z0-9_.-]*)\.([A-Za-z0-9-]+)\.([A-Za-z0-9]+)$/', $name, $m) !== 1) {
            return '';
        }

        return $m[1];
    }

    /**
     * Clé de comparaison d'une image Docker décrite par une référence (« postgres »,
     * « registry.exemple.test:5000/boutique/api ») : « hôte/chemin », Docker Hub ramené à
     * « docker.io/library/... » pour les images officielles.
     */
    public static function referenceKey(string $image): string
    {
        $image = strtolower(trim($image));
        if ($image === '') {
            return '';
        }
        $host = 'docker.io';
        $slash = strpos($image, '/');
        if ($slash !== false) {
            $first = substr($image, 0, $slash);
            if (str_contains($first, '.') || str_contains($first, ':') || $first === 'localhost') {
                $host = $first;
                $image = substr($image, $slash + 1);
            }
        }

        return self::key($host, $image);
    }

    /**
     * Clé de comparaison d'une image vue par WUD : hôte tiré de l'adresse du registre (ou Docker Hub
     * pour le registre « hub »), chemin donné par WUD. Sans hôte connu : chemin seul.
     */
    public static function wudImageKey(string $registryName, string $registryUrl, string $imageName): string
    {
        $imageName = strtolower(trim($imageName));
        if ($imageName === '') {
            return '';
        }
        if (self::referenceKey($imageName) !== self::key('docker.io', $imageName)) {
            // WUD a donné une référence complète, avec l'hôte du registre.
            return self::referenceKey($imageName);
        }
        $host = '';
        $parts = parse_url($registryUrl);
        if (is_array($parts) && isset($parts['host'])) {
            $host = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        } elseif (in_array(strtolower($registryName), ['hub', 'docker', 'dockerhub'], true)) {
            $host = 'docker.io';
        }

        return $host === '' ? $imageName : self::key($host, $imageName);
    }

    /**
     * Même image ? Si l'une des clés n'a pas d'hôte (registre inconnu de WUD), seul le chemin compte.
     */
    public static function sameImage(string $keyA, string $keyB): bool
    {
        if ($keyA === '' || $keyB === '') {
            return false;
        }
        if ($keyA === $keyB) {
            return true;
        }
        $pathA = self::path($keyA);
        $pathB = self::path($keyB);
        $hostless = $pathA === $keyA || $pathB === $keyB;

        return $hostless && self::stripLibrary($pathA) === self::stripLibrary($pathB);
    }

    private static function key(string $host, string $path): string
    {
        if (in_array($host, self::DOCKER_HUB_HOSTS, true)) {
            $host = 'docker.io';
            if (!str_contains($path, '/')) {
                $path = 'library/' . $path;
            }
        }

        return $host . '/' . $path;
    }

    /**
     * Chemin d'une clé « hôte/chemin » ; la clé elle-même si elle n'a pas d'hôte.
     */
    private static function path(string $key): string
    {
        $slash = strpos($key, '/');
        if ($slash === false) {
            return $key;
        }
        $first = substr($key, 0, $slash);

        return str_contains($first, '.') || str_contains($first, ':') || $first === 'localhost'
            ? substr($key, $slash + 1)
            : $key;
    }

    private static function stripLibrary(string $path): string
    {
        return str_starts_with($path, 'library/') ? substr($path, 8) : $path;
    }

    /**
     * @return array<mixed>
     */
    private static function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function str(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
