<?php

/**
 * -------------------------------------------------------------------------
 * Containers plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-containers
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

namespace GlpiPlugin\Containers\Portainer;

/**
 * Échec d'un appel à Portainer. Le message est un code stable (constantes ci-dessous), jamais le
 * texte de l'exception d'origine : il ne peut ainsi contenir ni la clé d'API ni une réponse
 * arbitraire du serveur.
 */
final class PortainerException extends \RuntimeException
{
    public const UNAUTHORIZED = 'unauthorized';
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const NOT_SWARM = 'not_swarm';
    public const PRIVATE_NETWORK = 'private_network';
    public const UNEXPECTED = 'unexpected';
    public const UNREACHABLE = 'unreachable';

    public static function fromThrowable(\Throwable $e): self
    {
        $message = $e->getMessage();
        $code = match (true) {
            // GLPI 12 : appel vers un réseau privé refusé (voir PortainerContext).
            str_contains($message, 'is blocked for') => self::PRIVATE_NETWORK,
            preg_match('/\b401\b/', $message) === 1 => self::UNAUTHORIZED,
            preg_match('/\b403\b/', $message) === 1 => self::FORBIDDEN,
            preg_match('/\b404\b/', $message) === 1 => self::NOT_FOUND,
            // Docker répond 503 « This node is not a swarm manager » hors Swarm.
            preg_match('/\b503\b/', $message) === 1 => self::NOT_SWARM,
            default => self::UNREACHABLE,
        };

        return new self($code);
    }
}
