/* JS for tables.php interactions (arrivals, AJAX updates) */
(function() {
    const config = window.tablesConfig || {};
    const activite_id = parseInt(config.selectedActivityId || 0, 10);

    function showDebug(msg, color) {
        let debugDiv = document.getElementById('static-debug-msg');
        if (!debugDiv) {
            debugDiv = document.createElement('div');
            debugDiv.id = 'static-debug-msg';
            debugDiv.style.position = 'fixed';
            debugDiv.style.top = '0';
            debugDiv.style.left = '0';
            debugDiv.style.width = '100vw';
            debugDiv.style.background = color || '#28a745';
            debugDiv.style.color = 'white';
            debugDiv.style.padding = '18px 30px';
            debugDiv.style.zIndex = 99999;
            debugDiv.style.fontSize = '1.3em';
            debugDiv.style.textAlign = 'center';
            debugDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
            debugDiv.style.fontWeight = 'bold';
            document.body.prepend(debugDiv);
        }
        debugDiv.innerHTML = msg + ' <button id="close-debug-arrivee" style="margin-left:30px;padding:6px 18px;background:#fff;color:#333;border:none;border-radius:3px;cursor:pointer;font-size:1em;">Fermer</button>';
        const closeBtn = document.getElementById('close-debug-arrivee');
        if (closeBtn) closeBtn.onclick = function() { debugDiv.innerHTML = 'DEBUG: Test message statique (doit rester visible)'; };
    }

    document.addEventListener('DOMContentLoaded', function() {
        let debugDiv = document.getElementById('debug-arrivee-msg');
        if (!debugDiv) {
            debugDiv = document.createElement('div');
            debugDiv.id = 'debug-arrivee-msg';
            debugDiv.style.position = 'fixed';
            debugDiv.style.top = '0';
            debugDiv.style.left = '0';
            debugDiv.style.width = '100vw';
            debugDiv.style.background = '#007bff';
            debugDiv.style.color = 'white';
            debugDiv.style.padding = '18px 30px';
            debugDiv.style.zIndex = 99999;
            debugDiv.style.fontSize = '1.3em';
            debugDiv.style.textAlign = 'center';
            debugDiv.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
            debugDiv.style.fontWeight = 'bold';
            document.body.prepend(debugDiv);
        }
        debugDiv.innerHTML = 'JS chargé (test debug) <button id="close-debug-arrivee" style="margin-left:30px;padding:6px 18px;background:#fff;color:#333;border:none;border-radius:3px;cursor:pointer;font-size:1em;">Fermer</button>';
        document.getElementById('close-debug-arrivee').onclick = function() { debugDiv.remove(); };
    });

    $(document).on('click', '.btn-arrivee', function(e) {
        e.preventDefault();
        const btn = $(this);
        const row = btn.closest('tr');
        const id_membre = btn.data('id-membre');
        const id_participation = btn.data('id-participation');

        showDebug('Appel update_field.php...', '#007bff');
        $.ajax({
            url: 'update_field.php',
            method: 'POST',
            data: {
                id_membre: id_membre,
                field: 'heure_arrivee',
                value: 'NOW()',
                id_activite: activite_id
            },
            dataType: 'json',
            success: function(response) {
                showDebug('update_field.php OK', '#28a745');
                showDebug('Appel update_bonus_arrivee.php...', '#007bff');
                $.ajax({
                    url: 'update_bonus_arrivee.php',
                    method: 'POST',
                    data: { id_activite: activite_id },
                    dataType: 'json',
                    success: function(response) {
                        showDebug('update_bonus_arrivee.php OK', '#28a745');
                        showDebug('Appel get_participant_data.php...', '#007bff');
                        $.ajax({
                            url: 'get_participant_data.php',
                            method: 'POST',
                            data: { id_membre: id_membre, id_activite: activite_id },
                            dataType: 'json',
                            success: function(data) {
                                showDebug('get_participant_data.php OK', '#28a745');
                                if (data && data.data) {
                                    showDebug('Données reçues : ' + JSON.stringify(data.data), '#ff9800');
                                    const bonusArriveeTd = row.find('td').eq(3);
                                    if ((parseInt(data.data.jetons_bonus_arrivee) || 0) > 0) {
                                        bonusArriveeTd.text(parseInt(data.data.jetons_bonus_arrivee).toLocaleString('fr-FR'));
                                    } else {
                                        bonusArriveeTd.text('-');
                                    }
                                    const totalTd = row.find('td').eq(1);
                                    let pseudo = data.data['nom-membre'] || '';
                                    let jetons_total = (parseInt(data.data.jetons_total) || 0) > 0 ? '<div style="font-size:0.9em;color:#666;margin-top:2px;">'+parseInt(data.data.jetons_total).toLocaleString('fr-FR')+'</div>' : '';
                                    totalTd.html(pseudo + jetons_total);
                                    const statutTd = row.find('td').eq(4);
                                    if (data.data.option) {
                                        statutTd.text(data.data.option);
                                    }
                                }
                            },
                            error: function(xhr, status, error) {
                                let msg = 'Erreur get_participant_data.php: ' + error;
                                if (xhr && xhr.responseText) {
                                    msg += ' | Réponse brute: ' + xhr.responseText;
                                }
                                showDebug(msg, '#dc3545');
                            }
                        });
                    },
                    error: function(xhr, status, error) {
                        showDebug('Erreur update_bonus_arrivee.php: ' + error, '#dc3545');
                    }
                });
            },
            error: function(xhr, status, error) {
                let msg = 'Erreur update_field.php: ' + error;
                if (xhr && xhr.responseText) {
                    msg += ' | Réponse brute: ' + xhr.responseText;
                }
                showDebug(msg, '#dc3545');
            }
        });
    });
})();
