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

    const ALERT_HOST_UP             = 1;
    const ALERT_HOST_DOWN           = 2;
    const ALERT_HOST_UNREACHABLE    = 4;
    const ALERT_SERVICE_OK          = 8;
    const ALERT_SERVICE_WARNING     = 16;
    const ALERT_SERVICE_CRITICAL    = 32;
    const ALERT_SERVICE_UNKNOWN     = 64;

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

    static function getCommentsURL($params = []) {
        return self::$config['nagios_url'] . "/cgi-bin/statusjson.cgi?query=commentlist" . self::formatURLOptions($params);
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
                foreach ($servicelist as $host => $services) {
                    foreach ($services as $service => $status) {
                        if (isset($details[$host][$service])) {
                            $servicelist[$host][$service] = $details[$host][$service];
                        }
                    }
                }
                self::attachServiceComments($servicelist, self::getComments());
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
                self::attachHostComments($hostlist, self::getComments());
                apcu_store('hostlist_brief', $hostlist, self::$config['refresh_interval']);
            }
            return apcu_fetch('hostlist_brief');
        }
        return [];
    }

    public static function getHostAlerts() {
        $hostlist = self::getHostList();
        $alerts = [];
        foreach ($hostlist as $host => $details) {
            if (is_array($details)) {
                // This data will be sent to the client so only send the values that will be used
                $used_values = ['status', 'state_type', 'is_flapping', 'problem_has_been_acknowledged',
                    'plugin_output', 'last_state_change'];
                $alerts[$host] = [];
                foreach ($used_values as $value) {
                    $alerts[$host][$value] = $details[$value];
                }
                $alerts[$host]['last_check'] = Toolbox::formatNagiosTimestamp($details['last_check']);
                $alerts[$host]['last_time_ok'] = Toolbox::formatNagiosTimestamp($details['last_time_ok']);
                $alerts[$host]['next_check'] = Toolbox::formatNagiosTimestamp($details['next_check']);
            }
        }

        return $alerts;
    }

    public static function getServiceAlerts() {
        $servicelist = self::getServiceList();
        $alerts = [];
        foreach ($servicelist as $host => $services) {
            $alerts[$host] = [];
            foreach ($services as $service => $details) {
                if (is_array($details)) {
                    // This data will be sent to the client so only send the values that will be used
                    $used_values = ['status', 'state_type', 'is_flapping', 'problem_has_been_acknowledged',
                        'plugin_output', 'last_state_change', '_comments'];
                    $alerts[$host][$service] = [];
                    foreach ($used_values as $value) {
                        if (isset($details[$value])) {
                            $alerts[$host][$service][$value] = $details[$value];
                        }
                    }
                    $alerts[$host][$service]['last_check'] = Toolbox::formatNagiosTimestamp($details['last_check']);
                    $alerts[$host][$service]['last_time_ok'] = Toolbox::formatNagiosTimestamp($details['last_time_ok']);
                    $alerts[$host][$service]['next_check'] = Toolbox::formatNagiosTimestamp($details['next_check']);
                }
            }
        }

        return $alerts;
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
                if (is_array($status)) {
                    if (in_array($status['status'], $statuses)) {
                        $count++;
                    }
                } else {
                    if (in_array($status, $statuses)) {
                        $count++;
                    }
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
                            $count++;
                        }
                    } else {
                        if (in_array($details, $statuses)) {
                            $count++;
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
            'endtime'   => '+0'
        ]));
        $alertlist = json_decode($response, true)['data']['alertlist'];
        foreach ($alertlist as &$alert) {
            $alert['_time'] = Toolbox::formatNagiosTimestamp($alert['timestamp']);
        }
        return array_reverse($alertlist);
    }

    static function getComments() {
        if (extension_loaded('apcu')) {
            if (!apcu_exists('commentlist')) {
                $commentlist = self::_getComments();
                apcu_store('commentlist', $commentlist, self::$config['refresh_interval']);
            }
            return apcu_fetch('commentlist');
        }
        return [];
    }

    private static function _getComments() {
        $response = self::getCurlResponse(self::getCommentsURL([
            'details'           => 'true',
            // Get only non-expired comments
            'starttime'         => 0,
            'endtime'           => 0,
            'commenttimefield'  => 'expiretime',
            // Only get acknowledgement comments for now
            'entrytypes'        => 'acknowledgement'
        ]));
        $commentlist = json_decode($response, true)['data']['commentlist'];
        // Replace entry_time with formatted time string
        foreach ($commentlist as $comment_id => &$c_details) {
            $c_details['entry_time'] = Toolbox::formatNagiosTimestamp($c_details['entry_time']);
        }
        return array_reverse($commentlist);
    }

    private static function attachHostComments(&$hostlist, $commentlist) {
        foreach ($hostlist as $host => &$details) {
            foreach ($commentlist as $comment_id => $c_details) {
                if (is_array($details) && $c_details['comment_type'] === self::TYPE_HOST &&
                $c_details['host_name'] === $host) {
                    // This comment applies to the host
                    // Remove redundant/useless data
                    unset($c_details['host_name'], $c_details['comment_id'], $c_details['comment_type'],
                        $c_details['entry_type']);
                    $details['_comments'][$comment_id] = $c_details;
                }
            }
        }
    }

    private static function attachServiceComments(&$servicelist, $commentlist) {
        foreach ($servicelist as $host => &$services) {
            foreach ($services as $service => &$details) {
                if (!is_array($details)) {
                    continue;
                }
                foreach ($commentlist as $comment_id => $c_details) {
                    if ($c_details['comment_type'] === self::TYPE_SERVICE &&
                        $c_details['host_name'] === $host && $c_details['service_description'] === $service) {
                        // This comment applies to the host
                        // Remove redundant/useless data
                        unset($c_details['host_name'], $c_details['service_description'], $c_details['comment_id'],
                            $c_details['comment_type'], $c_details['entry_type']);
                        $details['_comments'][$comment_id] = $c_details;
                    }
                }
            }
        }
    }

    public static function getSafeConfig() {
        $safe_config = self::$config;
        unset($safe_config['username'], $safe_config['password']);
        return $safe_config;
    }
}