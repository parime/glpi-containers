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

use GlpiPlugin\Dockerinventory\Connection;

// Liste des connexions Portainer : administrateurs de la configuration de GLPI seulement.
Session::checkRight(Connection::RIGHTNAME, UPDATE);

Html::header(Connection::getTypeName(2), '', 'config', Connection::class);
Search::show(Connection::class);
Html::footer();
