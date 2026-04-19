<?php
session_start();
if (empty($_SESSION['login'])) {
    $_SESSION['redirect'] = 'panel/local-timer.php';
    header('location:logout.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Local Timer</title>
    <style>
        :root {
            --bg: #06090f;
            --card: rgba(18, 24, 38, 0.94);
            --border: rgba(255, 255, 255, 0.08);
            --text: #f4f7fb;
            --muted: #98a3b3;
            --accent: #00d2ff;
            --accent-2: #ffc107;
            --danger: #ff5b5b;
            --success: #22c55e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top, rgba(0, 210, 255, 0.18), transparent 34%),
                linear-gradient(180deg, #09111f 0%, #05070c 100%);
            display: flex;
            justify-content: center;
            padding: 24px 16px 110px;
        }
        .page {
            width: 100%;
            max-width: 760px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: 0 18px 60px rgba(0, 0, 0, 0.35);
        }
        .hero {
            padding: 28px;
            text-align: center;
        }
        .eyebrow {
            font-size: 12px;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: var(--accent-2);
            font-weight: 700;
        }
        .time {
            font-size: clamp(72px, 18vw, 148px);
            line-height: 1;
            font-weight: 900;
            letter-spacing: -0.04em;
            margin: 18px 0 12px;
            color: var(--accent);
            text-shadow: 0 0 28px rgba(0, 210, 255, 0.22);
        }
        .meta {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
        }
        .pill {
            border: 1px solid var(--border);
            background: rgba(255,255,255,.03);
            border-radius: 999px;
            padding: 10px 14px;
            font-size: 14px;
            color: var(--muted);
        }
        .pill strong { color: var(--text); }
        .controls, .quick-adjust, .levels {
            margin-top: 18px;
            display: grid;
            gap: 12px;
        }
        .controls { grid-template-columns: repeat(3, 1fr); }
        .quick-adjust { grid-template-columns: repeat(4, 1fr); }
        .levels { grid-template-columns: repeat(2, 1fr); }
        button {
            border: 0;
            border-radius: 16px;
            padding: 14px 16px;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            color: var(--text);
            background: #121a29;
            transition: transform .12s ease, opacity .12s ease, background .12s ease;
        }
        button:hover { transform: translateY(-1px); }
        button.primary { background: linear-gradient(135deg, #00c2ff, #0078ff); }
        button.warning { background: linear-gradient(135deg, #ffb800, #ff7a00); }
        button.danger { background: linear-gradient(135deg, #ff6767, #ff2d55); }
        button.success { background: linear-gradient(135deg, #2bd576, #16a34a); }
        .secondary-card {
            margin-top: 20px;
            padding: 22px;
        }
        .section-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .14em;
            color: var(--accent-2);
            font-weight: 700;
            margin-bottom: 14px;
        }
        .status {
            margin-top: 14px;
            color: var(--muted);
            text-align: center;
            font-size: 14px;
        }
        .bottom-nav-backdrop {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            height: 86px;
            background: rgba(2, 4, 8, 0.96);
            border-top: 1px solid rgba(255,255,255,.08);
            backdrop-filter: blur(16px);
        }
        .bottom-nav {
            position: fixed;
            left: 12px;
            right: 12px;
            bottom: 12px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            z-index: 10;
        }
        .bottom-nav button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            padding: 14px 10px;
            font-size: 14px;
            font-weight: 700;
        }
        .bottom-nav button.active {
            color: var(--accent);
            border-color: rgba(0,210,255,.35);
            background: rgba(0,210,255,.08);
        }
        @media (max-width: 640px) {
            .controls, .quick-adjust, .levels { grid-template-columns: repeat(2, 1fr); }
            .hero { padding: 22px 16px; }
            .secondary-card { padding: 18px 16px; }
        }
    </style>
</head>
<body>
    <div class="page">
        <section class="card hero">
            <div class="eyebrow">Timer autonome</div>
            <div class="time" id="timer-display">15:00</div>
            <div class="meta">
                <div class="pill"><strong id="level-label">Niveau 1</strong></div>
                <div class="pill">Blindes <strong id="blinds-label">25 / 50</strong></div>
                <div class="pill">Suivant <strong id="next-label">50 / 100</strong></div>
            </div>
            <div class="status" id="timer-status">Prêt à démarrer</div>
            <div class="controls">
                <button class="primary" id="start-pause-btn">Démarrer</button>
                <button class="warning" id="reset-btn">Reset</button>
                <button class="success" id="next-level-btn">Niveau suivant</button>
            </div>
        </section>

        <section class="card secondary-card">
            <div class="section-title">Ajustement rapide</div>
            <div class="quick-adjust">
                <button id="minus-60">- 1 min</button>
                <button id="minus-120">- 2 min</button>
                <button id="plus-60">+ 1 min</button>
                <button id="plus-120">+ 2 min</button>
            </div>
        </section>

        <section class="card secondary-card">
            <div class="section-title">Navigation des blindes</div>
            <div class="levels">
                <button id="prev-level-btn">Niveau précédent</button>
                <button id="restart-level-btn">Rejouer le niveau</button>
            </div>
        </section>
    </div>

    <div class="bottom-nav-backdrop" aria-hidden="true"></div>
    <nav class="bottom-nav" role="navigation" aria-label="Main navigation">
        <button type="button" onclick="window.location.href='/panel/quickview.php';">
            <span>Accueil</span>
        </button>
        <button type="button" class="active">
            <span>Local Timer</span>
        </button>
        <button type="button" onclick="window.location.href='/panel/repartition.php';">
            <span>Répartition</span>
        </button>
    </nav>

    <script>
        const levels = [
            { sb: 25, bb: 50, duration: 15 * 60 },
            { sb: 50, bb: 100, duration: 15 * 60 },
            { sb: 100, bb: 200, duration: 15 * 60 },
            { sb: 200, bb: 400, duration: 15 * 60 },
            { sb: 300, bb: 600, duration: 15 * 60 },
            { sb: 400, bb: 800, duration: 15 * 60 },
            { sb: 500, bb: 1000, duration: 15 * 60 },
            { sb: 1000, bb: 2000, duration: 15 * 60 }
        ];

        let currentLevel = 0;
        let timeLeft = levels[0].duration;
        let timerId = null;
        let isRunning = false;

        const display = document.getElementById('timer-display');
        const status = document.getElementById('timer-status');
        const levelLabel = document.getElementById('level-label');
        const blindsLabel = document.getElementById('blinds-label');
        const nextLabel = document.getElementById('next-label');
        const startPauseBtn = document.getElementById('start-pause-btn');

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
            const secs = Math.max(0, seconds % 60).toString().padStart(2, '0');
            return mins + ':' + secs;
        }

        function syncUi() {
            const current = levels[currentLevel];
            const next = levels[currentLevel + 1] || current;
            display.textContent = formatTime(timeLeft);
            levelLabel.textContent = 'Niveau ' + (currentLevel + 1);
            blindsLabel.textContent = current.sb + ' / ' + current.bb;
            nextLabel.textContent = next.sb + ' / ' + next.bb;
            display.style.color = timeLeft <= 120 ? '#ff5b5b' : '#00d2ff';
            status.textContent = isRunning ? 'Timer en cours' : 'Timer en pause';
            startPauseBtn.textContent = isRunning ? 'Pause' : 'Démarrer';
        }

        function stopTimer() {
            if (timerId) {
                clearInterval(timerId);
                timerId = null;
            }
            isRunning = false;
            syncUi();
        }

        function startTimer() {
            if (timerId) return;
            isRunning = true;
            syncUi();
            timerId = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft -= 1;
                    syncUi();
                    return;
                }
                if (currentLevel < levels.length - 1) {
                    currentLevel += 1;
                    timeLeft = levels[currentLevel].duration;
                    syncUi();
                    return;
                }
                stopTimer();
                status.textContent = 'Structure terminée';
            }, 1000);
        }

        function resetLevel() {
            timeLeft = levels[currentLevel].duration;
            stopTimer();
            status.textContent = 'Niveau réinitialisé';
            syncUi();
        }

        function changeLevel(delta, restartOnly) {
            if (!restartOnly) {
                currentLevel = Math.max(0, Math.min(levels.length - 1, currentLevel + delta));
            }
            timeLeft = levels[currentLevel].duration;
            stopTimer();
            status.textContent = restartOnly ? 'Niveau relancé' : 'Niveau changé';
            syncUi();
        }

        function adjustTime(delta) {
            timeLeft = Math.max(0, timeLeft + delta);
            syncUi();
        }

        startPauseBtn.addEventListener('click', () => {
            if (isRunning) {
                stopTimer();
                return;
            }
            startTimer();
        });
        document.getElementById('reset-btn').addEventListener('click', resetLevel);
        document.getElementById('next-level-btn').addEventListener('click', () => changeLevel(1, false));
        document.getElementById('prev-level-btn').addEventListener('click', () => changeLevel(-1, false));
        document.getElementById('restart-level-btn').addEventListener('click', () => changeLevel(0, true));
        document.getElementById('minus-60').addEventListener('click', () => adjustTime(-60));
        document.getElementById('minus-120').addEventListener('click', () => adjustTime(-120));
        document.getElementById('plus-60').addEventListener('click', () => adjustTime(60));
        document.getElementById('plus-120').addEventListener('click', () => adjustTime(120));

        syncUi();
    </script>
</body>
</html>
