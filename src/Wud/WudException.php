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
 * Échec d'un appel à WUD. Le message est un code stable (constantes ci-dessous), jamais le texte
 * de l'exception d'origine : il ne peut ainsi contenir ni le mot de passe ni une réponse
 * arbitraire du serveur.
 */
final class WudException extends \RuntimeException
{
    public const UNAUTHORIZED = 'wud_unauthorized';
    public const FORBIDDEN = 'wud_forbidden';
    public const NOT_FOUND = 'wud_not_found';
    public const SERVER_ERROR = 'wud_server_error';
    public const PRIVATE_NETWORK = 'wud_private_network';
    public const UNEXPECTED = 'wud_unexpected';
    public const UNREACHABLE = 'wud_unreachable';

    public static function fromThrowable(\Throwable $e): self
    {
        $message = $e->getMessage();
        $code = match (true) {
            // GLPI 12 : appel vers un réseau privé refusé (voir WudContext).
            str_contains($message, 'is blocked for') => self::PRIVATE_NETWORK,
            preg_match('/\b401\b/', $message) === 1 => self::UNAUTHORIZED,
            preg_match('/\b403\b/', $message) === 1 => self::FORBIDDEN,
            preg_match('/\b404\b/', $message) === 1 => self::NOT_FOUND,
            preg_match('/\b5\d\d\b/', $message) === 1 => self::SERVER_ERROR,
            default => self::UNREACHABLE,
        };

        return new self($code);
    }
}
