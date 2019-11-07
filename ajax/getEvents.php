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

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Content-Type: application/json; charset=UTF-8", true);

if (!extension_loaded('apcu')) {
    // Custom HTTP code indicating missing APCu extension
    http_response_code(555);
    return;
}

NagiosServer::init();
Client::init();

$eventlist = NagiosServer::getEvents();
$warn_data = [];
$critical_data = [];
$x_labels = [];

// Fill days from -Client::$config['historical_days'] to today
for ($i = 0; $i < NagiosServer::getSafeConfig()['historical_days']; $i++) {
    $x_labels[] = date('M dS', strtotime("-{$i} days"));
    $warn_data[$i] = 0;
    $critical_data[$i] = 0;
}

foreach ($eventlist as $event) {
    $relative_day = abs(time() - ($event['timestamp'] / 1000));
    $relative_day = intval($relative_day / 86400);

    $critical_states = [NagiosServer::ALERT_HOST_UNREACHABLE, NagiosServer::ALERT_HOST_DOWN, NagiosServer::ALERT_SERVICE_CRITICAL];
    $warn_states = [NagiosServer::ALERT_SERVICE_WARNING];

    $type = in_array($event['state'], $critical_states) ? 1 : (in_array($event['state'], $warn_states) ? 0 : -1);
    switch ($type) {
        case 0:
            $warn_data[$relative_day]++;
            break;
        case 1:
            $critical_data[$relative_day]++;
            break;
    }
}

$x_labels = array_reverse(array_values($x_labels));
$warn_data = array_reverse(array_values($warn_data));
$critical_data = array_reverse(array_values($critical_data));

$chart_data = [
    'warn'      => $warn_data,
    'critical'  => $critical_data,
    'labels'    => $x_labels
];

if (isset($_GET['eventstart'])) {
    $start = $_GET['eventstart'];
    $eventlist = array_filter($eventlist, function($event) use ($start) {
        return $event['timestamp'] >= $start;
    });
}
echo json_encode(['chart' => $chart_data, 'events' => array_reverse($eventlist)]);