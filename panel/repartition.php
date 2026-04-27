<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

$asset_ver = @filemtime(__DIR__ . '/timer_web/public/style.variantA.css') ?: @filemtime(__DIR__ . '/timer_web/public/style.css') ?: time();
?>
<!doctype html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>CardEvent - Répartition</title>
	<link rel="stylesheet" href="/panel/timer_web/public/style.css?v=<?php echo $asset_ver; ?>">
	<link id="theme-stylesheet" rel="stylesheet" href="/panel/timer_web/public/style.variantA.css?v=<?php echo $asset_ver; ?>">
	<style>
	body{background: linear-gradient(180deg, rgba(0,0,0,0.36) 0%, rgba(0,0,0,0.24) 100%), url('/panel/images/bg.png?v=<?php echo $asset_ver; ?>') center/cover no-repeat; background-blend-mode:overlay;}
	body.bg-small{background-image:none !important;background-color:#000 !important;background-blend-mode:normal !important}
	.card{margin:16px auto;max-width:760px;}
	.calc-field{width:100%;max-width:135px;padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,0.15);background:rgba(0,0,0,0.35);color:#fff;font-weight:700;text-align:center;font-size:16px;}
	</style>
</head>
<body>
	<div class="app-shell" style="color:#fff;padding:14px;">
		<div style="display:flex;align-items:center;justify-content:space-between;max-width:760px;margin:auto;">
			<div>
				<div style="font-size:30px;font-weight:700;color:var(--blue);">CardEvent v2.0</div>
				<div style="font-size:16px;opacity:.85">Répartition du prizepool</div>
			</div>
		</div>

		<section class="card stroked" style="margin-top:16px;padding:16px;background:rgba(0,0,0,0.45);border:1px solid rgba(0,172,255,.45);">
			<div style="font-weight:700;color:var(--blue);text-transform:uppercase;font-size:13px;margin-bottom:8px">Répartition du Prizepool</div>
			<div style="display:flex;flex-wrap:nowrap;gap:10px;align-items:flex-end;justify-content:flex-start;">
				<div style="flex:0 0 80px;"><label style="font-size:14px;display:block;margin-bottom:6px;color:#fff;">Prizepool (€)</label><input type="number" id="pricepool" class="calc-field" min="0" value="670" step="1" /></div>
				<div style="flex:0 0 80px;"><label style="font-size:14px;display:block;margin-bottom:6px;color:#fff;">Nb Buy-Rebuy</label><input type="number" id="buyrebuy" class="calc-field" min="1" value="26" step="1" /></div>
				<div style="flex:0 0 auto;align-self:flex-end;display:flex;gap:8px;">
<button id="run-calc" class="button primary" style="padding:6px 10px;height:32px;line-height:20px;border-radius:8px;font-weight:700;min-width:50px;">Calculer</button>
					<button id="close-btn" class="button" style="padding:6px 10px;height:32px;line-height:20px;border-radius:8px;border:1px solid rgba(255,255,255,0.3);background:rgba(0,0,0,0.35);color:#ffa500;min-width:50px;">Fermer</button>
				</div>
			</div>
			<div id="repartition-result" style="margin-top:16px;color:#fff;">
				<div class="small" style="opacity:.8">Cliquez sur "Calculer" pour afficher la répartition.</div>
			</div>
		</section>

		<section class="card stroked" style="margin-top:8px;padding:16px;background:rgba(0,0,0,0.45);border:1px solid rgba(0,172,255,.25);">
			<div style="font-weight:700;color:var(--gold);text-transform:uppercase;font-size:13px;margin-bottom:2px">Paramètres de répartition</div>
			<ul style="line-height:1.5;font-size:14px;">
				<li>1er à 5e (par défaut) si 25 Buy-Rebuy ou plus</li>
				<li>Si moins de 25 Buy-Rebuy, la répartition s’adapte.</li>
			</ul>
		</section>
	</div>

	<!-- Bottom navigation -->
	<div class="bottom-nav-backdrop" aria-hidden="true"></div>
	<nav class="bottom-nav" role="navigation" aria-label="Main navigation">
		<button id="nav-home" class="" title="Accueil" onclick="window.location.href='/panel/quickview.php';">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5L12 4l9 7.5"/><path d="M5 21h14a1 1 0 0 0 1-1v-7H4v7a1 1 0 0 0 1 1z"/></svg>
			<div class="nav-label">Accueil</div>
		</button>
		<button id="nav-local" title="Local Timer" onclick="window.location.href='/newtimer/index.php';">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg>
			<div class="nav-label">Local Timer</div>
		</button>
		<button id="nav-split" class="active" title="Répartition">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
			<div class="nav-label">Répartition</div>
		</button>
	</nav>

	<script>
	function computeDistribution(prizepool, buyrebuy) {
		var total = Math.max(0, Math.round(prizepool));
		var players = Math.max(1, Math.round(buyrebuy));
		var weights;
		if (players <= 1) {
			weights = [100];
		} else if (players <= 13) {
			weights = [60, 40];
		} else if (players <= 17) {
			weights = [50, 30, 20];
		} else if (players <= 25) {
			weights = [40, 30, 20, 10];
		} else if (players <= 30) {
			// moins de 31 buy/rebuy => 5 places payées
			weights = [35, 25, 18, 12, 10];
		} else {
			// 32 et plus => 6 places payées
			weights = [32 ,22, 16, 13, 10, 7];
		}
		var paidPlaces = weights.length;
		var amounts = weights.map(function(w){
			var v = total * w / 100;
			return Math.max(0, Math.round(v / 10) * 10);
		});
		var sum = amounts.reduce(function(a,b){ return a+b; }, 0);
		var remainder = total - sum;
		if (remainder !== 0) {
			var step = remainder > 0 ? 10 : -10;
			while (remainder !== 0) {
				var idx = 0;
				amounts[idx] = Math.max(0, amounts[idx] + step);
				remainder -= step;
			}
		}
		return { amounts: amounts, paidPlaces: paidPlaces };
	}

	function renderResult() {
		var pricepool = parseFloat(document.getElementById('pricepool').value) || 0;
		var buyrebuy = parseInt(document.getElementById('buyrebuy').value) || 0;
		var res = computeDistribution(pricepool, buyrebuy);
		var total = res.amounts.reduce(function(a,b){return a+b;},0);
		var fragment = '<div style="font-weight:700;margin-bottom:8px;color:var(--gold);">Repartition proposée pour ' + total + ' €</div>';
		fragment += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">';
		res.amounts.forEach(function(amount,index){
			fragment += '<div style="padding:10px;border:1px solid rgba(255,255,255,0.12);border-radius:10px;background:rgba(0,0,0,0.25);"><strong>' + (index+1) + 'er :</strong></div>';
			fragment += '<div style="padding:10px;border:1px solid rgba(255,255,255,0.12);border-radius:10px;background:rgba(0,0,0,0.25);text-align:right;font-weight:700;">' + amount + ' €</div>';
		});
		fragment += '</div>';
		if (Math.max(0,Math.round(pricepool)) !== total) {
			fragment += '<div class="small" style="margin-top:8px;color:var(--red);">Ajustement appliqué: total généré = ' + total + ' €, montant initial saisi = ' + Math.round(pricepool) + ' €.</div>';
		}

		document.getElementById('repartition-result').innerHTML = fragment;
	}

	document.getElementById('run-calc').addEventListener('click', function(e){
		e.preventDefault();
		renderResult();
	});

	// Clear value when focusing input fields
	['pricepool','buyrebuy'].forEach(function(id){
		var el = document.getElementById(id);
		if (el) {
			el.addEventListener('focus', function(){
				this.value = '';
			});
		}
	});

	// Calcul initial
	renderResult();

	// Close button behavior
	document.getElementById('close-btn').addEventListener('click', function(){
		window.location.href = '/panel/quickview.php';
	});
	</script>

	<script src="/panel/timer_web/public/app.js?v=<?php echo $asset_ver . '-' . rand(100000,999999); ?>"></script>

	<script>
		(function(){
			const link = document.getElementById('theme-stylesheet');
			const apply = v=>{ link.href = (v==='B')? '/panel/timer_web/public/style.variantB.css':'/panel/timer_web/public/style.variantA.css'; localStorage.setItem('uiVariant', v); };
			const saved = localStorage.getItem('uiVariant') || 'A'; apply(saved);
		})();
	</script>
</body>
</html>
