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

namespace GlpiPlugin\Dockerinventory\Version;

/**
 * Écart entre la version déployée d'une image et la dernière version disponible. Fonctions pures,
 * sans GLPI.
 *
 * Avec WUD, son calcul est repris tel quel (classify()) : le plugin ne refait la comparaison que
 * si WUD ne donne pas le type d'écart. Un tag mobile (« latest », « stable »...) ou non versionné
 * n'est pas suivable de façon fiable : seule l'empreinte dit alors si l'image a changé.
 */
final class VersionComparator
{
    public const GAP_NONE = 'none';
    public const GAP_PATCH = 'patch';
    public const GAP_MINOR = 'minor';
    public const GAP_MAJOR = 'major';
    public const GAP_PRERELEASE = 'prerelease';
    public const GAP_DIGEST = 'digest';
    public const GAP_UNTRACKABLE = 'untrackable';
    public const GAP_UNKNOWN = 'unknown';

    /** Du plus grave au moins grave : ordre d'affichage et choix de la ligne d'un service. */
    public const GAPS = [
        self::GAP_MAJOR,
        self::GAP_MINOR,
        self::GAP_PATCH,
        self::GAP_PRERELEASE,
        self::GAP_DIGEST,
        self::GAP_UNTRACKABLE,
        self::GAP_UNKNOWN,
        self::GAP_NONE,
    ];

    /** Version : « v » facultatif, 1 à 4 nombres séparés par des points, suffixe facultatif. */
    private const VERSION = '/^[vV]?(\d+(?:\.\d+){0,3})(?:[-+_.]([0-9A-Za-z][0-9A-Za-z.+_-]*))?$/';

    /**
     * Le tag désigne-t-il une version (« 1.2 », « v1.2.3 », « 1.2.3-alpine », « 2024.10.1 ») ?
     * « latest », « stable », « edge », un nom de branche ou une empreinte : non.
     */
    public static function isTrackable(string $tag): bool
    {
        return self::parse($tag) !== null;
    }

    /**
     * Nombres et suffixe d'un tag versionné, null sinon.
     *
     * @return array{numbers: list<int>, suffix: string}|null
     */
    public static function parse(string $tag): ?array
    {
        $tag = trim($tag);
        if (preg_match(self::VERSION, $tag, $m) !== 1) {
            return null;
        }

        return [
            'numbers' => array_map('intval', explode('.', $m[1])),
            'suffix'  => $m[2] ?? '',
        ];
    }

    /**
     * Écart entre deux tags versionnés : majeure, mineure, correctif, préversion (seul le suffixe
     * change) ou aucun. Null si l'un des deux n'est pas versionné. Un nombre absent vaut 0
     * (« 1.2 » = « 1.2.0 »).
     */
    public static function diff(string $from, string $to): ?string
    {
        $a = self::parse($from);
        $b = self::parse($to);
        if ($a === null || $b === null) {
            return null;
        }
        $levels = [self::GAP_MAJOR, self::GAP_MINOR, self::GAP_PATCH, self::GAP_PATCH];
        $count = max(count($a['numbers']), count($b['numbers']));
        for ($i = 0; $i < $count; $i++) {
            if (($a['numbers'][$i] ?? 0) !== ($b['numbers'][$i] ?? 0)) {
                return $levels[$i];
            }
        }

        return $a['suffix'] === $b['suffix'] ? self::GAP_NONE : self::GAP_PRERELEASE;
    }

    /**
     * Écart d'un conteneur suivi par WUD (valeurs de WudMapper::mapContainer()).
     *
     * @param array{tag: string, digest: string, latest_tag: string, latest_digest: string, has_result: bool,
     *     update_available: bool|null, update_kind: string, semver_diff: string, error?: string} $container
     */
    public static function classify(array $container): string
    {
        if (!self::isTrackable($container['tag'])) {
            return self::GAP_UNTRACKABLE;
        }
        if (!$container['has_result'] || ($container['latest_tag'] === '' && $container['latest_digest'] === '')) {
            // Pas encore vérifié, ou WUD n'a pas pu interroger le registre.
            return self::GAP_UNKNOWN;
        }
        $update = $container['update_available'];
        if ($update === null) {
            // Champ absent : déduit du résultat.
            $update = ($container['latest_tag'] !== '' && $container['latest_tag'] !== $container['tag'])
                || ($container['latest_digest'] !== '' && $container['digest'] !== ''
                    && $container['latest_digest'] !== $container['digest']);
        }
        if (!$update) {
            return self::GAP_NONE;
        }
        $kind = $container['update_kind'];
        if ($kind === '') {
            $tagChanged = $container['latest_tag'] !== '' && $container['latest_tag'] !== $container['tag'];
            $kind = $tagChanged ? 'tag' : 'digest';
        }
        if ($kind === 'digest') {
            return self::GAP_DIGEST;
        }
        $known = [self::GAP_MAJOR, self::GAP_MINOR, self::GAP_PATCH, self::GAP_PRERELEASE];
        if (in_array($container['semver_diff'], $known, true)) {
            return $container['semver_diff'];
        }
        $diff = self::diff($container['tag'], $container['latest_tag']);

        return $diff === null || $diff === self::GAP_NONE ? self::GAP_UNKNOWN : $diff;
    }

    /**
     * Rang de gravité (0 = le plus grave).
     */
    public static function severity(string $gap): int
    {
        $rank = array_search($gap, self::GAPS, true);

        return $rank === false ? count(self::GAPS) : (int) $rank;
    }
}
