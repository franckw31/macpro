// Bonus Inscription Auto-Calculation
// bonus_ins = 200 * Math.floor((activite.date_depart - participation.ds) / 60 minutes)
// Appel lorsque le DOM est prêt

let bonusCalculationInProgress = false;
let bonusSaveInProgress = false;

function calculateBonusIns() {
    if (bonusCalculationInProgress) {
        console.log('Bonus calculation already in progress, skipping');
        return;
    }
    
    bonusCalculationInProgress = true;
    
    try {
        const inputs = document.querySelectorAll('[id^="bonus_ins_"]');
        
        inputs.forEach(function(input, idx) {
            const dateDepart = input.getAttribute('data-date-depart');
            const dateInscription = input.getAttribute('data-date-inscription');
            
            if (!dateDepart || !dateInscription) {
                return;
            }
            
            try {
                const depart = new Date(dateDepart);
                const inscription = new Date(dateInscription);
                
                if (isNaN(depart.getTime()) || isNaN(inscription.getTime())) {
                    return;
                }
                
                // Différence en secondes (date_depart - date_inscription)
                const diffSeconds = (depart.getTime() - inscription.getTime()) / 1000;
                
                // Convertir en minutes
                const minutesDiff = diffSeconds / 60;
                
                // Multiplicateur: 200 jetons par 60 minutes
                const multiplier = Math.floor(Math.abs(minutesDiff) / 60);
                
                // Bonus = 200 * multiplier, CAPPED at 5000 max
                let bonusValue = 200 * multiplier;
                if (bonusValue > 5000) {
                    console.log(`Row ${idx}: Capping ${bonusValue} to 5000`);
                    bonusValue = 5000;
                }
                
                // Mettre à jour le champ
                input.value = bonusValue;
                
                // Enforce constraint: if value exceeds 5000, set to 5000
                if (parseInt(input.value) > 5000) {
                    console.log(`Row ${idx}: Input value ${input.value} exceeds 5000, capping`);
                    input.value = 5000;
                }
                
                console.log(`Row ${idx}: minutesDiff=${minutesDiff.toFixed(2)}, multiplier=${multiplier}, bonusValue=${bonusValue}`);
                
                // Ajouter tooltip avec détails
                input.title = `Départ: ${dateDepart}\nInscription: ${dateInscription}\nDiff: ${minutesDiff.toFixed(2)} min\nBonus: ${bonusValue} jetons`;
                
            } catch (e) {
                console.error('Error calculating bonus for row ' + idx + ':', e);
            }
        });
        
        // Final enforcement: ensure NO bonus_ins value exceeds 5000
        const allBonusInputs = document.querySelectorAll('[id^="bonus_ins_"]');
        let cappedCount = 0;
        allBonusInputs.forEach(function(input) {
            const val = parseInt(input.value) || 0;
            if (val > 5000) {
                console.log(`FINAL CAP: ${input.id} = ${val} → 5000`);
                input.value = 5000;
                cappedCount++;
            }
        });
        
        console.log('Bonus calculation complete: ${inputs.length} fields processed, ${cappedCount} fields capped to 5000');
        
        // Update all bonus totals on the page
        updateAllBonusTotals();
        
        // Auto-save all calculated bonus values (saveCalculatedBonusToDatabase will also recalculate and save jetons_total)
        if (!bonusSaveInProgress) {
            saveCalculatedBonusToDatabase();
        }
        
    } catch (e) {
        console.error('Error in calculateBonusIns:', e);
    } finally {
        bonusCalculationInProgress = false;
    }
}

// Auto-save calculated bonus values to database
function saveCalculatedBonusToDatabase() {
    if (bonusSaveInProgress) {
        console.log('Save already in progress, skipping');
        return;
    }
    
    bonusSaveInProgress = true;
    
    try {
        console.log('saveCalculatedBonusToDatabase called');
        
        // Collect all bonus values with their participation IDs using a different approach
        const bonusData = [];
        const allBonusInputs = document.querySelectorAll('[id^="bonus_ins_"]');
        
        allBonusInputs.forEach(function(bonusInput) {
            // Extract index from ID: "bonus_ins_0", "bonus_ins_1", etc
            const idMatch = bonusInput.id.match(/bonus_ins_(\d+)/);
            if (!idMatch) return;
            
            const index = idMatch[1];
            
            // Look for the corresponding id_participation input in the same row
            const row = bonusInput.closest('tr');
            if (!row) return;
            
            // Find the hidden id_participation input
            const idParticipationInput = row.querySelector('input[name*="id_participation"]');
            
            if (idParticipationInput && idParticipationInput.value) {
                bonusData.push({
                    id_participation: idParticipationInput.value,
                    jetons_bonus_ins: parseInt(bonusInput.value) || 0
                });
            }
        });
        
        console.log('Participants found in table: ' + bonusData.length);
        console.log('Bonus data to save: ' + JSON.stringify(bonusData.slice(0, 3))); // Log first 3
        
        if (bonusData.length === 0) {
            console.log('No participant data found to save');
            return;
        }
        
        // Send AJAX request to save
        console.log('Sending AJAX POST to save...');
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'save_bonus_ins',
                data: bonusData
            })
        })
        .then(response => {
            console.log('Response received, status: ' + response.status);
            return response.json();
        })
        .then(result => {
            console.log('Result: ' + JSON.stringify(result));
            if (result && result.success) {
                console.log('✓ SUCCESS: ' + result.updated + ' rows updated in database');
                // Refresh bonus totals display after successful save
                setTimeout(updateAllBonusTotals, 100);
            } else {
                console.error('✗ FAILED:', result);
            }
        })
        .catch(error => {
            console.error('✗ FETCH ERROR:', error);
        });
        
    } catch (e) {
        console.error('Exception in saveCalculatedBonusToDatabase:', e);
    } finally {
        bonusSaveInProgress = false;
    }
}

// Exécuter à différents moments pour s'assurer que le DOM est prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', calculateBonusIns);
} else {
    calculateBonusIns();
}

// Appeler aussi après un délai pour les contenus chargés dynamiquement
setTimeout(calculateBonusIns, 1000);

// Note: MutationObserver disabled to prevent infinite loop
// If rows are added dynamically, we'd need a smarter approach
/*
if (window.MutationObserver) {
    const observer = new MutationObserver(calculateBonusIns);
    observer.observe(document.body, { childList: true, subtree: true });
}
*/

// Save a single row's jetons_total to database
function saveRowToDatabase(input) {
    try {
        const row = input.closest('tr');
        if (!row) return;
        
        // Find the participation ID for this row
        const idParticipationInput = row.querySelector('input[name*="id_participation"]');
        const jetonsInput = row.querySelector('input[name*="[jetons]"]');
        const bonusInsInput = row.querySelector('input[id^="bonus_ins_"]');
        const bonusArriveeInput = row.querySelector('input[name*="[jetons_bonus_arrivee]"]');
        
        if (!idParticipationInput || !jetonsInput || !bonusInsInput || !bonusArriveeInput) {
            return;
        }
        
        const jetons = parseInt(jetonsInput.value) || 0;
        const bonusIns = parseInt(bonusInsInput.value) || 0;
        const bonusArrivee = parseInt(bonusArriveeInput.value) || 0;
        const jetonsTotal = jetons + bonusIns + bonusArrivee;
        const idParticipation = idParticipationInput.value;
        
        console.log(`Saving row ${idParticipation}: jetons_total=${jetonsTotal}`);
        
        // Send AJAX request
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'save_jetons_total',
                id_participation: idParticipation,
                jetons_bonus_ins: bonusIns,
                jetons_bonus_arrivee: bonusArrivee,
                jetons_total: jetonsTotal
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result && result.success) {
                console.log('✓ Row saved successfully');
            } else {
                console.error('✗ Save failed:', result);
            }
        })
        .catch(error => {
            console.error('✗ Save error:', error);
        });
        
    } catch (e) {
        console.error('Error in saveRowToDatabase:', e);
    }
}
document.addEventListener('change', function(e) {
    if (e.target && e.target.id && e.target.id.startsWith('bonus_ins_')) {
        const val = parseInt(e.target.value) || 0;
        if (val > 5000) {
            e.target.value = 5000;
        }
        // Recalculate bonus total for this row
        updateBonusTotalForRow(e.target);
    }
}, true);

// Also listen for bonus_arrivee changes and save automatically
document.addEventListener('change', function(e) {
    if (e.target && e.target.name && e.target.name.includes('[jetons_bonus_arrivee]')) {
        updateBonusTotalForRow(e.target);
        // Save the updated jetons_total to database
        saveRowToDatabase(e.target);
    }
}, true);

// Update all bonus totals on the page
function updateAllBonusTotals() {
    document.querySelectorAll('[id^="bonus_ins_"]').forEach(function(bonusInput) {
        updateBonusTotalForRow(bonusInput);
    });
}

// Function to update bonus total display for a specific row
function updateBonusTotalForRow(input) {
    try {
        // Find the row containing this input
        const row = input.closest('tr');
        if (!row) return;
        
        // Find jetons, bonus_ins, bonus_arrivee inputs in this row
        const jetonsInput = row.querySelector('input[name*="[jetons]"]');
        const bonusInsInput = row.querySelector('input[id^="bonus_ins_"]');
        const bonusArriveeInput = row.querySelector('input[name*="[jetons_bonus_arrivee]"]');
        
        if (jetonsInput && bonusInsInput && bonusArriveeInput) {
            const jetons = parseInt(jetonsInput.value) || 0;
            const bonusIns = parseInt(bonusInsInput.value) || 0;
            const bonusArrivee = parseInt(bonusArriveeInput.value) || 0;
            const bonusTotal = jetons + bonusIns + bonusArrivee;
            
            // Find the bonus total cell (after bonus_arrivee)
            const bonusArriveeCell = bonusArriveeInput.closest('td');
            const bonusTotalCell = bonusArriveeCell.nextElementSibling;
            
            if (bonusTotalCell) {
                // Update the display
                bonusTotalCell.innerHTML = '<span class="sort-value" style="display: none;">' + bonusTotal + '</span><strong>' + bonusTotal.toLocaleString('fr-FR') + '</strong>';
            }
        }
    } catch (e) {
        console.error('Error updating bonus total:', e);
    }
}
