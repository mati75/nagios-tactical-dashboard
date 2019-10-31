var refreshDashboard = function() {
    $.ajax({
        method: 'GET',
        url: "ajax/dashboard.php",
    }).done(function (data) {
        $("body").empty();
        $("body").append(data);
    });
};

$(document).ready(function() {
    refreshDashboard();
    setInterval(function() {
        refreshDashboard();
    }, 15000);
});