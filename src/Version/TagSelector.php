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
 * Dernière version d'une image parmi les tags d'un registre, quand WUD ne la donne pas. Fonctions
 * pures, sans GLPI.
 *
 * Règles (proches de celles de WUD) :
 * - seuls les tags versionnés comptent (« latest », « stable », un nom de branche : jamais) ;
 * - même nombre de composantes que le tag déployé (« 16 » suit « 17 », pas « 16.4 ») ;
 * - même variante (« 1.2.3-alpine » ne suit que les « -alpine ») ;
 * - préversions (« -rc1 », « -beta2 »...) ignorées, sauf si une préversion est déployée : elle
 *   suit alors les versions finales et les préversions du même canal (« rc2 » après « rc1 »).
 */
final class TagSelector
{
    private const PRERELEASE = '/^(alpha|a|beta|b|rc|cr|pre|preview|dev|snapshot|nightly|canary|next|milestone|m|ea)$/';

    /**
     * Écart entre le tag déployé et les tags du registre.
     *
     * @param list<array{name: string, digest: string}> $tags Tags du registre (empreinte facultative).
     * @return array{latest_tag: string, latest_digest: string, gap: string, update_available: int}
     */
    public static function evaluate(string $deployedTag, string $deployedDigest, array $tags): array
    {
        $digests = [];
        foreach ($tags as $tag) {
            $digests[$tag['name']] = $tag['digest'];
        }
        $sameTagDigest = $digests[$deployedTag] ?? '';
        $digestChanged = $sameTagDigest !== '' && $deployedDigest !== '' && $sameTagDigest !== $deployedDigest;

        if (!VersionComparator::isTrackable($deployedTag)) {
            // Tag mobile : seule l'empreinte du même tag dit si l'image a changé.
            return [
                'latest_tag'       => $digestChanged ? $deployedTag : '',
                'latest_digest'    => $sameTagDigest,
                'gap'              => VersionComparator::GAP_UNTRACKABLE,
                'update_available' => (int) $digestChanged,
            ];
        }
        if ($tags === []) {
            return [
                'latest_tag'       => '',
                'latest_digest'    => '',
                'gap'              => VersionComparator::GAP_UNKNOWN,
                'update_available' => 0,
            ];
        }
        $latest = self::latest($deployedTag, array_keys($digests)) ?? $deployedTag;
        $gap = VersionComparator::diff($deployedTag, $latest) ?? VersionComparator::GAP_UNKNOWN;
        if ($gap === VersionComparator::GAP_NONE && $digestChanged) {
            // Même tag, image reconstruite (Docker Hub donne l'empreinte de chaque tag).
            $gap = VersionComparator::GAP_DIGEST;
        }

        return [
            'latest_tag'       => $latest,
            'latest_digest'    => $digests[$latest] ?? '',
            'gap'              => $gap,
            'update_available' => (int) !in_array(
                $gap,
                [VersionComparator::GAP_NONE, VersionComparator::GAP_UNKNOWN],
                true
            ),
        ];
    }

    /**
     * Tag le plus récent comparable au tag déployé (le tag déployé lui-même s'il est le plus
     * récent). Null si le tag déployé n'est pas versionné.
     *
     * @param list<string> $tags
     */
    public static function latest(string $deployed, array $tags): ?string
    {
        $base = VersionComparator::parse($deployed);
        if ($base === null) {
            return null;
        }
        $best = $deployed;
        $bestParsed = $base;
        foreach ($tags as $tag) {
            $tag = trim((string) $tag);
            $parsed = VersionComparator::parse($tag);
            if ($parsed === null || !self::accepts($base, $parsed)) {
                continue;
            }
            $cmp = self::compareParsed($parsed, $bestParsed);
            $samePrefix = self::hasV($tag) === self::hasV($deployed);
            if ($cmp > 0 || ($cmp === 0 && $samePrefix && self::hasV($best) !== self::hasV($deployed))) {
                $best = $tag;
                $bestParsed = $parsed;
            }
        }

        return $best;
    }

    /**
     * Comparaison de deux tags versionnés (négatif, zéro, positif) ; 0 si l'un ne l'est pas.
     */
    public static function compare(string $a, string $b): int
    {
        $pa = VersionComparator::parse($a);
        $pb = VersionComparator::parse($b);

        return $pa === null || $pb === null ? 0 : self::compareParsed($pa, $pb);
    }

    /**
     * Variante d'un suffixe, sans chiffres ni séparateurs : « alpine3.19 » => « alpine »,
     * « rc.1 » => « rc », « 1 » (révision) => chaîne vide.
     */
    public static function family(string $suffix): string
    {
        return strtolower((string) preg_replace('/[\d._+-]+/', '', $suffix));
    }

    public static function isPrerelease(string $suffix): bool
    {
        $family = self::family($suffix);

        return $family !== '' && preg_match(self::PRERELEASE, $family) === 1;
    }

    /**
     * @param array{numbers: list<int>, suffix: string} $base Tag déployé.
     * @param array{numbers: list<int>, suffix: string} $candidate
     */
    private static function accepts(array $base, array $candidate): bool
    {
        if (count($candidate['numbers']) !== count($base['numbers'])) {
            return false;
        }
        $baseFamily = self::family($base['suffix']);
        $family = self::family($candidate['suffix']);
        if ($baseFamily === '' || self::isPrerelease($base['suffix'])) {
            // Version finale déployée (ou préversion) : versions finales, et préversions du même
            // canal seulement si une préversion est déployée.
            return $family === '' || (self::isPrerelease($base['suffix']) && $family === $baseFamily);
        }

        // Variante (alpine, bookworm...) : la même seulement.
        return $family === $baseFamily;
    }

    /**
     * @param array{numbers: list<int>, suffix: string} $a
     * @param array{numbers: list<int>, suffix: string} $b
     */
    private static function compareParsed(array $a, array $b): int
    {
        $count = max(count($a['numbers']), count($b['numbers']));
        for ($i = 0; $i < $count; $i++) {
            $cmp = ($a['numbers'][$i] ?? 0) <=> ($b['numbers'][$i] ?? 0);
            if ($cmp !== 0) {
                return $cmp;
            }
        }
        // Mêmes nombres : la version finale passe avant ses préversions.
        $preA = self::isPrerelease($a['suffix']);
        $preB = self::isPrerelease($b['suffix']);
        if ($preA !== $preB) {
            return $preA ? -1 : 1;
        }

        return strnatcasecmp($a['suffix'], $b['suffix']) <=> 0;
    }

    private static function hasV(string $tag): bool
    {
        return $tag !== '' && ($tag[0] === 'v' || $tag[0] === 'V');
    }
}
