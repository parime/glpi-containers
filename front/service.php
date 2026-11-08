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

use GlpiPlugin\Containers\Service;

// Liste des services : droit de lecture du plugin ; la recherche filtre par entités actives.
Session::checkRight(Service::RIGHTNAME, READ);

Html::header(Service::getTypeName(2), '', 'assets', Service::class);
Search::show(Service::class);
Html::footer();
