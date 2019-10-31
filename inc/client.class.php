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

class Client {
    private static $config = null;

    static function init() {
        self::$config = json_decode(file_get_contents(SITE_ROOT . '/config/client.json'), true);
    }

    private static function _showDashboardHostAlerts() {
        echo "<h3>" . NagiosServer::countHosts() . " Hosts</h3>";
        echo "<ul>";
        if (NagiosServer::countHosts(NagiosServer::getNonOKHostStatusArray()) === 0) {
            echo "<li class='ok'><h4>All hosts OK</h4></li>";
        } else {
            $all_hosts = NagiosServer::getHostsByStatus();

            if (isset($all_hosts[NagiosServer::HOST_UP])) {
                foreach ($all_hosts[NagiosServer::HOST_UP] as $host) {
                    echo "<li class='critical'><h4>{$host} - Down</h4>";
                    echo "<p>Extra stuff here</p>";
                    echo "</li>";
                }
            }
        }
        echo "</ul>";
    }

    private static function _showDashboardServiceAlerts() {
        echo "<h3>" . NagiosServer::countServices() . " Services</h3>";
        echo "<ul>";
        if (NagiosServer::countServices(NagiosServer::getNonOKServiceStatusArray()) === 0) {
            echo "<div><h4 class='ok'>All services OK</h4>";
        } else {
            $all_services = NagiosServer::getServicesByStatus();

            foreach ($all_services as $host => $statuses) {
                if (isset($statuses[NagiosServer::SERVICE_CRITICAL])) {
                    $to_show = [];
                    foreach ($statuses[NagiosServer::SERVICE_CRITICAL] as $service => $details) {
                        $to_show[] = $service;
                    }
                    if (Client::$config['group_host_services']) {
                        $service_str = '';
                        foreach ($to_show as $service) {
                            $service_str .= $service . ', ';
                        }
                        $service_str = rtrim($service_str, ', ');
                        echo "<li class='critical'><h4>{$host} - <span class='service-name'>{$service_str}</span></h4>";
                        echo "<p>Extra stuff here</p>";
                        echo "</li>";
                    } else {
                        foreach ($to_show as $service) {
                            echo "<li class='critical'><h4>{$host} - <span class='service-name'>{$service}</span></h4>";
                            echo "<p>Extra stuff here</p>";
                            echo "</li>";
                        }
                    }
                }
            }
            foreach ($all_services as $host => $statuses) {
                if (isset($statuses[NagiosServer::SERVICE_WARNING])) {
                    $to_show = [];
                    foreach ($statuses[NagiosServer::SERVICE_WARNING] as $service) {
                        $to_show[] = $service;
                    }
                    if (Client::$config['group_host_services']) {
                        $service_str = '';
                        foreach ($to_show as $service) {
                            $service_str .= $service . ', ';
                        }
                        $service_str = rtrim($service_str, ', ');
                        echo "<li class='warn'><h4>{$host} - <span class='service-name'>{$service_str}</span></h4>";
                        echo "<p>Extra stuff here</p>";
                        echo "</li>";
                    } else {
                        foreach ($to_show as $service) {
                            echo "<li class='warn'><h4>{$host} - <span class='service-name'>{$service}</span></h4>";
                            echo "<p>Extra stuff here</p>";
                            echo "</li>";
                        }
                    }
                }
            }
        }
        echo "</ul>";
    }

    static function showDashboard() {
        echo "<div id='nagios-dashboard' class='dashboard'>";
        self::_showDashboardHostAlerts();
        self::_showDashboardServiceAlerts();
        echo "</div>";
    }
}