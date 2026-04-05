<?php
// IMPORTANT : PAS DE session_start() ICI !
// Ce fichier est inclus dans une page qui a déjà démarré la session.
// Le remettre provoquerait une Erreur 500 immédiate.

if(isset($_GET['uid'])) {
    $id = intval($_GET['uid']);
}
?>

<!-- Conteneur propre -->
<style>
    /* Conteneur principal du cercle - RESPONSIVE via vmin */
    .cardevent-circle-container {
        position: relative;
        width: 70vmin; /* Taille par défaut (Desktop/Paysage) */
        height: 70vmin;
        margin: 0 auto;
        display: flex;
        justify-content: center;
        align-items: center;
        touch-action: none; /* Désactive le scroll natif pour garantir le swipe JS */
    }

    /* Adaptation pour Mobile (Portrait) */
    @media (orientation: portrait) {
        .cardevent-circle-container {
            width: 90vmin; /* Plus grand sur mobile */
            height: 90vmin;
        }
    }

    /* SVG qui contient les cercles */
    .cardevent-svg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        transform: rotate(-90deg); /* Pour commencer en haut */
    }

    /* Cercle de fond (gris foncé) */
    .cardevent-bg {
        fill: none;
        stroke: #222;
        stroke-width: 15; /* Epaisseur du trait */
        pointer-events: none; /* Laisse passer les events au parent */
    }

    /* Cercle de progression (bleu cyan) */
    .cardevent-progress {
        fill: none;
        stroke: #00d2ff; /* Couleur cyan comme sur l'image */
        stroke-width: 15;
        stroke-linecap: round; /* Bouts arrondis */
        stroke-dasharray: 1131; /* 2 * PI * 180 (rayon) */
        stroke-dashoffset: 1131; /* Commence vide */
        transition: stroke-dashoffset 1s linear;
        filter: drop-shadow(0 0 10px #00d2ff); /* Effet néon */
        pointer-events: none; /* Laisse passer les events au parent */
    }

    /* Contenu central (Texte) */
    .cardevent-content {
        position: absolute;
        text-align: center;
        color: white;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 100%;
        height: 100%;
        z-index: 10;
    }

    /* Niveau (Haut) */
    #level-name {
        font-size: 4vmin;
        font-weight: 300;
        margin-bottom: 0,25vh;
        color: #ffffff;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    /* Heure (Centre) */
    #cardevent-display {
        font-size: 20vmin;
        font-weight: 700;
        line-height: 1;
        color: white;
        font-variant-numeric: tabular-nums; /* Chiffres de même largeur */
        transition: transform 0.3s ease; /* Animation fluide */
    }
    
    /* Effet au survol du conteneur */
    .cardevent-circle-container:hover #cardevent-display {
        transform: scale(1.1); /* Agrandissement */
        cursor: pointer;
    }
    
    #cardevent-display.paused {
        color: #ff0000; /* Rouge en pause */
        font-size: 10vmin;
    }

    /* Blindes (Bas) */
    #level-info {
        font-size: 8vmin;
        font-weight: 700;
        margin-top: 1vh;
        color: #ffc107;
    }
    #level-stack {
        font-size: 7vmin !important;
        font-weight: 500;
        margin-top: 0.5vh;
        color: #ffffff;
    }
    
    .ante-text {
        color: #00d2ff;
        font-size: 0.7em;
        display: block; /* Ante sur une nouvelle ligne ou bloc */
    }

    /* Info Pause (Sous le cercle) */
    #car-pause {
        margin-top: 20px;
        font-size: 3.5vmin;
        color: #ff0000; /* Rouge pour le texte de pause */
        text-align: center;
        font-weight: 300;
    }
</style>

<!-- Structure HTML Circulaire -->
<div class="cardevent-circle-container">
    <svg class="cardevent-svg" viewBox="0 0 400 400">
        <!-- Rayon 180, Centre 200,200 -->
        <circle class="cardevent-bg" cx="200" cy="200" r="180"></circle>
        <circle class="cardevent-progress" id="progress-circle" cx="200" cy="200" r="180"></circle>
    </svg>
    
    <div class="cardevent-content">
        <div id="level-name">Niveau --</div>
        <div id="cardevent-display">--:--</div>
        <div id="level-info">-- / --</div>
        <div id="level-stack" style="font-size: 7vmin !important; cursor: pointer;" onclick="announceCircleStack(event)" title="Cliquer pour annoncer le stack moyen"><span style="font-size: 5vmin;">Stack M.</span> <strong id="circle-stack-value" style="color:#ff0000; font-size: 7vmin;">--</strong></div>
        <div id="next-blinds" style="font-size: 3vmin; color:#ffc107; margin-top: 0.5vh;">-> <span id="next-blinds-value">--</span></div>
    </div>
</div>

<!-- Info Pause en dehors du cercle -->
<div id="car-pause"></div>

<!-- Bouton Bienvenue -->

<div style="margin-top: 10px; text-align: center; display: flex; justify-content: center; gap: 10px;">
    <button onclick="playWelcomeMessage()" style="cursor:pointer; font-size:14px; color:#00d2ff; background:rgba(0, 210, 255, 0.1); border:1px solid #00d2ff; padding: 5px 15px; border-radius: 20px;">
        <i class="fa fa-bullhorn"></i> Message Bienvenue
    </button>
    <button onclick="playRulesMessage()" style="cursor:pointer; font-size:14px; color:#ffc107; background:rgba(255, 193, 7, 0.1); border:1px solid #ffc107; padding: 5px 15px; border-radius: 20px;">
        <i class="fa fa-gavel"></i> Rappel Règles
    </button>
    <button onclick="playBlindsMessage()" style="cursor:pointer; font-size:14px; color:#4cd137; background:rgba(76, 209, 55, 0.1); border:1px solid #4cd137; padding: 5px 15px; border-radius: 20px;">
        <i class="fa fa-money"></i> Rappel Blindes
    </button>
	<button onclick="playSirenAlert()" style="cursor:pointer; font-size:14px; color:#e84118; background:rgba(232, 65, 24, 0.1); border:1px solid #e84118; padding: 5px 15px; border-radius: 20px;">
		<i class="fa fa-bell"></i> Alerte Sirène
	</button>
    <button id="pause-resume-btn" onclick="togglePause()" style="cursor:pointer; font-size:14px; color:#ff6b6b; background:rgba(255, 107, 107, 0.1); border:1px solid #ff6b6b; padding: 5px 15px; border-radius: 20px;">
        <i class="fa fa-pause"></i> Pause/Reprise
    </button>
</div>

<!-- BOUTONS DE TEST (Debug) -->
<div style="margin-top: 5px; opacity: 0.7; text-align: center;">
    <!-- <button onclick="manualTrigger()" style="cursor:pointer; font-size:10px; color:red; background:none; border:none;">
        🚨 Test Voix
    </button> -->
</div>

<script>

// --- LOGIQUE JS ---
// Variable globale pour la détection du swipe (priorité swipe sur pause)
let swipeDetected = false;

// Simulation manuelle
function manualTrigger() {
    document.dispatchEvent(new CustomEvent('trigger-alert'));
}
// Bouton alerte sirène : joue un son de sirène (Web Audio API)
function playSirenAlert() {
    try {
        // Web Audio API : sirène simple
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator();
        const g = ctx.createGain();
        o.type = 'sine';
        o.connect(g);
        g.connect(ctx.destination);
        let t = ctx.currentTime;
        // Sirène : monte et descend 3 fois
        for (let i = 0; i < 3; i++) {
            o.frequency.setValueAtTime(440, t + i*0.6);
            o.frequency.linearRampToValueAtTime(1760, t + i*0.3 + i*0.6);
            o.frequency.linearRampToValueAtTime(440, t + (i+1)*0.6);
        }
        g.gain.setValueAtTime(0.2, t);
        o.start(t);
        o.stop(t + 1.8);
        o.onended = () => ctx.close();
    } catch (e) {
        // Si Web Audio non supporté, fallback vocal
        if ('speechSynthesis' in window) {
            let utter = new SpeechSynthesisUtterance('Alerte sirène !');
            utter.lang = 'fr-FR';
            utter.rate = 0.8;
            window.speechSynthesis.speak(utter);
        } else {
            alert('Alerte sirène !');
        }
    }
}

// Message de Bienvenue Manuel
function playWelcomeMessage() {
    document.dispatchEvent(new CustomEvent('trigger-welcome'));
}

// Amorçage audio après interaction utilisateur pour contourner les blocages autoplay
let audioPrimed = false;
function primeAudioOnce() {
    if (audioPrimed) return;
    audioPrimed = true;

    const debugVoices = () => {
        try {
            if (typeof responsiveVoice !== 'undefined') {
                const v = responsiveVoice.getVoices();
                console.log('responsiveVoice loaded, voices:', v && v.length ? v.map(x => x.name || '').slice(0, 5) : 'none');
            } else {
                console.warn('responsiveVoice not available at prime time');
            }
        } catch (e) {
            console.warn('voice debug error', e);
        }
    };

    debugVoices();

    // Tenter un petit message pour débloquer l'audio
    const fallbackText = 'Audio activé';
    if (typeof responsiveVoice !== 'undefined') {
        try {
            responsiveVoice.speak(fallbackText, 'French Female');
            return;
        } catch (e) {
            console.warn('responsiveVoice prime failed', e);
        }
    }

    if ('speechSynthesis' in window) {
        const msg = new SpeechSynthesisUtterance(fallbackText);
        msg.lang = 'fr-FR';
        msg.rate = 0.95;
        try {
            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(msg);
        } catch (e) {
            console.warn('speechSynthesis prime failed', e);
        }
    } else {
        console.warn('No speechSynthesis available');
    }
}
// Message de rappel des blindes
function playBlindsMessage() {
    // On utilise les infos du niveau courant
    let sb = window.lastSB || '';
    let bb = window.lastBB || '';
    let durationMin = window.lastDurationMin || 0;
    let avgStack = window.lastAvgStack || '';
    let playersActive = window.lastPlayersActive || 0;
    let playersTotal = window.lastPlayersTotal || 0;
    
    let text = "Rappel : Les blinde actuelle sont, ";
    if (sb && bb && durationMin > 0) {
        text += `${sb} et ${bb}, pour des niveaux de ${durationMin} minutes.`;
    } else if (sb && bb) {
        text += `${sb} et ${bb}.`;
    } else if (durationMin > 0) {
        text += `Niveau de ${durationMin} minutes.`;
    } else {
        text += `non disponibles actuellement.`;
    }
    if (avgStack) {
        text += ` Le stack moyen est de ${avgStack}.`;
    }
    if (playersActive > 0 && playersTotal > 0) {
        text += ` Il reste ${playersActive} joueurs sur ${playersTotal}.`;
    }
    document.dispatchEvent(new CustomEvent('trigger-alert', { detail: { text: text } }));
}

// Message de rappel des règles
function playRulesMessage() {
    const rulesText = "Petit rappel des règles : pas de boisson ou nourriture sur les tables, on ne fume pas à l'intérieur, il y a des poubelles , donc on ne laisse rien traîner, un joueur debout à la première carte posée est out, seul le croupier touche les jetons, seul l'organisateur change les jetons sous surveillance. Bonne partie à tous !";
    document.dispatchEvent(new CustomEvent('trigger-alert', { detail: { text: rulesText } }));
}

// Fonction pour Pause/Reprise via bouton
function togglePause() {
    // Cette fonction sera appelée après DOMContentLoaded et aura accès aux variables
    document.dispatchEvent(new CustomEvent('toggle-pause-button'));
}

document.addEventListener('DOMContentLoaded', () => {
    // Récupération ID
    let uid = "<?php echo isset($id) ? $id : ''; ?>";
    if (!uid) uid = new URLSearchParams(window.location.search).get('uid');
    
    if (!uid) {
        document.getElementById('level-info').innerHTML = "Erreur ID";
        return;
    }

    const display = document.getElementById('cardevent-display');
    const info = document.getElementById('level-info');
    const levelNameDisplay = document.getElementById('level-name');
    const stackDisplay = document.getElementById('level-stack');
    const pauseInfo = document.getElementById('car-pause');
    const progressCircle = document.getElementById('progress-circle');
    
    // Circonférence pour le cercle (2 * PI * 180)
    const circumference = 2 * Math.PI * 180;
    
    let seconds = 0;
    let totalDuration = 0; // Durée totale du niveau en secondes
    window.isPaused = false; // Rendre accessible globalement
    let lastLevelId = null;
    let currentBlindsName = "";
    let pauseJustResumed = false; // Flag pour éviter le son quand on sort de pause
    let lastLevelAnnouncementTime = 0; // Timestamp du dernier annonce changement de niveau
    let lastElimAnnouncementTime = 0; // Anti-spam pour les annonces d'élimination
    let lastEliminationId = null; // Pour annonces nominatives
    let lastRecaveEliminationId = null; // Pour annonces recave
    // Pour rappel blindes
    window.lastSB = '';
    window.lastBB = '';
    window.lastAvgStack = '';
    window.lastDurationMin = 0;
    window.lastPlayersActive = 0;
    window.lastPlayersTotal = 0;

    // 2. FONCTION ALERTE VOCALE
    function playAlert(customText) {
        // Ne pas parler pendant les pauses
        if (window.isPaused) return;
        
        let textToSpeak = customText || "Changement de niveau. ";
        
        if (!customText) {
            if (currentBlindsName && currentBlindsName !== "default") {
                // Si c'est une pause (0/0, 0-0 ou contient PAUSE)
                if (currentBlindsName === "0/0" || currentBlindsName === "0-0" || currentBlindsName.toUpperCase().includes("PAUSE")) {
                    textToSpeak += "C'est la pause.";
                } else {
                    // Remplace le slash par " " pour une meilleure prononciation
                    // Ex: "100/200" devient "Blindes 100 200"
                    let blinds = currentBlindsName.replace(/\//g, ' et ').replace(/-/g, ' et ');
                    textToSpeak += "Blinde, " + blinds;
                }
            }
            // Ajouter le stack moyen
            if (window.lastAvgStack) {
                textToSpeak += `. Stack moyen : ${window.lastAvgStack}`;
            }
            // Ajouter le nombre de joueurs
            if (window.lastPlayersActive > 0 && window.lastPlayersTotal > 0) {
                textToSpeak += `. Il reste ${window.lastPlayersActive} joueurs sur ${window.lastPlayersTotal}`;
            }
        }
        
        console.log("Speaking: " + textToSpeak);
        if (typeof responsiveVoice !== 'undefined') {
            // Stratégie de sélection de voix V4 (Spécial iOS - Thomas/Amelie)
            var voice = "French Female"; // Valeur sûre par défaut
            var voices = responsiveVoice.getVoices();
            
            var foundFemale = false;
            var foundAmelie = null;
            var foundThomas = null;
            var foundFrench = null;
            
            for (var i = 0; i < voices.length; i++) {
                var v = voices[i];
                var name = v.name || "";
                var lang = v.lang || "";
                
                if (name === "French Female") foundFemale = true;
                if (name.indexOf("Amelie") !== -1) foundAmelie = name;
                if (name.indexOf("Thomas") !== -1) foundThomas = name;
                
                // Détection par code langue
                if (!foundFrench && (lang.indexOf("fr") === 0 || name.indexOf("French") !== -1)) {
                    foundFrench = name;
                }
            }
            
            if (foundFemale) voice = "French Female";
            else if (foundAmelie) voice = foundAmelie;
            else if (foundFrench) voice = foundFrench;
            else if (foundThomas) voice = foundThomas;
            
            console.log("Voix sélectionnée : " + voice);
            responsiveVoice.speak(textToSpeak, voice);
        } else {
            console.warn("ResponsiveVoice non chargé, fallback Web Speech");
            if ('speechSynthesis' in window) {
                const msg = new SpeechSynthesisUtterance(textToSpeak);
                msg.lang = 'fr-FR';
                msg.rate = 0.95;
                window.speechSynthesis.cancel();
                window.speechSynthesis.speak(msg);
            }
        }
    }
    document.addEventListener('trigger-alert', function(e) { 
        // Si l'event contient du texte (via detail.text), on l'utilise
        let txt = (e.detail && e.detail.text) ? e.detail.text : null;
        playAlert(txt); 
    });

    // Listener pour le message de bienvenue dynamique
    document.addEventListener('trigger-welcome', function() {
        let durationMin = Math.floor(totalDuration / 60);
        let blinds = currentBlindsName.replace(/\//g, ' et ').replace(/-/g, ' et ');
        
        let text = `Bienvenue à tous , le tournoi vient de commencer. Les blinde de départ sont ${blinds}, pour des niveaux de ${durationMin} minutes. Bonne chance !`;
        playAlert(text);
    });

    // Armer l'audio dès la première interaction utilisateur
    ['click','touchstart','keydown'].forEach(evt => {
        document.addEventListener(evt, primeAudioOnce, { once: true, passive: evt === 'touchstart' });
    });

    // Listener pour le bouton Pause/Reprise
    document.addEventListener('toggle-pause-button', function() {
        syncLocked = true;
        clearTimeout(unlockSyncTimeout);

        const script = window.isPaused ? 'de-pause.php' : 'en-pause.php';
        window.isPaused = !window.isPaused;
        
        // Si on annule la pause (resume), marquer qu'on vient de reprendre
        if (!window.isPaused) {
            pauseJustResumed = true;
            setTimeout(() => { pauseJustResumed = false; }, 2000); // Réinitialiser après 2 secondes
        }
        
        updateTimer();

        fetch(`${script}?act=${uid}&sou=ajax_ignore`)
            .then(() => console.log("Pause/Resume actionné"))
            .catch(err => console.error("Erreur Pause/Resume", err))
            .finally(() => {
                unlockSyncTimeout = setTimeout(() => { 
                    syncLocked = false; 
                    sync(); 
                }, 1500);
            });
    });

    // 3. MISE A JOUR AFFICHAGE (Chaque seconde)
    function updateTimer() {
        if (!window.isPaused && seconds > 0) {
            seconds--;
        }
        
        // Affichage Heure
        if (window.isPaused) {
            display.innerText = "PAUSE";
            display.classList.add('paused');
            display.style.color = ""; // Laisser la classe .paused gérer la couleur
        } else {
            let m = Math.floor(seconds / 60).toString().padStart(2, '0');
            let s = (seconds % 60).toString().padStart(2, '0');
            display.innerText = `${m}:${s}`;
            display.classList.remove('paused');
        }

        // Mise à jour du Cercle de Progression
        if (totalDuration > 0) {
            // Calcul du temps écoulé
            let elapsed = totalDuration - seconds;
            // Pourcentage d'avancement (0 à 1)
            let progress = elapsed / totalDuration;
            // Limites
            if (progress < 0) progress = 0;
            if (progress > 1) progress = 1;
            
            // Calcul de l'offset (Plein -> Vide ou Vide -> Plein ?)
            // L'image suggère une barre qui se remplit (bleu sur fond noir)
            // Si on veut qu'elle se remplisse : offset va de circumference à 0
            // Offset = circumference * (1 - progress)
            
            const offset = circumference * (1 - progress);
            progressCircle.style.strokeDashoffset = offset;

            // CHANGEMENT DE COULEUR (2 dernières minutes)
            if (seconds <= 120 && seconds > 0) {
                progressCircle.style.stroke = "#ff0000";
                progressCircle.style.filter = "drop-shadow(0 0 10px #ff0000)";
                display.style.color = "#ff0000";
            } else {
                progressCircle.style.stroke = "#00d2ff";
                progressCircle.style.filter = "drop-shadow(0 0 10px #00d2ff)";
                display.style.color = "#00d2ff";
            }
        } else {
            progressCircle.style.strokeDashoffset = circumference; // Vide si pas de durée
        }
    }

    // 4. SYNCHRONISATION API (Toutes les 5s)
    async function sync() {
        // Bloquer la sync si l'utilisateur interagit avec la molette
        if (typeof syncLocked !== 'undefined' && syncLocked) return;

        // Mémoriser l'état précédent des joueurs pour détecter les éliminations
        const prevPlayersActive = (typeof window.lastPlayersActive === 'number') ? window.lastPlayersActive : null;
        const prevPlayersTotal = (typeof window.lastPlayersTotal === 'number') ? window.lastPlayersTotal : null;
        const prevElimId = lastEliminationId;
        const prevRecaveElimId = lastRecaveEliminationId;

        try {
            const res = await fetch(`cardevent-api.php?uid=${uid}`);
            if (!res.ok) return;
            const data = await res.json();
            
            if (data.status === 'error') return;

            window.isPaused = data.is_paused;
            currentBlindsName = data.blinds_raw || "default";

            // Récupération SB/BB pour le rappel vocal
            if (data.blinds_raw && /^(\d{1,6})[-/](\d{1,6})$/.test(data.blinds_raw)) {
                let m = data.blinds_raw.match(/^(\d{1,6})[-/](\d{1,6})$/);
                window.lastSB = m[1];
                window.lastBB = m[2];
            } else {
                window.lastSB = '';
                window.lastBB = '';
            }
            if (data.duration_seconds) {
                totalDuration = parseInt(data.duration_seconds);
                window.lastDurationMin = Math.floor(totalDuration / 60);
            } else {
                window.lastDurationMin = 0;
            }
            window.lastAvgStack = data.avg_stack || '';
            // Mise à jour Nom du Niveau
            if (data.level_name) levelNameDisplay.innerText = data.level_name;
            else levelNameDisplay.innerText = "Niveau --";

            // Mise à jour Stack sous les blindes
            if (stackDisplay && data.avg_stack !== undefined) {
                stackDisplay.innerHTML = `<span style='color:white; font-size: 5vmin;'>Stack.M</span> <strong id='circle-stack-value' style='color:#ff0000; font-size: 5vmin;'>${data.avg_stack}</strong>`;
            }

            // Mise à jour des blindes suivantes (si disponibles)
            const nextBlindsSpan = document.getElementById('next-blinds-value');
            if (nextBlindsSpan) {
                // Essayer plusieurs clés possibles provenant de l'API
                let nextBlinds = data.next_blinds_text || data.next_blinds_raw || data.next_level_blinds || data.next_blinds || '';
                if (nextBlinds) {
                    // Uniformiser l'affichage
                    nextBlinds = String(nextBlinds).replace(/\//g, ' / ').replace(/-/g, ' / ');
                    nextBlindsSpan.textContent = nextBlinds;
                } else {
                    nextBlindsSpan.textContent = '--';
                }
            }

            // Mise à jour Stats Joueurs
            const statsZone = document.getElementById('zone-stats');
            if (statsZone && data.players_active !== undefined) {
                // Stocker les valeurs globalement pour la fonction playBlindsMessage
                window.lastPlayersActive = data.players_active;
                window.lastPlayersTotal = data.players_total;
                
                statsZone.innerHTML = `<a href="#" id="speak-active-players" data-players="${data.players_active}" style="color:#00d2ff; text-decoration:underline; cursor:pointer;">${data.players_active}</a> <a href="fullscreen-player.php?uid=${uid}" style="color:white; text-decoration:underline; cursor:pointer;">Joueurs</a> / ${data.players_total}`;

                // Ajout du handler pour l'audio sur le nombre de joueurs actifs (une seule fois)
                const activePlayersLink = document.getElementById('speak-active-players');
                if (activePlayersLink && !activePlayersLink.hasAttribute('data-listener')) {
                    activePlayersLink.setAttribute('data-listener', 'true');
                    activePlayersLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        const nb = this.getAttribute('data-players');
                        let msgJoueurs = `Il reste ${nb} joueurs en jeu.`;
                        if (typeof responsiveVoice !== 'undefined') {
                            responsiveVoice.speak(msgJoueurs, 'French Female');
                        } else if ('speechSynthesis' in window) {
                            let utter = new SpeechSynthesisUtterance(msgJoueurs);
                            utter.lang = 'fr-FR';
                            utter.rate = 0.95;
                            window.speechSynthesis.speak(utter);
                        } else {
                            alert(msgJoueurs);
                        }
                    });
                } else if (activePlayersLink) {
                    // Mise à jour du nombre de joueurs si le DOM est réécrit
                    activePlayersLink.setAttribute('data-players', data.players_active);
                }
                // ...existing code...
            }

            // Sync du temps restant
            if (!window.isPaused && Math.abs(seconds - data.seconds_remaining) > 2) {
                seconds = data.seconds_remaining;
            } else if (window.isPaused) {
                seconds = data.seconds_remaining;
            }

            // Affichage Blindes
            let txt = data.blinds_text;
            if (data.ante_text) txt += `<div class="ante-text">${data.ante_text}</div>`;
            info.innerHTML = txt;

            // Affichage Pause
            if (pauseInfo) {
                let pVal = data.next_pause || "";
                let displayText = "";
                let isBreak = (data.blinds_raw === "0/0" || data.blinds_raw === "0-0" || data.blinds_text === "PAUSE");
                
                if (isBreak && seconds > 0) {
                    let d = new Date();
                    d.setSeconds(d.getSeconds() + seconds);
                    let endH = d.getHours().toString().padStart(2, '0');
                    let endM = d.getMinutes().toString().padStart(2, '0');
                    displayText = `Reprise du jeu : ${endH}:${endM}`;
                } 
                else if (pVal) {
                    let matchAbs = pVal.match(/^(\d{1,2}):(\d{2})/);
                    let isRelative = pVal.toLowerCase().includes("dans");

                    let minutesPauseLinkHtml = null;
                    if (matchAbs) {
                        let h = parseInt(matchAbs[1]);
                        let m = parseInt(matchAbs[2]);
                        let now = new Date();
                        let target = new Date();
                        target.setHours(h); target.setMinutes(m); target.setSeconds(0);
                        let diffMs = target - now;
                        let diffMins = Math.max(0, Math.floor(diffMs / 60000));
                        minutesPauseLinkHtml = `<a href='#' id='speak-minutes-pause' data-minutes='${diffMins}' style='color:#00d2ff; text-decoration:underline; cursor:pointer;'>${diffMins}</a>`;
                        displayText = `Pause <span style='color:white'>dans</span> ${minutesPauseLinkHtml} <span style='color:white'>Minutes, Soit</span> ${h.toString().padStart(2,'0')}h${m.toString().padStart(2,'0')}`;
                    } 
                    else if (isRelative) {
                        let matchH = pVal.match(/(\d+)\s*h/);
                        let matchM = pVal.match(/(\d+)\s*m/);
                        let addH = matchH ? parseInt(matchH[1]) : 0;
                        let addM = matchM ? parseInt(matchM[1]) : 0;
                        let totalMinutes = (addH * 60) + addM;
                        let d = new Date();
                        d.setHours(d.getHours() + addH); d.setMinutes(d.getMinutes() + addM);
                        let pauseH = d.getHours().toString().padStart(2, '0');
                        let pauseM = d.getMinutes().toString().padStart(2, '0');
                        minutesPauseLinkHtml = `<a href='#' id='speak-minutes-pause' data-minutes='${totalMinutes}' style='color:#00d2ff; text-decoration:underline; cursor:pointer;'>${totalMinutes}</a>`;
                        displayText = `Pause <span style='color:white'>dans</span> ${minutesPauseLinkHtml} <span style='color:white'>Minutes, Soit</span> ${pauseH}h${pauseM}`;
                    } else {
                        displayText = pVal;
                    }
                }
                pauseInfo.innerHTML = displayText;
                // Ajout du handler pour l'audio sur les minutes restantes avant la pause
                const minutesPauseLink = document.getElementById('speak-minutes-pause');
                if (minutesPauseLink && !minutesPauseLink.hasAttribute('data-listener')) {
                    minutesPauseLink.setAttribute('data-listener', 'true');
                    // Cherche l'heure de la pause dans le texte parent
                    minutesPauseLink.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const min = this.getAttribute('data-minutes');
                        // Recherche l'heure juste après "Soit"
                        let parent = this.parentElement;
                        let msgHeure = '';
                        if (parent) {
                            // Cherche le texte après 'Soit' jusqu'à la fin du span ou balise
                            let match = parent.innerHTML.match(/Soit\s*([^< ]+h[^< ]*)/);
                            if (match) {
                                msgHeure = match[1];
                            } else {
                                // Fallback : cherche le dernier nombre h nombre dans le parent
                                let match2 = parent.innerHTML.match(/(\d{2}h\d{2})/g);
                                if (match2 && match2.length > 0) {
                                    msgHeure = match2[match2.length-1];
                                }
                            }
                        }
                        // Construire le message avec une pause claire entre les parties
                        let firstPart = msgHeure ? `Il reste , ${min} minutes avant la prochaine pause, estimée à ${msgHeure}.` : `Il reste, ${min} minutes avant la prochaine pause.`;
                        // Conserver l'indication "plus trois mains" si pertinente
                        let plusTrois = '';
                        if (parent && /plus\s+trois\s+mains/i.test(parent.innerHTML)) plusTrois = ' Plus trois mains.';
                        // Heure actuelle
                        let now = new Date();
                        let hh = now.getHours().toString().padStart(2, '0');
                        let mm = now.getMinutes().toString().padStart(2, '0');
                        let secondPart = `Il est actuellement ${hh} heures ${mm} minutes.`;

                        // Message final : deux phrases séparées pour une bonne pause vocale
                        let msgPause = `${firstPart}${plusTrois} ${secondPart}`;
                        if (typeof responsiveVoice !== 'undefined') {
                            responsiveVoice.speak(msgPause, 'French Female');
                        } else if ('speechSynthesis' in window) {
                            let utter = new SpeechSynthesisUtterance(msgPause);
                            utter.lang = 'fr-FR';
                            utter.rate = 0.95;
                            window.speechSynthesis.speak(utter);
                        } else {
                            alert(msgPause);
                        }
                    });
                } else if (minutesPauseLink) {
                    // Mise à jour du nombre de minutes si le DOM est réécrit
                    minutesPauseLink.setAttribute('data-minutes', minutesPauseLink.textContent);
                }
            }

            // Son au changement de niveau
            const now = Date.now();
            if (lastLevelId !== null && lastLevelId !== data.level_id && data.level_id !== 0 && !window.isPaused && !pauseJustResumed && (now - lastLevelAnnouncementTime) > 5000) {
                playAlert();
                lastLevelAnnouncementTime = now;
            }

            lastLevelId = data.level_id;

            // Enregistrer l'id de la dernière élimination reçue
            if (data.last_elimination_id !== undefined) {
                lastEliminationId = data.last_elimination_id;
            }
            if (data.last_recave_elimination_id !== undefined) {
                lastRecaveEliminationId = data.last_recave_elimination_id;
            }

            // Annonce audio lorsqu'un ou plusieurs joueurs sont éliminés entre deux synchronisations
            const prevActive = prevPlayersActive;
            const newActive = (data.players_active !== undefined) ? data.players_active : null;
            if (
                prevActive !== null && newActive !== null && newActive >= 0 && newActive < prevActive && !window.isPaused && !pauseJustResumed
            ) {
                const eliminatedCount = prevActive - newActive;
                const totalPlayers = data.players_total || prevPlayersTotal || newActive;
                const nowTs = Date.now();

                // Empêcher les doublons trop rapprochés (1s)
                if (nowTs - lastElimAnnouncementTime > 1000) {
                    // Message nominatif si on a les noms et un nouvel ID d'élimination
                    let elimMsg;
                    const killer = (data.last_elimination_killer || '').split('.')[0];
                    const victim = (data.last_elimination_victim || '').split('.')[0];
                    const hasNames = killer && victim && data.last_elimination_id && data.last_elimination_id !== prevElimId;

                    if (hasNames) {
                        elimMsg = `${killer} a éliminé ${victim} (élimination définitive). Il reste ${newActive} joueurs`;
                    } else {
                        elimMsg = eliminatedCount === 1
                            ? 'Un joueur a été éliminé (élimination définitive).'
                            : `${eliminatedCount} joueurs ont été éliminés (élimination définitive).`;
                        elimMsg += ` Il reste ${newActive} joueurs`;
                    }
                    if (totalPlayers) {
                        elimMsg += ` sur ${totalPlayers}`;
                    }
                    elimMsg += '.';

                    // Annonce vocale des éliminations désactivée ici pour éviter un doublon
                    // avec fullscreen-player.php qui gère désormais la voix (avec membres.phonetique).
                    console.log('[Timer] Elimination détectée (sans annonce vocale) : ' + elimMsg);
                    lastElimAnnouncementTime = nowTs;
                }
            }

            // Annonce pour une recave (élimination non définitive) détectée par nouvel id
            if (
                data.last_recave_elimination_id && data.last_recave_elimination_id !== prevRecaveElimId && !window.isPaused && !pauseJustResumed
            ) {
                const killer = (data.last_recave_elimination_killer || '').split('.')[0];
                const victim = (data.last_recave_elimination_victim || '').split('.')[0];
                let recaveMsg = '';
                if (killer && victim) {
                    recaveMsg = `${killer} a éliminé ${victim}, qui recave.`;
                } else {
                    recaveMsg = `Un joueur a été éliminé mais recave.`;
                }
                // Annonce vocale des recaves désactivée ici pour éviter un doublon
                // avec fullscreen-player.php qui gère désormais la voix (avec membres.phonetique).
                console.log('[Timer] Recave détectée (sans annonce vocale) : ' + recaveMsg);
            }

        } catch (e) { console.error(e); }
    }

    setInterval(updateTimer, 1000);
    setInterval(sync, 5000);
    sync();

    // --- GESTION DE LA MOLETTE (SOURIS) ---
    let syncLocked = false;
    let scrollTimeout;
    let pendingMinutes = 0;
    let unlockSyncTimeout;

    const container = document.querySelector('.cardevent-circle-container');
    
    if (container) {
            // Variable pour la détection du swipe circulaire (priorité swipe sur pause)
            let swipeDetected = false;
        // Gestion molette inchangée
        container.addEventListener('wheel', (e) => {
            e.preventDefault();
            
            // 1. Bloquer la synchro serveur pour éviter les sauts
            syncLocked = true;
            clearTimeout(unlockSyncTimeout);
            
            // 2. Déterminer le sens (Haut = Ajout, Bas = Retrait)
            // deltaY < 0 : Scroll vers le haut
            const direction = e.deltaY < 0 ? 1 : -1;
            
            // 3. Accumuler les minutes à envoyer
            pendingMinutes += direction;
            
            // 4. Mise à jour visuelle immédiate (Feedback)
            seconds += direction * 60;
            if (seconds < 0) seconds = 0;
            updateTimer();
            
            // 5. Debounce : Envoyer la requête quand le scroll s'arrête (500ms)
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                if (pendingMinutes !== 0) {
                    // Envoi de la requête AJAX (sans recharger la page)
                    // On utilise 'ajax_ignore' pour le paramètre 'sou' car on ignore la réponse
                    fetch(`modif-horloge.php?act=${uid}&min=${pendingMinutes}&sou=ajax_ignore`)
                        .then(() => {
                            console.log(`Temps mis à jour : ${pendingMinutes} min`);
                            pendingMinutes = 0;
                        })
                        .catch(err => console.error("Erreur update temps", err));
                }
                
                // Réactiver la synchro après 2 secondes (laisser le temps au serveur de traiter)
                unlockSyncTimeout = setTimeout(() => { 
                    syncLocked = false; 
                    sync(); // Forcer une sync pour être sûr d'être calé
                }, 2000);
                
            }, 500);
        }, { passive: false });

        // --- GESTION DU CLIC (PAUSE/RESUME) ---
        container.addEventListener('click', (e) => {
            // Ignorer si le clic vient du stack (annonce audio uniquement)
            if (e.target.closest('#level-stack')) {
                return;
            }
            
            // Ignorer si c'est un swipe qui a généré ce clic
            if (swipeDetected) {
                swipeDetected = false;
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            syncLocked = true;
            clearTimeout(unlockSyncTimeout);

            const script = window.isPaused ? 'de-pause.php' : 'en-pause.php';
            window.isPaused = !window.isPaused;
            
            // Si on annule la pause (resume), marquer qu'on vient de reprendre
            if (!window.isPaused) {
                pauseJustResumed = true;
                setTimeout(() => { pauseJustResumed = false; }, 2000); // Réinitialiser après 2 secondes
            }
            
            updateTimer();

            fetch(`${script}?act=${uid}&sou=ajax_ignore`)
                .then(() => console.log("Pause/Resume actionné"))
                .catch(err => console.error("Erreur Pause/Resume", err))
                .finally(() => {
                    unlockSyncTimeout = setTimeout(() => { 
                        syncLocked = false; 
                        sync(); 
                    }, 1500);
                });
        });

        // --- GESTION DU SWIPE TACTILE (TOUCH) ---
        let touchStartY = null;
        let touchStartTarget = null;
        let swipeActive = false;
        let touchStartAngle = null;
        let lastAngle = null;
        // Délégation : on écoute sur le parent, mais on accepte tout enfant (SVG, cercles, etc)
        container.addEventListener('touchstart', function(e) {
            if (e.touches.length !== 1) return;
            const rect = container.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const x = e.touches[0].clientX - centerX;
            const y = e.touches[0].clientY - centerY;
            // Correction de la rotation -90deg du cercle : on soustrait PI/2
            touchStartAngle = Math.atan2(y, x) - Math.PI / 2;
            lastAngle = touchStartAngle;
            touchStartY = e.touches[0].clientY;
            touchStartTarget = e.target;
            swipeActive = true;
            swipeDetected = false;
        }, { passive: true });

        document.addEventListener('touchmove', function(e) {
            if (!swipeActive || touchStartY === null) return;
                swipeDetected = true;
            const rect = container.getBoundingClientRect();
            const centerX = rect.left + rect.width / 2;
            const centerY = rect.top + rect.height / 2;
            const x = e.touches[0].clientX - centerX;
            const y = e.touches[0].clientY - centerY;
            // Correction de la rotation -90deg du cercle : on soustrait PI/2
            const angle = Math.atan2(y, x) - Math.PI / 2;
            // Calcul de la différence d'angle (en radians)
            let deltaAngle = angle - lastAngle;
            // Corriger le passage -PI/+PI
            if (deltaAngle > Math.PI) deltaAngle -= 2 * Math.PI;
            if (deltaAngle < -Math.PI) deltaAngle += 2 * Math.PI;
            // Seuil : chaque 15° (PI/12) = 1 minute
            const angleStep = Math.PI / 12;
            if (Math.abs(deltaAngle) >= angleStep) {
                // Bloquer la synchro serveur pour éviter les sauts
                syncLocked = true;
                clearTimeout(unlockSyncTimeout);
                let steps = Math.trunc(deltaAngle / angleStep);
                // Sens horaire (angle augmente) = -minutes, antihoraire = +minutes
                pendingMinutes -= steps;
                seconds -= steps * 60;
                if (seconds < 0) seconds = 0;
                updateTimer();
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    if (pendingMinutes !== 0) {
                        fetch(`modif-horloge.php?act=${uid}&min=${pendingMinutes}&sou=ajax_ignore`)
                            .then(() => {
                                console.log(`Temps mis à jour (touch) : ${pendingMinutes} min`);
                                pendingMinutes = 0;
                            })
                            .catch(err => console.error("Erreur update temps (touch)", err));
                    }
                    unlockSyncTimeout = setTimeout(() => {
                        syncLocked = false;
                        sync();
                    }, 2000);
                }, 500);
                // Réinitialiser la référence pour suivre le doigt
                lastAngle += steps * angleStep;
            }
        }, { passive: false });
        document.addEventListener('touchend', function(e) {
            touchStartY = null;
            touchStartTarget = null;
            swipeActive = false;
        }, { passive: true });

        // Réinitialisation si le système interrompt le swipe
        document.addEventListener('touchcancel', function(e) {
            touchStartY = null;
            touchStartTarget = null;
            swipeActive = false;
        }, { passive: true });
    }
});

// Fonction d'annonce vocale pour le stack du cercle
function announceCircleStack(event) {
    // Empêcher la propagation pour ne pas déclencher le toggle pause
    if (event) {
        event.stopPropagation();
        event.preventDefault();
    }
    
    var stackElement = document.getElementById('circle-stack-value');
    if (!stackElement) return;
    
    // Récupérer la valeur numérique (sans espaces)
    var stackText = stackElement.innerText.replace(/\s/g, '');
    var stackValue = parseInt(stackText);
    
    if (isNaN(stackValue) || stackValue <= 0) return;
    
    // Formater le nombre pour l'annonce vocale
    var announcement = "Stack moyen : " + stackValue.toLocaleString('fr-FR');
    
    // Utiliser ResponsiveVoice si disponible
    if (typeof responsiveVoice !== 'undefined') {
        responsiveVoice.speak(announcement, "French Female", {
            rate: 0.9,
            pitch: 1,
            volume: 1
        });
    } else {
        // Fallback sur l'API native si ResponsiveVoice n'est pas disponible
        if ('speechSynthesis' in window) {
            var msg = new SpeechSynthesisUtterance(announcement);
            msg.lang = 'fr-FR';
            msg.rate = 0.9;
            window.speechSynthesis.speak(msg);
        }
    }
    
    console.log('Stack moyen du cercle annoncé : ' + stackValue);
}
</script>
