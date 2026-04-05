// Fonction pour sauvegarder les blindes actuelles
function saveSnapshot() {
    var snapshotName = document.getElementById('snapshotName').value.trim();
    
    if (!snapshotName) {
        alert('⚠️ Veuillez entrer un nom pour la sauvegarde');
        return;
    }
    
    // Récupérer l'ID de l'activité depuis l'URL
    var urlParams = new URLSearchParams(window.location.search);
    var id_activite = urlParams.get('uid');
    
    if (!id_activite) {
        alert('❌ Erreur: ID d\'activité introuvable');
        console.error('URL params:', window.location.search);
        return;
    }
    
    console.log('Début sauvegarde - ID activité:', id_activite, 'Nom:', snapshotName);
    
    // Récupérer le bouton
    var saveButton = document.querySelector('button[onclick="saveSnapshot()"]');
    if (saveButton) {
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sauvegarde...';
    }
    
    $.ajax({
        url: 'save_blindes_snapshot.php',
        type: 'POST',
        data: {
            id_activite: id_activite,
            snapshot_name: snapshotName
        },
        dataType: 'json',
        success: function(response) {
            console.log('Réponse serveur:', response);
            if (response.status === 'success') {
                alert('✅ Sauvegarde créée avec succès');
                document.getElementById('snapshotName').value = '';
                loadSnapshots();
            } else {
                alert('❌ Erreur: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Erreur AJAX:', error);
            console.error('Statut:', status);
            console.error('Réponse serveur:', xhr.responseText);
            alert('❌ Erreur lors de la création de la sauvegarde. Vérifiez la console.');
        },
        complete: function() {
            // Réactiver le bouton
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fa fa-save" style="margin-right: 8px;"></i> Sauvegarder';
            }
        }
    });
}

// Fonction pour charger les sauvegardes disponibles
function loadSnapshots() {
    console.log('Chargement des snapshots...');
    
    $.ajax({
        url: 'get_blindes_snapshots.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Réponse du serveur:', response);
            var snapshotsList = document.getElementById('snapshots-list');
            
            if (!snapshotsList) {
                console.error('Element snapshots-list introuvable');
                return;
            }
            
            if (response.status === 'success' && response.snapshots && response.snapshots.length > 0) {
                var html = '';
                response.snapshots.forEach(function(snapshot) {
                    html += '<div class="snapshot-item" style="padding: 12px; margin-bottom: 10px; border: 1px solid #e0e6ed; border-radius: 6px; background-color: #f8f9fa; display: flex !important; justify-content: space-between; align-items: center;">';
                    html += '<div style="flex: 1; display: flex; align-items: center; gap: 10px;">';
                    html += '<div style="font-weight: 600; color: #333; font-size: 13px;">';
                    html += snapshot.name;
                    html += '</div>';
                    if (snapshot.titre_activite) {
                        html += '<div style="font-weight: 400; color: #666; font-size: 13px;">';
                        html += ' ' + snapshot.titre_activite;
                        html += '</div>';
                    }
                    html += '</div>';
                    html += '<div style="display: flex !important; gap: 8px; align-items: center; visibility: visible !important; opacity: 1 !important;">';
                    html += '<button class="btn btn-sm btn-primary" onclick="restoreSnapshot(' + snapshot.id + ', ' + snapshot.id_activite + ')" style="padding: 6px 12px; font-size: 11px; background-color: #007bff !important; border: none; border-radius: 4px; color: white !important; font-weight: 600; display: inline-block !important; visibility: visible !important; opacity: 1 !important;">';
                    html += '<i class="fa fa-undo" style="margin-right: 4px; display: inline !important; visibility: visible !important; color: white !important;"></i> Restaurer';
                    html += '</button>';
                    html += '<button class="btn btn-sm btn-danger btn-delete-snapshot" onclick="deleteSnapshot(' + snapshot.id + ')" style="padding: 6px 10px; font-size: 11px; background-color: #dc3545 !important; border: none; border-radius: 4px; color: white !important; font-weight: 600; display: inline-block !important; visibility: visible !important; opacity: 1 !important;">';
                    html += '<i class="fa fa-trash" style="display: inline !important; visibility: visible !important; opacity: 1 !important; color: white !important;"></i>';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
                });
                snapshotsList.innerHTML = html;
            } else {
                snapshotsList.innerHTML = '<p style="margin: 0; color: #999; font-size: 12px; text-align: center; padding: 20px 0;">📭 Aucune sauvegarde disponible</p>';
            }
        },
        error: function(xhr, status, error) {
            console.error('Erreur lors du chargement des sauvegardes:', error);
            console.error('Réponse serveur:', xhr.responseText);
            var snapshotsList = document.getElementById('snapshots-list');
            if (snapshotsList) {
                snapshotsList.innerHTML = '<p style="margin: 0; color: #dc3545; font-size: 12px; text-align: center; padding: 20px 0;">❌ Erreur lors du chargement</p>';
            }
        }
    });
}

// Fonction pour restaurer une sauvegarde
function restoreSnapshot(snapshotId, idActivite) {
    if (confirm('⚠️ Êtes-vous sûr de vouloir restaurer cette sauvegarde ? Cela remplacera toutes les blindes actuelles de cette activité.')) {
        console.log('Restauration snapshot:', snapshotId, 'pour activité:', idActivite);
        
        $.ajax({
            url: 'restore_blindes_snapshot.php',
            type: 'POST',
            data: {
                snapshot_id: snapshotId,
                id_activite: idActivite
            },
            dataType: 'json',
            success: function(response) {
                console.log('Réponse restauration:', response);
                if (response.status === 'success') {
                    alert('✅ Sauvegarde restaurée avec succès');
                    setTimeout(function() {
                        window.location.href = 'voir-blindes.php?uid=' + idActivite;
                    }, 1000);
                } else {
                    alert('❌ Erreur: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Erreur lors de la restauration');
                console.error('Erreur:', error);
                console.error('Réponse serveur:', xhr.responseText);
            }
        });
    }
}

// Fonction pour supprimer une sauvegarde
function deleteSnapshot(snapshotId) {
    if (confirm('⚠️ Êtes-vous sûr de vouloir supprimer cette sauvegarde ?')) {
        console.log('Suppression snapshot:', snapshotId);
        
        $.ajax({
            url: 'delete_blindes_snapshot.php',
            type: 'POST',
            data: {
                snapshot_id: snapshotId
            },
            dataType: 'json',
            success: function(response) {
                console.log('Réponse suppression:', response);
                if (response.status === 'success') {
                    alert('✅ Sauvegarde supprimée');
                    loadSnapshots();
                } else {
                    alert('❌ Erreur: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('❌ Erreur lors de la suppression');
                console.error('Erreur:', error);
                console.error('Réponse serveur:', xhr.responseText);
            }
        });
    }
}

// Charger les sauvegardes au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé - tentative de chargement des snapshots');
    var snapshotsList = document.getElementById('snapshots-list');
    if (snapshotsList) {
        console.log('Element snapshots-list trouvé');
        loadSnapshots();
    } else {
        console.log('Element snapshots-list NON trouvé');
    }
}
);
