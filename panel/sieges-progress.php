<?php
// Progress UI page for synchronous assignment
session_start();
require_once __DIR__ . '/include/config.php';
$act = isset($_GET['ac']) ? intval($_GET['ac']) : 0;
if (!$act) { echo "Missing activity id"; exit; }
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Réaffectation — Progress</title>
<link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
</head><body style="padding:20px">
<div class="container">
  <h3>Réaffectation des sièges — activité #<?php echo intval($act); ?></h3>
  <div id="note" class="alert alert-info">Initialisation...</div>
  <div class="progress" style="height:28px;margin-bottom:12px"><div id="bar" class="progress-bar" role="progressbar" style="width:0%">0%</div></div>
  <pre id="log" style="height:240px;overflow:auto;background:#f7f7f7;padding:8px;border:1px solid #ddd"></pre>
  <div>
    <a href="/panel/voir-activite.php?uid=<?php echo intval($act); ?>" class="btn btn-secondary" id="back" style="display:none">Retour activité</a>
  </div>
</div>
<script>
var act = <?php echo intval($act); ?>;
var polling = null;
function tailLog(){
  fetch('/panel/sieges-status.php?act='+act+'&_t='+Date.now())
  .then(r=>r.json()).then(function(d){
    var logEl = document.getElementById('log');
    var tail = d.tail || [];
    var spawn = d.spawn_tail || [];
    var txt = (spawn.concat(tail)).join('\n');
    logEl.textContent = txt;
    if (d.progress) {
      var p = d.progress.percent || 0;
      document.getElementById('bar').style.width = p+'%';
      document.getElementById('bar').textContent = p+'%';
      document.getElementById('note').textContent = 'Statut: ' + (d.progress.status||'') + (d.progress.last_id?(' — last id:'+d.progress.last_id):'');
      if (d.progress.status === 'done') {
        clearInterval(polling); document.getElementById('back').style.display='inline-block';
      }
    }
    if (d.last_worker_done || d.done_summary) {
      clearInterval(polling); document.getElementById('back').style.display='inline-block';
    }
  }).catch(e=>console.warn('tail err',e));
}

// Start the synchronous job via fetch (runs on server; progress monitored via progress file)
(function(){
  polling = setInterval(tailLog, 800);
  tailLog();
  // use text() then try parse to handle possible non-JSON error responses
  fetch('/panel/sieges-sync.php?ac='+act, {credentials:'same-origin'})
  .then(function(r){ return r.text().then(function(t){ return { status: r.status, text: t }; }); })
  .then(function(obj){
    try {
      var j = JSON.parse(obj.text);
      if (obj.status >= 400 || !j || j.ok === false) {
        document.getElementById('note').textContent = 'Erreur (serveur): ' + (j && j.msg ? j.msg : obj.text.slice(0,200));
        clearInterval(polling);
        return;
      }
      document.getElementById('note').textContent = 'Terminé — positions traitées: ' + (j.processed||0);
      tailLog();
      document.getElementById('back').style.display='inline-block';
    } catch (e) {
      document.getElementById('note').textContent = 'Erreur: réponse invalide du serveur';
      var logEl = document.getElementById('log'); logEl.textContent = obj.text; // show raw response
      clearInterval(polling);
    }
  }).catch(function(e){ document.getElementById('note').textContent = 'Erreur réseau: ' + e; clearInterval(polling); });
})();
</script>
</body></html>