(function() {
    var activityId = window.tablesPingActivityId || 0;
    if (!activityId || activityId <= 0) return;

    var lastVersion = null;
    function checkUpdate() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'tables_ping.php?id_activite=' + activityId, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (!resp || typeof resp.version === 'undefined') return;
                    if (lastVersion === null) {
                        lastVersion = resp.version;
                    } else if (resp.version !== lastVersion) {
                        window.location.reload();
                    }
                } catch (e) {
                    // ignore parse errors
                }
            }
        };
        xhr.send();
    }

    checkUpdate();
    setInterval(checkUpdate, 1000);
})();
