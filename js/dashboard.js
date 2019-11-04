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

   /**
    * @todo Remove dev flag
    * @type {boolean}
    */
   this.legacyRefresh = true;

   this.refreshDashboard = function() {
      if (self.legacyRefresh) {
         $.ajax({
            method: 'GET',
            url: "ajax/dashboard.php",
         }).done(function (data) {
            $("body").empty();
            $("body").append(data);
         });
      } else {
         let now = new Date();
         self.dashboard.find('#last_refresh').text('Last refreshed: ' + now.toLocaleDateString() + ' ' + now.toLocaleTimeString());
         $.ajax({
            method: 'GET',
            url: "ajax/getCurrentAlerts.php",
            dataType: "application/json",
         }).done(function (data) {
            let hostalertlist = $('#host_alerts ul');
            let servicealertlist = $('#service_alerts ul');

            if (data['hosts'] !== undefined) {
               $.each(data['hosts'], function(host, details) {
                  let match = hostalertlist.find("[data-host='" + host + "']");
               });
            }
         });
      }
   };

   this.init = function() {
      $.ajax({
         method: 'GET',
         url: "ajax/getClientConfig.php",
         success: function(data) {
            self.config = data;
            if (self.config['refresh_interval'] === undefined || self.config['refresh_interval'] < 10) {
               self.config['refresh_interval'] = 10;
            }
            if (self.config['historical_chart_rebuild_minutes'] === undefined || self.config['historical_chart_rebuild_minutes'] < 1) {
               self.config['historical_chart_rebuild_minutes'] = 1;
            }

            // Build HTML layout
            self.dashboard = $("<div id='nagios-dashboard' class='dashboard'></div>").appendTo($('body'));
            self.dashboard.append("<h3 id='last_refresh'></h3>");
            let activealerts = $("<div id='active_alerts'></div>").appendTo(self.dashboard);
            activealerts.append("<div id='host_alerts'><ul></ul></div>");
            activealerts.append("<div id='service_alerts'><ul></ul></div>");
            self.dashboard.append("<canvas id='historical-chart'></canvas>");
            self.dashboard.append("<div id='historical_event_list'><ul></ul></div>");

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