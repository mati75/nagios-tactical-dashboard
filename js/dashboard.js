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

window.nagiosDashboard = function () {
   var self = this;

   this.dashboard = null;

   this.config = {};

   this.chart = null;

   const SERVICE_STATUSES = {
      1: "Pending",
      2: "Up",
      4: "Warning",
      8: "Unknown",
      16: "Critical"
   };

   const HOST_STATUSES = {
      1: "Pending",
      2: "Up",
      4: "Down",
      8: "Unreachable"
   };

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

   this.latest_event_timestamp = 0;

   this.initial_load = true;


   const getLinkForHost = function(host) {
      let extinfo = self.config['nagios_url'] + "/cgi-bin/extinfo.cgi";
      return "<a target='_blank' href='" + encodeURI(extinfo + "?type=1&host=" + host) + "'>" + host + "</a>";
   };

   const getLinkForService = function(host, service) {
      let extinfo = self.config['nagios_url'] + "/cgi-bin/extinfo.cgi";
      return "<a target='_blank' href='" + encodeURI(extinfo + "?type=2&host=" + host + "&service=" + service) + "'>" + service + "</a>";
   };

   const addHostAlert = function(host, details, old_alert) {
      let hostalertlist = $('#host_alerts ul');
      let host_url = getLinkForHost(host);
      let hostalert = "<li data-host='"+host+"' class='critical show'><h4>" + host_url + " - " + HOST_STATUSES[details['status']] + "<span class='right'>";
      if (details['status'] !== HOST_UNREACHABLE) {
         if (details['state_type'] === STATE_SOFT) {
            hostalert += "<span class='space-m upper'>Soft</span>";
         } else {
            hostalert += "<span class='space-m upper'>Hard</span>";
         }
         if (details['is_flapping']) {
            hostalert += "<span class='space-m warn no-bg upper'>Flapping</span>";
         }
         if (details['problem_has_been_acknowledged']) {
            hostalert += "<span class='space-m ok no-bg upper'>Ack</span>";
         }
      }
      hostalert += "</span></h4>";
      hostalert += details['plugin_output'];
      hostalert += "</li>";
      let hostalert_entry = null;
      if (old_alert === undefined) {
         hostalert_entry = $(hostalert).prependTo(hostalertlist);
      } else {
         old_alert.replaceWith(hostalert);
         hostalert_entry = hostalertlist.find("[data-host='" + host + "']");
      }
      hostalert_entry.data('details', details);
   };

   const addServiceAlert = function(host, service, details, old_alert) {
      let servicealertlist = $('#service_alerts ul');
      let statustype = details['status'] === SERVICE_WARNING ? 'warn' : 'critical';
      if (details['problem_has_been_acknowledged']) {
         statustype += '-ack';
      }
      let host_url = getLinkForHost(host);
      let service_url = getLinkForService(host, service);
      let servicealert = "<li data-host='"+host+"' data-service='"+service+"' class='" + statustype + " show'><h4>" + host_url + " - <span class='service-name'>" + service_url + "</span>";
      servicealert += "<span class='right'>";
      if (details['state_type'] === STATE_SOFT) {
         servicealert += "<span class='space-m upper'>Soft</span>";
      } else {
         servicealert += "<span class='space-m upper'>Hard</span>";
      }
      if (details['is_flapping']) {
         servicealert += "<span class='space-m warn no-bg upper'>Flapping</span>";
      }
      if (details['problem_has_been_acknowledged']) {
         servicealert += "<span class='space-m ok no-bg upper'>Ack</span>";
      }
      servicealert += "</span>";
      servicealert += "</h4>";
      servicealert += "<span class='" + statustype + "'> - " + details['plugin_output'] + "</span></h4>";
      servicealert += "<h4><span class=''>Last Check: " + details['last_check'] + " - Next Check: " + details['next_check'] + "</span>";
      servicealert += "<span class=' right'>Last OK: " + details['last_time_ok'] + "</span>";
      if (details['_comments'] !== undefined) {
         servicealert += "<br>";
         $.each(details['_comments'], function(comment_id, comment) {
            servicealert += "<span class='ok no-bg'>" + comment['author'] + ": " + comment['comment_data'] + "&nbsp-&nbsp" + comment['entry_time'] + "</span>";
         });
      }
      servicealert += "</h4>";
      servicealert += "</li>";
      let servicealert_entry = null;
      if (old_alert === undefined) {
         servicealert_entry = $(servicealert).prependTo(servicealertlist);
      } else {
         old_alert.replaceWith(servicealert);
         servicealert_entry = servicealertlist.find("[data-host='" + host + "'][data-service='" + service + "']");
      }
      servicealert_entry.data('details', details);
   };

   const updateAlerts = function(data) {
      let hostalertlist = $('#host_alerts ul');
      let servicealertlist = $('#service_alerts ul');

      if (!hostalertlist.hasClass('slide-fade')) {
         hostalertlist.addClass('slide-fade');
      }
      if (!servicealertlist.hasClass('slide-fade')) {
         servicealertlist.addClass('slide-fade');
      }

      if (data['hosts'] !== undefined) {
         // Remove old alerts
         let current_alerts = hostalertlist.find("li");
         $.each(current_alerts, function(ind, alert) {
            if (data['hosts'][alert.getAttribute('data-host')] === undefined) {
               // Old alert. Remove it and play OK sound.
               alert.remove();
               playSound('ok');
            }
         });

         // Add new alerts
         $.each(data['hosts'], function(host, details) {
            if (details === undefined) {
               // Bad alert
               return true;
            }

            let match = hostalertlist.find("[data-host='" + host + "']");
            if (match.length === 0) {
               // New alert
               addHostAlert(host, details);
               playSound('critical');
            } else {
               // Already have this alert. Check to see if anything changed.
               if (match.data('details') !== undefined) {
                  $.each(match.data('details'), function(k, v) {
                     if (v !== details[k]) {
                        // Alert has changed. Replace it and don't play new alert sound.
                        addHostAlert(host, details, match);
                     }
                  });
               } else {
                  // Malformed alert. Replace it and don't play new alert sound.
                  addHostAlert(host, details, match);
               }
            }
         });
      }

      if (data['services'] !== undefined) {
         // Remove old alerts
         let current_alerts = servicealertlist.find("li");
         $.each(current_alerts, function(ind, alert) {
            if (data['services'][alert.getAttribute('data-host')][alert.getAttribute('data-service')] === undefined) {
               // Old alert. Remove it and play OK sound.
               alert.remove();
               playSound('ok');
            }
         });

         // Add new alerts
         $.each(data['services'], function(host, services) {
            $.each(services, function(service, details) {
               if (details === undefined) {
                  // Bad alert
                  return true;
               }
               let match = servicealertlist.find("[data-host='" + host + "'][data-service='" + service + "']");
               if (match.length === 0) {
                  // New alert
                  addServiceAlert(host, service, details);
                  switch (details['status']) {
                     case SERVICE_WARNING:
                        playSound('warning');
                        break;
                     case SERVICE_CRITICAL:
                        playSound('critical');
                        break;
                  }
               } else {
                  // Already have this alert. Check to see if anything changed.
                  if (match.data('details') !== undefined) {
                     $.each(match.data('details'), function(k, v) {
                        if (v !== details[k]) {
                           // Alert has changed. Replace it and don't play new alert sound.
                           addServiceAlert(host, service, details, match);
                        }
                     });
                  } else {
                     // Malformed alert. Replace it and don't play new alert sound.
                     addServiceAlert(host, service, details, match);
                  }
               }
            });
         });
      }
   };

   const updateChart = function(chart_data) {
      self.chart.data.datasets[0].data = chart_data['warn'];
      self.chart.data.datasets[1].data = chart_data['critical'];
      self.chart.options.scales.xAxes[0].labels = chart_data['labels'];
      self.chart.update(0);
   };

   const calcEventID = function(host, service, timestamp) {
      const concat_str = host + service + timestamp;
      let hash = 0;
      if (concat_str.length === 0) {
         return hash;
      }
      for (let i = 0; i < concat_str.length; i++) {
         let char = concat_str.charCodeAt(i);
         hash = ((hash << 5)-hash) + char;
         hash = hash & hash;
      }
      return hash;
   };

   const addEvent = function(event) {
      console.log("add event");
      let eventlist = $('#historical_event_list ul');
      let statetype = 'critical';
      if (event['object_type'] === TYPE_SERVICE) {
         switch (event['state']) {
            case ALERT_SERVICE_OK:
               statetype = 'ok';
               break;
            case ALERT_SERVICE_WARNING:
               statetype = 'warn';
               break;
            case ALERT_SERVICE_UNKNOWN:
            case ALERT_SERVICE_CRITICAL:
               statetype = 'critical';
               break;
         }
      } else {
         switch (event['state']) {
            case ALERT_HOST_UP:
               statetype = 'ok';
               break;
            case ALERT_HOST_DOWN:
            case ALERT_HOST_UNREACHABLE:
               statetype = 'critical';
               break;
         }
      }

      statetype = statetype + ' no-bg';

      let event_entry = "<li class='neutral'>";
      let right_info = '';
      if (event['state_type'] === STATE_SOFT) {
         right_info += "<span class='space-m upper'>Soft</span>";
      } else {
         right_info += "<span class='space-m upper'>Hard</span>";
      }
      let main_info = event['_time'] + "<span class='right'>" + right_info + "</span><br>";

      if (event['object_type'] === TYPE_SERVICE) {
         let hostlink = getLinkForHost(event['host_name']);
         let servicelink = getLinkForService(event['host_name'], event['description']);
         main_info += hostlink + " - <span class='service-name'>" + servicelink + "</span>";
      } else {
         let hostlink = getLinkForHost(event['name']);
         main_info += hostlink;
      }
      main_info += " - <span class='" + statetype + "'>" + event['plugin_output'] + "</span>";
      event_entry += "<p>" + main_info + "</p></li>";
      let element = $(event_entry).prependTo(eventlist);
      let uid = null;
      if (event['object_type'] === TYPE_SERVICE) {
         uid = calcEventID(event['host_name'], event['description'], event['timestamp']);
      } else {
         uid = calcEventID(event['name'], '', event['timestamp']);
      }
      element.attr('data-uid', uid);
      self.latest_event_timestamp = Math.max(self.latest_event_timestamp, event['timestamp']);
   };

   const updateEventsList = function(events_data) {
      let eventlist = $('#historical_event_list ul');
      $.each(events_data, function(ind, event) {
         let uid = null;
         if (event['object_type'] === TYPE_SERVICE) {
            uid = calcEventID(event['host_name'], event['description'], event['timestamp']);
         } else {
            uid = calcEventID(event['name'], '', event['timestamp']);
         }
         let match = eventlist.find("[data-uid='" + uid + "']");
         if (match.length === 0) {
            addEvent(event);
         }
      });
   };

   const playSound = function(sound) {
      // Don't play sounds when first populating the screen or if the client config disables them.
      if (self.initial_load || !self.config['play_sounds']) {
         return;
      }
      const player = $('#alert-player').get(0);
      player.setAttribute('src', 'ajax/audio.php?sound=' + sound);
      player.load();
      player.play();
   };

   this.refreshDashboard = function() {
      playSound('critical');
      let now = new Date();
      self.dashboard.find('#last_refresh').text('Last refreshed: ' + now.toLocaleDateString() + ' ' + now.toLocaleTimeString());
      $.ajax({
         method: 'GET',
         url: "ajax/getCurrentAlerts.php",
      }).done(function (data) {
         updateAlerts(data);
         $.ajax({
            method: 'GET',
            url: "ajax/getEvents.php",
            contentType: "application/json",
            data: {
               eventstart: self.latest_event_timestamp
            }
         }).done(function (data) {
            updateChart(data['chart']);
            updateEventsList(data['events']);
            self.initial_load = false;
         });
      });
   };

   const initChart = function() {
      let ctx = document.getElementById('historical-chart');
      ctx.height = 25;
      self.chart = new Chart(ctx, {
         type: 'bar',
         data: {
            datasets: [
               {
                  label: "Warning",
                  data: [],
                  backgroundColor: "#ffff33"
               },
               {
                  label: "Critical",
                  data: [],
                  backgroundColor: "#ff6666"
               }
            ]
         },
         options: {
            animation: false,
            legend: {
               display: false
            },
            scales: {
               xAxes: [{
                  labels: [],
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
   };

   this.init = function() {
      $.ajax({
         method: 'GET',
         url: "ajax/getConfig.php",
         success: function(data) {
            self.config = data;

            // Apply some defaults for refresh intervals as a non-existent or bad value could result in a DOS situation.
            if (self.config['refresh_interval'] === undefined || self.config['refresh_interval'] < 10) {
               self.config['refresh_interval'] = 10;
            }
            if (self.config['historical_chart_rebuild_minutes'] === undefined || self.config['historical_chart_rebuild_minutes'] < 1) {
               self.config['historical_chart_rebuild_minutes'] = 1;
            }

            // Build HTML layout
            self.dashboard = $("<div id='nagios-dashboard' class='dashboard'></div>").appendTo($('body'));
            self.dashboard.append("<audio id='alert-player'></audio>");
            self.dashboard.append("<h3 id='last_refresh'></h3>");
            let activealerts = $("<div id='active_alerts'></div>").appendTo(self.dashboard);
            activealerts.append("<div id='host_alerts'><h3>Host Alerts</h3><ul></ul></div>");
            activealerts.append("<div id='service_alerts'><h3>Service Alerts</h3><ul></ul></div>");
            self.dashboard.append("<h3>Historical</h3><canvas id='historical-chart'></canvas>");
            self.dashboard.append("<div id='historical_event_list'><ul></ul></div>");

            initChart();
            self.refreshDashboard();

            setInterval(function() {
               self.refreshDashboard();
            }, self.config['refresh_interval'] * 1000);
         },
         error: function() {
            $("<h2>Error communicating with server</h2>").appendTo($("body"));
         }
      });
   };
};

$(document).ready(function() {
   var dashboard = new window.nagiosDashboard();
   dashboard.init();
});