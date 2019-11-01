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

class Toolbox {

    public static function formatNagiosTimestamp($timestamp) {
        return date('M d Y, h:i:s A', $timestamp / 1000);
    }

    public static function getLinkForHost($hostname) {
        $href = NagiosServer::getExtinfoUrl(). "?type=1&host=$hostname";
        return "<a target='_blank' href='$href'>$hostname</a>";
    }

    public static function getLinkForService($hostname, $servicename) {
        $href = NagiosServer::getExtinfoUrl(). "?type=2&host=$hostname&service=$servicename";
        return "<a target='_blank' href='$href'>$servicename</a>";
    }
}