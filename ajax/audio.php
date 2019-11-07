<?php
/*
 -------------------------------------------------------------------------
 Nagios Tactical Dashboard
 Copyright (C) 2019 by Curtis Conard
 https://github.com/cconard96/nagios-tactical-dashboard
 -------------------------------------------------------------------------
 LICENSE
 This file is part of Nagios Tactical Dashboard.
 Nagios Tactical Dashboard is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 Nagios Tactical Dashboard is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 You should have received a copy of the GNU General Public License
 along with Nagios Tactical Dashboard. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

include("../inc/includes.php");

// Cache sounds for an hour
header("Cache-Control: max-age=3600");
header("Content-Type: audio/mpeg, audio/x-mpeg, audio/x-mpeg-3, audio/mpeg3", true);

$sound_name = preg_replace("/[^A-Za-z0-9 ]/", '', $_GET['sound']);
$filepath = '../sounds/' . $sound_name . '.mp3';

if (!file_exists($filepath)) {
    header("Cache-Control: no-store, no-cache, must-revalidate", true);
    return;
}

header('Content-length: ' . filesize($filepath));
echo readfile($filepath);
