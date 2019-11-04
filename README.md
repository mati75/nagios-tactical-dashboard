# Nagios Tactical Dashboard
A simple Nagios dashboard inspired by [chriscareycode/nagiostv-react](https://github.com/chriscareycode/nagiostv-react).
This dashboard differs in that it tries to reduce the amount of network traffic being sent by using server-side caching and intelligently getting full details about hosts and services only when needed.

## Configuration
### config/server.json
The following server.json example is for reference only. You will need to substitute the values to match your environment. All shown values are required.
```javascript
{
    "nagios_url": "http://localhost/nagios,
    "username": "nagiosadmin",
    "password": "nagiosadmin",
    "refresh_interval": 15,
    "historical_days": 7
}
```
#### Options
- nagios_url - The URL to your Nagios server (usually ends in /nagios)
- username - Username for the user that will be used to fetch information from the server. This can and should be a readonly user.
- password - The password for the user.
- refresh_interval - Time in seconds that new information will be fetched from the Nagios server. This is done by the server and not the client. Therefore, if multiple clients check in within a few seconds, the dashboard can use cached data instead of repeatedly asking the Nagios server for the data.
- historical_days - Number of days shown on the historical chart and list.

### config/client.json
The following client.json example is for reference only. You will need to substitute the values to match your environment.
All shown values are required.
```javascript
{
    "hide_services_on_down_hosts": true,
    "historical_chart_rebuild_minutes": 5,
    "refresh_interval": 15
}
```
#### Options
- hide_services_on_down_hosts - All active service alerts for a down or unreachable host will be hidden on the dashboard to reduce clutter.
- historical_chart_rebuild_minutes - Interval in minutes for refreshing the historical chart counts and labels.
- refresh_interval - Time in seconds that the dashboard is refreshed and data is fetched from the web server. It is useless to make this interval shorter than the server.json setting since the data is unlikely to have changed.
