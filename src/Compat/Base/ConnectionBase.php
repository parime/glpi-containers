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

namespace GlpiPlugin\Containers\Compat\Base;

use CommonDBTM;
use GlpiPlugin\Containers\Compat\GlpiVersion;
use GlpiPlugin\Containers\Connection;

/*
 * Parent de \GlpiPlugin\Containers\Connection : redéclare $rightname et $undisclosedFields (la clé
 * d'API n'est jamais rendue par l'API REST de GLPI) sans type en GLPI 11, typés en GLPI 12 (voir
 * GlpiVersion). Les valeurs viennent des constantes de la classe enfant.
 */
if (GlpiVersion::isAtLeast12()) {
    abstract class ConnectionBase extends CommonDBTM
    {
        public static string $rightname = Connection::RIGHTNAME;
        public static array $undisclosedFields = Connection::UNDISCLOSED_FIELDS;
    }
} else {
    abstract class ConnectionBase extends CommonDBTM
    {
        public static $rightname = Connection::RIGHTNAME;
        public static $undisclosedFields = Connection::UNDISCLOSED_FIELDS;
    }
}
