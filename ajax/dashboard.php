<?php
include("../inc/includes.php");

if (!extension_loaded('apcu')) {
    echo "<h2>APCu Extension Missing!</h2>";
}
NagiosServer::init();
Client::init();
Client::showDashboard();