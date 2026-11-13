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

/**
 * Échec d'un appel à un registre (Docker Hub, Harbor). Le message est un code stable (constantes
 * ci-dessous), jamais le texte de l'exception d'origine : il ne peut contenir ni le mot de passe
 * du compte robot ni une réponse arbitraire du serveur.
 */
final class RegistryException extends \RuntimeException
{
    public const UNAUTHORIZED = 'registry_unauthorized';
    public const FORBIDDEN = 'registry_forbidden';
    public const NOT_FOUND = 'registry_not_found';
    public const RATE_LIMITED = 'registry_rate_limited';
    public const SERVER_ERROR = 'registry_server_error';
    public const PRIVATE_NETWORK = 'registry_private_network';
    public const UNEXPECTED = 'registry_unexpected';
    public const UNREACHABLE = 'registry_unreachable';

    /** Pas une erreur d'appel : registre de l'image inconnu du plugin (ni Docker Hub ni Harbor). */
    public const UNKNOWN_REGISTRY = 'registry_unknown';

    /** Pas une erreur d'appel : registre privé (401) sans identifiants sur la connexion Harbor. */
    public const NO_CREDENTIALS = 'registry_no_credentials';

    /** Pas une erreur d'appel : Docker Hub non interrogé (limite atteinte ou injoignable). */
    public const SKIPPED = 'registry_skipped';

    public static function fromThrowable(\Throwable $e): self
    {
        $message = $e->getMessage();
        $code = match (true) {
            // GLPI 12 : appel vers un réseau privé refusé (voir RegistryContext).
            str_contains($message, 'is blocked for') => self::PRIVATE_NETWORK,
            preg_match('/\b401\b/', $message) === 1 => self::UNAUTHORIZED,
            preg_match('/\b403\b/', $message) === 1 => self::FORBIDDEN,
            preg_match('/\b404\b/', $message) === 1 => self::NOT_FOUND,
            preg_match('/\b429\b/', $message) === 1 => self::RATE_LIMITED,
            preg_match('/\b5\d\d\b/', $message) === 1 => self::SERVER_ERROR,
            default => self::UNREACHABLE,
        };

        return new self($code);
    }
}
