console.log("%c[voir-blindes.js] Chargement du script (Mode Direct Clean v6 - Fix Classement)", "color: green; font-weight: bold; font-size: 14px;");

// --- 1. FONCTIONS D'ACTION DIRECTE (Appelées par onclick="") ---

// Action du bouton PLUS (+)
window.addRecave = function(button) {
    console.log("%c[Action] Clic sur bouton PLUS (+)", "color: blue; font-weight: bold;");
    var container = button.closest('.input-group');
    var input = container.querySelector('.recave-input');
    
    if (input) {
        var currentVal = parseInt(input.value) || 0;
        var newVal = currentVal + 1;
        input.value = newVal;
        console.log(" -> ID Participation: " + input.getAttribute('data-id'));
        console.log(" -> Ancienne valeur: " + currentVal + " | Nouvelle valeur: " + newVal);
    } else {
        console.error(" -> Erreur: Input recave introuvable !");
    }
};

// Action du bouton POUBELLE / SORTIE
window.confirmDeletePlayer = function(button) {
    console.log("%c[Action] Clic sur bouton SORTIE", "color: red; font-weight: bold;");
    var participationId = button.getAttribute('data-id');
    var memberId = button.getAttribute('data-member-id');
    var name = button.getAttribute('data-name');
    // AJOUT : Récupération de l'ID activité
    var activityId = button.getAttribute('data-activity-id');
    
    console.log(" -> Joueur ciblé: " + name + " (ID Part: " + participationId + ")");
    // AJOUT : Passage de l'ID activité à la modale
    openEliminationModal(participationId, name, activityId);
};

// Action du bouton VALIDER (Vert)
window.validerRecaves = function() {
    console.log("%c[Action] Validation des recaves en cours...", "color: orange; font-weight: bold;");
    var updates = [];
    var inputs = document.querySelectorAll('.recave-input');
    inputs.forEach(function(input) {
        updates.push({
            'id-participation': input.getAttribute('data-id'),
            'recave': input.value
        });
    });

    console.log(" -> Données à envoyer:", updates);

    $.ajax({
        url: 'update_recave.php',
        type: 'POST',
        data: {
            updates: JSON.stringify(updates),
            classements: JSON.stringify([])
        },
        dataType: 'json',
        success: function(response) {
            console.log("%c[AJAX Success] Mise à jour réussie", "color: green;");
            alert('Recaves mises à jour avec succès');
            location.reload();
        },
        error: function(xhr, status, error) {
            console.error("[AJAX Error]", error);
            alert('Erreur lors de la mise à jour des recaves');
        }
    });
};

// Action de suppression de blinde
window.deleteBlinde = function(id, activiteId) {
    console.log("[Action] Demande suppression blinde ID: " + id);
    if(confirm("Voulez-vous vraiment supprimer cette blinde ?")) {
        window.location.href = "delete-blinde.php?id=" + id + "&uid=" + activiteId;
    }
};


// --- 2. LOGIQUE D'ELIMINATION ET MODALE ---

// AJOUT : Paramètre activityId dans la signature
window.openEliminationModal = function(victimParticipationId, victimName, activityId) {
    console.log("[Modale] Ouverture pour éliminer: " + victimName);
    
    // Nettoyage ancienne modale
    var oldModal = document.querySelector('.elimination-modal-overlay');
    if(oldModal) oldModal.remove();

    var rows = document.querySelectorAll('#joueurs-list tr');
    var options = '<option value="" data-member-id="">-- Sélectionner un joueur --</option>';
    var countPlayers = 0;

    rows.forEach(function (r) {
        var inp = r.querySelector('.recave-input');
        var pseudoSpan = r.querySelector('.actual-pseudo');
        var elimSpan = r.querySelector('.eliminated-by'); // On regarde si le joueur est déjà éliminé
        
        if (!inp || !pseudoSpan) return;
        
        var partId = inp.dataset.id; 
        var membreId = inp.dataset.memberId; 
        
        if (String(partId) === String(victimParticipationId)) return; 
        
        // FILTRE : On ne propose que les joueurs EN JEUX (pas éliminés)
        // On vérifie la classe 'eliminated' ajoutée par PHP
        if (r.classList.contains('eliminated')) return;
        
        var pseudo = pseudoSpan.textContent.trim();
        options += '<option value="' + pseudo + '" data-member-id="' + membreId + '">' + pseudo + '</option>';
        countPlayers++;
    });
    console.log(" -> Joueurs disponibles pour éliminer: " + countPlayers);

    var overlay = document.createElement('div');
    overlay.className = 'elimination-modal-overlay';
    overlay.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:99999;';
    
    overlay.innerHTML = `
        <div style="background:#fff;padding:16px;border-radius:6px;min-width:320px;box-shadow:0 0 20px rgba(0,0,0,0.5);">
            <h5 style="margin:0 0 10px">Quel joueur a éliminé <strong>${victimName}</strong> ?</h5>
            <select id="eliminatorSelect" style="width:100%; height: 40px; padding:6px; margin-top:6px; color: #333; background-color: #fff;">${options}</select>
            
            <div style="margin-top:12px;padding:10px;border:1px solid #ddd;border-radius:4px;background-color:#f9f9f9;">
                <label style="display:flex;align-items:center;margin:0;cursor:pointer;">
                    <input type="checkbox" id="definitiveElimination" style="margin-right:8px;cursor:pointer;" />
                    <span style="color:red; font-size:13px;">Éliminé définitivement (Sortie du tournoi)</span>
                </label>
            </div>
            
            <div style="text-align:right;margin-top:10px;">
                <button class="btn btn-secondary btn-sm" id="elimCancel" style="margin-right:5px;">Annuler</button>
                <button class="btn btn-primary btn-sm" style="color:white; background:green !important" id="elimConfirm">CONFIRMER</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);

    overlay.querySelector('#elimCancel').onclick = function () {
        console.log("[Modale] Annulation");
        document.body.removeChild(overlay);
    };

    overlay.querySelector('#elimConfirm').onclick = function () {
        var select = overlay.querySelector('#eliminatorSelect');
        var eliminatorName = select.value;
        var selectedOption = select.options[select.selectedIndex];
        var eliminatorMemberId = selectedOption.getAttribute('data-member-id');
        var isDefinitive = overlay.querySelector('#definitiveElimination').checked;

        console.log("[Modale] Confirmation:");
        console.log(" -> Éliminateur: " + eliminatorName);
        console.log(" -> Définitif ? " + isDefinitive);

        if (eliminatorName === "") {
            alert("Veuillez sélectionner un joueur.");
            return;
        }

        document.body.removeChild(overlay);
        // AJOUT : Passage de activityId à la fonction d'application
        applyElimination(victimParticipationId, eliminatorMemberId, eliminatorName, isDefinitive, activityId);
    };
};

// AJOUT : Paramètre activityId dans la signature
window.applyElimination = function(victimParticipationId, eliminatorMemberId, eliminatorName, isDefinitiveElim, activityId) {
    console.log("%c[Process] Application de l'élimination...", "color: purple; font-weight: bold;");
    
    var markAsEliminatedUI = function() {
        console.log(" -> Mise à jour UI (Griser la ligne)");
        var rows = document.querySelectorAll('#joueurs-list tr');
        rows.forEach(function (r) {
            var inp = r.querySelector('.recave-input');
            if (!inp) return;
            if (String(inp.dataset.id) === String(victimParticipationId)) {
                var statusCell = r.querySelector('.eliminated-by');
                if (statusCell) {
                    statusCell.textContent = eliminatorName;
                    statusCell.setAttribute('data-eliminator-id', eliminatorMemberId);
                    statusCell.style.color = 'red';
                    statusCell.style.fontWeight = 'bold';
                }
                r.style.opacity = '0.6';
                r.style.backgroundColor = '#dcdcdc';
                var controls = r.querySelectorAll('input, button');
                controls.forEach(function (c) { c.disabled = true; });
            }
        });
    };

    var currentRecaveVal = 0;
    var rows = document.querySelectorAll('#joueurs-list tr');
    rows.forEach(function (r) {
        var inp = r.querySelector('.recave-input');
        if (inp && String(inp.dataset.id) === String(victimParticipationId)) {
            currentRecaveVal = parseInt(inp.value) || 0;
        }
    });

    var executeElimination = function() {
        var finalizeElimination = function() {
            if (isDefinitiveElim) {
                markAsEliminatedUI();
            }

            console.log(" -> Envoi AJAX record_elimination.php");
            
            $.ajax({
                url: 'record_elimination.php',
                type: 'POST',
                data: {
                    victim_id: victimParticipationId,
                    eliminator_id: eliminatorMemberId,
                    eliminator_name: eliminatorName,
                    is_definitive: isDefinitiveElim ? 1 : 0,
                    activity_id: activityId // AJOUT : Envoi de l'ID activité au PHP
                },
                dataType: 'json',
                success: function (resp) {
                    console.log("[AJAX Success] Réponse:", resp);
                    if (resp && resp.status === 'success') {
                        location.reload();
                    } else {
                        alert('Erreur: ' + (resp ? resp.message : 'Réponse vide'));
                    }
                },
                error: function (xhr, status, error) {
                    console.error('[AJAX Error]', error);
                    alert('Erreur AJAX: ' + error);
                }
            });
        };

        if (isDefinitiveElim) {
            console.log(" -> Calcul du classement pour élimination définitive");
            var totalJoueurs = document.querySelectorAll('#joueurs-list tr .recave-input').length;
            var dejaElimines = 0;
            
            // CORRECTION MAJEURE : Compter les joueurs déjà éliminés
            // On vérifie si la colonne "eliminated-by" contient du texte.
            // C'est la méthode la plus fiable car elle ne dépend pas du CSS ou de l'état disabled.
            document.querySelectorAll('#joueurs-list tr').forEach(function(row) {
                var elimSpan = row.querySelector('.eliminated-by');
                if (elimSpan && elimSpan.textContent.trim().length > 0) {
                    dejaElimines++;
                }
            });

            var rangCalcule = totalJoueurs - dejaElimines;
            console.log(" -> Total Joueurs: " + totalJoueurs);
            console.log(" -> Déjà éliminés: " + dejaElimines);
            console.log(" -> Rang calculé pour ce joueur: " + rangCalcule);
            
            // On envoie le classement à update_recave.php
            $.ajax({
                url: 'update_recave.php',
                type: 'POST',
                data: {
                    updates: JSON.stringify([]), // Pas de mise à jour de recave
                    classements: JSON.stringify([{
                        'id-participation': victimParticipationId,
                        'classement': rangCalcule
                    }])
                },
                dataType: 'json',
                success: function(response) {
                    console.log(" -> Classement sauvegardé avec succès.");
                    finalizeElimination();
                },
                error: function(xhr, status, error) {
                    console.error("Erreur sauvegarde classement:", error);
                    // On continue quand même pour enregistrer l'élimination
                    finalizeElimination();
                }
            });
        } else {
            finalizeElimination();
        }
    };

    if (!isDefinitiveElim) {
        var proceedWithRebuy = true; 

        if (typeof maxRecavesAllowed !== 'undefined' && currentRecaveVal >= maxRecavesAllowed) {
            console.log(" -> Max recaves atteint (" + maxRecavesAllowed + ")");
            var confirmExceptional = confirm("Le joueur a atteint le nombre maximum de recaves (" + maxRecavesAllowed + ").\n\nVoulez-vous autoriser une RECAVE EXCEPTIONNELLE ?\n\n- OK : Ajoute une recave (+1) et continue le tournoi.\n- Annuler : Élimine définitivement le joueur.");
            
            if (confirmExceptional) {
                proceedWithRebuy = true;
                isDefinitiveElim = false; 
                console.log(" -> Recave exceptionnelle ACCEPTEE");
            } else {
                proceedWithRebuy = false;
                isDefinitiveElim = true; 
                console.log(" -> Recave exceptionnelle REFUSEE -> Elimination définitive");
            }
        }

        if (proceedWithRebuy) {
            console.log(" -> Ajout automatique d'une recave (+1)");
            var newRecave = currentRecaveVal + 1;
            var updates = [{ 'id-participation': victimParticipationId, 'recave': newRecave }];

            $.ajax({
                url: 'update_recave.php',
                type: 'POST',
                data: { updates: JSON.stringify(updates), classements: JSON.stringify([]) },
                dataType: 'json',
                success: function(response) {
                    executeElimination();
                },
                error: function(xhr, status, error) {
                    alert("Attention : L'ajout automatique de la recave a échoué.");
                    executeElimination();
                }
            });
        } else {
            executeElimination();
        }
    } else {
        executeElimination();
    }
};

// --- 3. GESTION DES BLINDES (Restoration) ---

// Mise à jour unitaire (SB, BB, Ante)
window.updateBlindeValue = function(input) {
    var blindeId = input.getAttribute('data-id');
    var field = input.getAttribute('data-field'); // 'sb', 'bb', 'ante'
    var newValue = input.value.trim();

    if (newValue === '') return;

    $.ajax({
        url: 'update_blindes_values.php',
        type: 'POST',
        data: {
            id: blindeId,
            field: field,
            value: newValue
        },
        dataType: 'json',
        success: function (response) {
            if (response.status === 'success') {
                input.style.borderColor = 'green';
                console.log("[Blindes] " + field + " mis à jour (ID: " + blindeId + ")");
            } else {
                input.style.borderColor = 'red';
                console.error("[Blindes] Erreur update " + field + ": " + response.message);
            }
        },
        error: function () {
            input.style.borderColor = 'red';
        }
    });
};

// Mise à jour unitaire (Durée)
window.updateDureBlinde = function(input) {
    var blindeId = input.getAttribute('data-id');
    var newDuree = input.value.trim();

    if (newDuree === '') return;

    $.ajax({
        url: 'update_duree_blinde.php',
        type: 'POST',
        data: {
            id: blindeId,
            duree: newDuree
        },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                input.style.borderColor = 'green';
                console.log("[Blindes] Durée mise à jour (ID: " + blindeId + ")");
            } else {
                input.style.borderColor = 'red';
                console.error("[Blindes] Erreur update durée: " + response.message);
            }
        },
        error: function() {
            input.style.borderColor = 'red';
        }
    });
};

// Fonction globale pour le bouton "Valider Blindes"
window.validerBlindes = function() {
    console.log("[Action] Validation manuelle de toutes les blindes");
    var count = 0;
    
    // Mettre à jour SB, BB, Ante
    var inputs = document.querySelectorAll('.blinde-input');
    inputs.forEach(function(inp) { 
        updateBlindeValue(inp); 
        count++;
    });
    
    // Mettre à jour Durée
    var durees = document.querySelectorAll('.duree-input');
    durees.forEach(function(inp) { 
        updateDureBlinde(inp); 
        count++;
    });
    
    if(count > 0) {
        alert("Validation lancée pour " + count + " champs.");
    } else {
        alert("Aucun champ de blinde trouvé.");
    }
};

// Auto-save sur changement (Blur / Enter géré par le navigateur sur 'change')
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('blinde-input')) {
        updateBlindeValue(e.target);
    }
    if (e.target.classList.contains('duree-input')) {
        updateDureBlinde(e.target);
    }
});