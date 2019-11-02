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

class NagiosServer {
    private static $config = null;

    const SERVICE_PENDING   = 1;
    const SERVICE_OK        = 2;
    const SERVICE_WARNING   = 4;
    const SERVICE_UNKNOWN   = 8;
    const SERVICE_CRITICAL  = 16;

    const HOST_PENDING      = 1;
    const HOST_UP           = 2;
    const HOST_DOWN         = 4;
    const HOST_UNREACHABLE  = 8;

    const STATE_SOFT        = 2;
    const STATE_HARD        = 1;

    const TYPE_HOST         = 1;
    const TYPE_SERVICE      = 2;

    static function init() {
        self::$config = json_decode(file_get_contents('../config/server.json'), true);
    }

    static function getNonOKServiceStatusArray() {
        return [
            self::SERVICE_PENDING,
            self::SERVICE_WARNING,
            self::SERVICE_UNKNOWN,
            self::SERVICE_CRITICAL
        ];
    }

    static function getNonOKHostStatusArray() {
        return [
            self::HOST_PENDING,
            self::HOST_DOWN,
            self::HOST_UNREACHABLE
        ];
    }

    static function setCurlAuth(&$ch) {
        if (isset(self::$config['username']) && (strlen(self::$config['username']) > 0)) {
            curl_setopt($ch, CURLOPT_USERPWD, self::$config['username'] . ":" . self::$config['password']);
        }
    }

    private static function getCurlResponse($url) {
        $ch = curl_init($url);
        self::setCurlAuth($ch);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    private static function formatURLOptions($params = [], $join_char = '&') {
        $param_str = '';
        foreach ($params as $key => $value) {
            $k = urlencode($key);
            $v = urlencode($value);
            $param_str .= "&$k=$v";
        }

        // Replace first join char with the user-specified one (Allows usage of ? instead of &)
        return $join_char . substr($param_str, 1);
    }

    static function getServiceStatusURL($params = []) {
        return self::$config['nagios_url'] . '/cgi-bin/statusjson.cgi?query=servicelist' . self::formatURLOptions($params);
    }

    static function getHostStatusURL($params = []) {
        return self::$config['nagios_url'] . '/cgi-bin/statusjson.cgi?query=hostlist' . self::formatURLOptions($params);
    }

    static function getEventsURL($params = []) {
        return self::$config['nagios_url'] . '/cgi-bin/archivejson.cgi?query=alertlist' . self::formatURLOptions($params);
    }

    static function getExtinfoUrl($params = []) {
        return self::$config['nagios_url'] . '/cgi-bin/extinfo.cgi' . self::formatURLOptions($params, '?');
    }

    static function getServiceList() {
        if (extension_loaded('apcu')) {
            if (!apcu_exists('servicelist')) {
                // Get the list of services without details, then the details for only non-OK services.
                $servicelist = self::_getServiceList();
                $details = self::_getNonOKServiceDetails();
                // Replace service => status pairs for non-OK services with the service => details[] pair.
                $servicelist = array_replace($servicelist, $details);
                apcu_store('servicelist', $servicelist, self::$config['refresh_interval']);
            }
            return apcu_fetch('servicelist');
        }
        return [];
    }

    private static function _getServiceList() {
        $response = self::getCurlResponse(self::getServiceStatusURL());
        return json_decode($response, true)['data']['servicelist'];
    }

    private static function _getNonOKServiceDetails() {
        $response = self::getCurlResponse(self::getServiceStatusURL([
            'details'       => 'true',
            'servicestatus' => 'unknown warning critical'
        ]));
        return json_decode($response, true)['data']['servicelist'];
    }

    static function getHostList() {
        if (extension_loaded('apcu')) {
            if (!apcu_exists('hostlist_brief')) {
                $hostlist = self::_getHostList();
                // Get the list of hosts without details, then the details for only non-OK hosts.
                $details = self::_getNonOKHostDetails();
                // Replace host => status pairs for non-OK hosts with the host => details[] pair.
                $hostlist = array_replace($hostlist, $details);
                apcu_store('hostlist_brief', $hostlist, self::$config['refresh_interval']);
            }
            return apcu_fetch('hostlist_brief');
        }
        return [];
    }

    private static function _getHostList() {
        $response = self::getCurlResponse(self::getHostStatusURL());
        return json_decode($response, true)['data']['hostlist'];
    }

    private static function _getNonOKHostDetails() {
        $response = self::getCurlResponse(self::getHostStatusURL([
            'details'       => 'true',
            'hoststatus'    => 'down unreachable'
        ]));
        return json_decode($response, true)['data']['hostlist'];
    }

    static function countHosts($statuses = null) {
        if ($statuses === null) {
            $statuses = [self::HOST_PENDING, self::HOST_UP, self::HOST_DOWN, self::HOST_UNREACHABLE];
        }
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }
        $hostlist = self::getHostList();
        if ($hostlist && is_array($hostlist)) {
            $count = 0;
            foreach ($hostlist as $host => $status) {
                if (in_array($status, $statuses)) {
                    $count += 1;
                }
            }
            return $count;
        }
        return 0;
    }

    static function countServices($statuses = null) {
        if ($statuses === null) {
            $statuses = [self::SERVICE_PENDING, self::SERVICE_OK, self::SERVICE_WARNING, self::SERVICE_UNKNOWN, self::SERVICE_CRITICAL];
        }
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }
        $servicelist = self::getServiceList();
        if ($servicelist && is_array($servicelist)) {
            $count = 0;
            foreach ($servicelist as $host => $services) {
                foreach ($services as $service => $details) {
                    if (is_array($details)) {
                        if (in_array($details['status'], $statuses)) {
                            $count += 1;
                        }
                    } else {
                        if (in_array($details, $statuses)) {
                            $count += 1;
                        }
                    }
                }
            }
            return $count;
        }
        return 0;
    }

    static function getServicesByStatus($details = false) {
        $servicelist = self::getServiceList();
        if ($servicelist && is_array($servicelist)) {
            $result = [];
            foreach ($servicelist as $host => $services) {
                foreach ($services as $service => $details) {
                    if (is_array($details)) {
                        $result[$host][$details['status']][$service] = $details;
                    } else {
                        $result[$host][$details][] = $service;
                    }
                }
            }
            return $result;
        }
        return [];
    }

    static function getHostsByStatus($details = false) {
        $hostlist = self::getHostList();
        if ($hostlist && is_array($hostlist)) {
            $result = [];
            foreach ($hostlist as $host => $details) {
                if (is_array($details)) {
                    $result[$details['status']][$host] = $details;
                } else {
                    $result[$details][] = $host;
                }
            }
            return $result;
        }
        return [];
    }

    static function isHostNonOK($host) {
        $hostlist = self::getHostList();
        if ($hostlist && is_array($hostlist)) {
            foreach ($hostlist as $host => $details) {
                if (is_array($details)) {
                    return $details['status'] !== self::HOST_UP;
                } else {
                    return $details !== self::HOST_UP;
                }
            }
            return true;
        }
        return false;
    }

    static function getEvents() {
        if (extension_loaded('apcu')) {
            if (!apcu_exists('eventlist')) {
                $eventlist = self::_getEvents();
                apcu_store('eventlist', $eventlist, self::$config['refresh_interval']);
            }
            return apcu_fetch('eventlist');
        }
        return [];
    }
    private static function _getEvents() {
        $time = self::$config['historical_days'] * 24 * 60 * 60;
        $response = self::getCurlResponse(self::getEventsURL([
            'starttime' => -($time),
            'endtime'   => 0
        ]));
        return array_reverse(json_decode($response, true)['data']['alertlist']);
    }
}
