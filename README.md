# Nagios Tactical Dashboard
A simple Nagios dashboard inspired by [chriscareycode/nagiostv-react](https://github.com/chriscareycode/nagiostv-react).

## Configuration
### config/server.json
The following server.json example is for reference only. You will need to substitute the values to match your environment. All shown values are required.
```javascript
{
    "nagios_url": "http://localhost/nagios,
    "username": "nagiosadmin",
    "password": "nagiosadmin",
    "refresh_interval": 15
}
```
#### Options
nagios_url - The URL to your Nagios server (usually ends in /nagios)
username - Username for the user that will be used to fetch information from the server. This can and should be a readonly user.
password - The password for the user.
refresh_interval - Time in seconds that new information will be fetched from the Nagios server. This is done by the server and not the client. Therefore, if multiple clients check in within a few seconds, the dashboard can use cached data instead of repeatedly asking the Nagios server for the data.

### config/client.json
The following client.json example is for reference only. You will need to substitute the values to match your environment.
All shown values are required.
```javascript
{
    "group_host_services": true
}
```
#### Options
group_host_services - If true and multiple services are in a non-OK state, they will be grouped into the same item (by status). This may reduce clutter on dashboards in some cases.
