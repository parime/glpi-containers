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

namespace GlpiPlugin\Dockerinventory\Compat;

/**
 * GLPI 11 et GLPI 12 depuis un seul code source.
 *
 * GLPI 12 type la plupart des propriétés de ses classes de base (CommonGLPI::$rightname devient
 * `string`), GLPI 11 non. PHP exige qu'une sous-classe reprenne exactement le type du parent :
 * chaque classe concernée hérite donc d'une classe intermédiaire (Compat/Base/) déclarée selon la
 * version installée. Une classe et non un trait : en PHP 8.2 à 8.4, un trait ne peut pas
 * redéclarer une propriété héritée avec une autre valeur.
 */
final class GlpiVersion
{
    public static function isAtLeast12(): bool
    {
        // '12.0.0-dev' : inclut les préversions de GLPI 12, qui ont déjà les changements d'API.
        return defined('GLPI_VERSION') && version_compare(GLPI_VERSION, '12.0.0-dev', '>=');
    }
}
