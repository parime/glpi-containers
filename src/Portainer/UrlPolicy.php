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

namespace GlpiPlugin\Dockerinventory\Portainer;

/**
 * Adresse d'un serveur Portainer : HTTPS obligatoire sauf autorisation explicite de HTTP (la clé
 * d'API circulerait en clair), sans identifiants, requête ni fragment.
 */
final class UrlPolicy
{
    public const ERROR_INVALID = 'invalid';
    public const ERROR_HTTPS_REQUIRED = 'https_required';
    public const ERROR_CREDENTIALS = 'credentials';

    public static function normalize(string $url): string
    {
        return rtrim(trim($url), '/');
    }

    /**
     * @return self::ERROR_*|null Null si l'adresse est acceptée.
     */
    public static function check(string $url, bool $allowHttp): ?string
    {
        $parts = parse_url($url);
        if (
            $parts === false
            || !isset($parts['scheme'], $parts['host'])
            || $parts['host'] === ''
            || isset($parts['query'])
            || isset($parts['fragment'])
            || preg_match('/\s/', $url) === 1
        ) {
            return self::ERROR_INVALID;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return self::ERROR_CREDENTIALS;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'https') {
            return null;
        }
        if ($scheme === 'http') {
            return $allowHttp ? null : self::ERROR_HTTPS_REQUIRED;
        }

        return self::ERROR_INVALID;
    }
}
