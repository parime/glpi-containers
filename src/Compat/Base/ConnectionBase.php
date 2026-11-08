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

namespace GlpiPlugin\Dockerinventory\Compat\Base;

use CommonDBTM;
use GlpiPlugin\Dockerinventory\Compat\GlpiVersion;
use GlpiPlugin\Dockerinventory\Connection;

/*
 * Parent de \GlpiPlugin\Dockerinventory\Connection : redéclare $rightname et $undisclosedFields (la clé
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
