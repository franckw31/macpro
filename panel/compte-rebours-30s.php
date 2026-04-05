<?php
// Compte à rebours 30 secondes pour l'onglet outils
?>
<style>
    #countdown-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 20px 0;
        padding: 30px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    #countdown-cardevent {
        font-size: 72px;
        font-weight: bold;
        color: #fff;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        font-family: 'Arial', sans-serif;
        min-height: 90px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 20px 0;
    }

    #countdown-label {
        font-size: 20px;
        color: #fff;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .countdown-buttons {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 20px;
    }

    .btn-countdown {
        padding: 12px 30px;
        font-size: 16px;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    .btn-start {
        background-color: #4CAF50;
        color: white;
    }

    .btn-start:hover:not(:disabled) {
        background-color: #45a049;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-start:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
    }

    .btn-reset {
        background-color: #f44336;
        color: white;
    }

    .btn-reset:hover {
        background-color: #da190b;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-stop {
        background-color: #ff9800;
        color: white;
    }

    .btn-stop:hover:not(:disabled) {
        background-color: #e68900;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-stop:disabled {
        background-color: #cccccc;
        cursor: not-allowed;
    }

    #countdown-cardevent.warning {
        color: #ffeb3b;
        animation: pulse 0.5s infinite;
    }

    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
</style>

<div id="countdown-container">
    <div id="countdown-label">⏱️ Compte à rebours</div>
    <div id="countdown-cardevent">30</div>
    <div class="countdown-buttons">
        <button class="btn-countdown btn-start" id="btn-start">▶ START</button>
        <button class="btn-countdown btn-stop" id="btn-stop" disabled>⏸ STOP</button>
        <button class="btn-countdown btn-reset" id="btn-reset">🔄 RESET</button>
    </div>
</div>

<script>
    let countdownTime = 30;
    let countdownTimer = null;
    let isRunning = false;
    
    const timerDisplay = document.getElementById('countdown-cardevent');
    const btnStart = document.getElementById('btn-start');
    const btnStop = document.getElementById('btn-stop');
    const btnReset = document.getElementById('btn-reset');

    // Fonction pour jouer le son d'alarme
    function playAlarm() {
        let alarmSound = new Audio('/newtimer/end.mp3');
        alarmSound.load();
        alarmSound.play();
        
        // Prononcé un message aussi
        if (typeof responsiveVoice !== 'undefined') {
            responsiveVoice.speak("Temps écoulé!", "French Female");
        }
    }

    // Mettre à jour l'affichage
    function updateDisplay() {
        timerDisplay.textContent = countdownTime;
        
        // Ajouter une animation quand on approche de 0
        if (countdownTime <= 5 && countdownTime > 0) {
            timerDisplay.classList.add('warning');
        } else {
            timerDisplay.classList.remove('warning');
        }
    }

    // Démarrer le compte à rebours
    btnStart.addEventListener('click', function() {
        if (!isRunning && countdownTime > 0) {
            isRunning = true;
            btnStart.disabled = true;
            btnStop.disabled = false;
            btnReset.disabled = true;
            
            countdownTimer = setInterval(function() {
                countdownTime--;
                updateDisplay();
                
                if (countdownTime <= 0) {
                    clearInterval(countdownTimer);
                    isRunning = false;
                    timerDisplay.textContent = '0';
                    timerDisplay.classList.add('warning');
                    playAlarm();
                    
                    btnStart.disabled = true;
                    btnStop.disabled = true;
                    btnReset.disabled = false;
                }
            }, 1000);
        }
    });

    // Arrêter le compte à rebours
    btnStop.addEventListener('click', function() {
        if (isRunning) {
            clearInterval(countdownTimer);
            isRunning = false;
            btnStart.disabled = false;
            btnStop.disabled = true;
            btnReset.disabled = false;
        }
    });

    // Réinitialiser le compte à rebours
    btnReset.addEventListener('click', function() {
        clearInterval(countdownTimer);
        isRunning = false;
        countdownTime = 30;
        updateDisplay();
        timerDisplay.classList.remove('warning');
        btnStart.disabled = false;
        btnStop.disabled = true;
        btnReset.disabled = false;
    });

    // Initialisation
    updateDisplay();
</script>
