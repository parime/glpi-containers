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
use GlpiPlugin\Dockerinventory\Service;

/*
 * Parent de \GlpiPlugin\Dockerinventory\Service : redéclare $rightname sans type en GLPI 11, typé en
 * GLPI 12 (voir GlpiVersion). La valeur vient de la constante de la classe enfant.
 */
if (GlpiVersion::isAtLeast12()) {
    abstract class ServiceBase extends CommonDBTM
    {
        public static string $rightname = Service::RIGHTNAME;
    }
} else {
    abstract class ServiceBase extends CommonDBTM
    {
        public static $rightname = Service::RIGHTNAME;
    }
}
