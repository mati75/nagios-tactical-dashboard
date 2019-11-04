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
    public static $config = null;

    static function init() {
        self::$config = json_decode(file_get_contents('../config/client.json'), true);
    }

    private static function _showDashboardHostAlerts() {
        echo "<h3>" . NagiosServer::countHosts() . " Hosts</h3>";
        echo "<ul>";
        if (NagiosServer::countHosts(NagiosServer::getNonOKHostStatusArray()) === 0) {
            echo "<li class='ok'><h4>All hosts OK</h4></li>";
        } else {
            $all_hosts = NagiosServer::getHostsByStatus();

            if (isset($all_hosts[NagiosServer::HOST_DOWN])) {
                foreach ($all_hosts[NagiosServer::HOST_DOWN] as $host => $details) {
                    $hostlink = Toolbox::getLinkForHost($host);
                    echo "<li class='critical'><h4>{$hostlink} - Down</h4>";
                    echo "{$details['plugin_output']}";
                    echo "</li>";
                }
            }
            if (isset($all_hosts[NagiosServer::HOST_UNREACHABLE])) {
                foreach ($all_hosts[NagiosServer::HOST_UNREACHABLE] as $host => $details) {
                    $hostlink = Toolbox::getLinkForHost($host);
                    echo "<li class='critical'><h4>{$hostlink} - Unreachable</h4>";
                    echo "{$details['plugin_output']}";
                    echo "</li>";
                }
            }
        }
        echo "</ul>";
    }

    private static function _showDashboardServiceAlerts() {
        $service_header_text = NagiosServer::countServices() . " Services";
        $service_list = "<ul>";
        if (NagiosServer::countServices(NagiosServer::getNonOKServiceStatusArray()) === 0) {
            $service_list .= "<li class='ok'><h4>All services OK</h4></li>";
        } else {
            $all_services = NagiosServer::getServiceList();
            $all_hosts = NagiosServer::getHostsByStatus();

            $to_show = [];
            foreach ($all_services as $host => $services) {
                foreach ($services as $service => $details) {
                    if (self::$config['hide_services_on_down_hosts']) {
                        if (isset($all_hosts[NagiosServer::HOST_DOWN][$host]) ||
                            isset($all_hosts[NagiosServer::HOST_UNREACHABLE][$host]) ||
                            isset($all_hosts[NagiosServer::HOST_PENDING][$host])) {
                            continue;
                        }
                    }
                    $to_show[$host][$details['status']][$service] = $details;
                }
            }

            foreach ($to_show as $host => $services) {
                foreach ($services as $status => $status_services) {
                    foreach ($status_services as $service => $details) {
                        $last_check = Toolbox::formatNagiosTimestamp($details['last_check']);
                        $last_ok = Toolbox::formatNagiosTimestamp($details['last_time_ok']);
                        $next_check = Toolbox::formatNagiosTimestamp($details['next_check']);
                        $hostlink = Toolbox::getLinkForHost($host);
                        $servicelink = Toolbox::getLinkForService($host, $service);

                        $statustype = $status === NagiosServer::SERVICE_WARNING ? 'warn' : 'critical';
                        if ($details['problem_has_been_acknowledged']) {
                            $statustype .= '-ack';
                        }
                        $service_list .= "<li class='$statustype'><h4>{$hostlink} - <span class='service-name'>{$servicelink}</span>";
                        $service_list .= "<span class='right'>";
                        if ($details['state_type'] === NagiosServer::STATE_SOFT) {
                            $service_list .= "<span class='space-m'>Soft</span>";
                        } else {
                            $service_list .= "<span class='space-m'>Hard</span>";
                        }
                        if ($details['is_flapping']) {
                            $service_list .= "<span class='space-m warn no-bg'>Flapping</span>";
                        }
                        if ($details['problem_has_been_acknowledged']) {
                            $service_list .= "<span class='space-m ok no-bg'>Ack</span>";
                        }
                        $service_list .= "</span>";
                        $service_list .= "</h4>";
                        $service_list .= "<span class='$statustype'> - {$details['plugin_output']}</span></h4>";
                        $service_list .= "<h4><span class=''>Last Check: $last_check - Next Check: $next_check</span>";
                        $service_list .= "<span class=' right'>Last OK: $last_ok</span>";
                        if (isset($details['_comments'])) {
                            $service_list .= "<br>";
                            foreach ($details['_comments'] as $comment_id => $comment) {
                                $c_timestamp = Toolbox::formatNagiosTimestamp($comment['entry_time']);
                                $service_list .= "<span class='ok no-bg'>{$comment['author']}: {$comment['comment_data']}&nbsp{$c_timestamp}</span>";
                            }
                        }
                        $service_list .= "</h4>";
                        $service_list .= "</li>";
                    }
                }
            }
        }
        $service_list .= "</ul>";
        echo "<h3>{$service_header_text}</h3>";
        echo $service_list;
    }

    private static function _showDashboardEvents() {
        echo "<h3>Historical</h3>";
        $eventlist = NagiosServer::getEvents();

        self::_showHistoricalChart();

        echo "<div id='historical-list'><ul>";
        foreach ($eventlist as $event) {
            $statetype = 'critical';
            if ($event['object_type'] === NagiosServer::TYPE_SERVICE) {
                switch ($event['state']) {
                    case NagiosServer::ALERT_SERVICE_OK:
                        $statetype = 'ok';
                        break;
                    case NagiosServer::ALERT_SERVICE_WARNING:
                        $statetype = 'warn';
                        break;
                    case NagiosServer::ALERT_SERVICE_UNKNOWN:
                    case NagiosServer::ALERT_SERVICE_CRITICAL:
                        $statetype = 'critical';
                        break;
                }
            } else {
                switch ($event['state']) {
                    case NagiosServer::ALERT_HOST_UP:
                        $statetype = 'ok';
                        break;
                    case NagiosServer::ALERT_HOST_DOWN:
                    case NagiosServer::ALERT_HOST_UNREACHABLE:
                        $statetype = 'critical';
                        break;
                }
            }

            $statetype = $statetype . ' no-bg';
            $timestamp = Toolbox::formatNagiosTimestamp($event['timestamp']);

            echo "<li class='neutral'>";
            $main_info = '';
            $right_info = $timestamp;
            if ($event['object_type'] === NagiosServer::TYPE_SERVICE) {
                $hostlink = Toolbox::getLinkForHost($event['host_name']);
                $servicelink = Toolbox::getLinkForService($event['host_name'], $event['description']);
                $main_info .= "$hostlink - <span class='service-name'>$servicelink</span>";
            } else {
                $hostlink = Toolbox::getLinkForHost($event['name']);
                $main_info .= $hostlink;
            }
            $main_info .= " - <span class='{$statetype}'>{$event['plugin_output']}</span>";
            echo "<p>$main_info <span class='right'>$right_info</span></p>";
            echo "</li>";
        }
        echo "</ul></div>";
    }

    private static function _getHistoricalChartData() {
        if (extension_loaded('apcu')) {
            if (!apcu_exists('historicalchart_data')) {
                $eventlist = NagiosServer::getEvents();
                $data = [
                    'labels'    => [],
                    'datasets'  => [
                        [
                            'label' => 'Warning',
                            'data'  => [],
                            'backgroundColor' => '#ffff00'
                        ],
                        [
                            'label' => 'Critical',
                            'data'  => [],
                            'backgroundColor' => '#880000'
                        ]
                    ]
                ];
                $warn_data = [];
                $critical_data = [];

                // Fill days from -Client::$config['historical_days'] to today
                for ($i = 0; $i < NagiosServer::getSafeConfig()['historical_days']; $i++) {
                    $data['labels'][] = date('M dS', strtotime("-{$i} days"));
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

                $x_labels = array_reverse(array_values($data['labels']));
                unset($data['labels']);

                $warn_data = array_reverse(array_values($warn_data));
                $critical_data = array_reverse(array_values($critical_data));
                $chartdata = [
                    'warn'  => $warn_data,
                    'critical'  => $critical_data,
                    'labels'    =>  $x_labels
                ];
                apcu_store('historicalchart_data', $chartdata, self::$config['historical_chart_rebuild_minutes'] * 60);
            }
            return apcu_fetch('historicalchart_data');
        }
        return [];
    }

    private static function _showHistoricalChart() {
        // Get (maybe) cached chart data to avoid unneeded re-calculations
        $chartdata = self::_getHistoricalChartData();
        $warn_data = json_encode($chartdata['warn']);
        $critical_data = json_encode($chartdata['critical']);
        $x_labels = json_encode($chartdata['labels']);

        $js = <<<JAVASCRIPT
        $(document).ready(function() {
            let chart = document.getElementById('historical-chart');
            chart.height = 64;
            var stackedBar = new Chart(chart, {
                type: 'bar',
                data: {
                    datasets: [
                        {
                            label: "Warning",
                            data: $warn_data,
                            backgroundColor: "#ffff00"
                        },
                        {
                            label: "Critical",
                            data: $critical_data,
                            backgroundColor: "#880000"
                        }
                    ]
                },
                options: {
                    animation: false,
                    scales: {
                        xAxes: [{
                            labels: $x_labels,
                            stacked: true,
                            ticks: {
                                min: 0
                            }
                        }],
                        yAxes: [{
                            stacked: true
                        }]
                    }
                }
            });
        });
JAVASCRIPT;
        echo "<canvas id='historical-chart'></canvas>";
        echo "<script>$js</script>";
    }

    static function showDashboard() {
        echo "<div id='nagios-dashboard' class='dashboard'>";
        echo "<h3>Last refreshed: " . date('M d Y, h:i:s A') . "</h3>";
        self::_showDashboardHostAlerts();
        self::_showDashboardServiceAlerts();
        self::_showDashboardEvents();
        echo "</div>";
    }
}