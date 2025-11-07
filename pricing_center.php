<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/visibility.php';

$basePath = "";
include __DIR__ . '/header.php';

// Basispreise laden (mit Dispatch)
$base = $pdo->query("SELECT * FROM price_base WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if (!$base) {
  $pdo->exec("INSERT INTO price_base (id,repair,wash,canister,dispatch_fee,tow_inside,tow_outside,tuning_markup_public)
              VALUES (1,650,350,650,200,1000,1200,10)");
  $base = $pdo->query("SELECT * FROM price_base WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
}
if (!array_key_exists('dispatch_fee', $base)) {
  try { $pdo->exec("ALTER TABLE price_base ADD COLUMN dispatch_fee INT NOT NULL DEFAULT 200 AFTER canister"); } catch(Exception $e){}
  $base = $pdo->query("SELECT * FROM price_base WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
}

// Vertragspartner
$partners = $pdo->query("SELECT * FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Admin?
$isAdmin = (!empty($_SESSION['user_role']) && $_SESSION['user_role']==='admin') || (!empty($_SESSION['admin_logged_in']));

// „Außerhalb“ aus Basis
$dispatch = (int)($base['dispatch_fee'] ?? 200);
$outside = [
  'repair'   => (int)$base['repair'] + $dispatch,
  'wash'     => (int)$base['wash'] + $dispatch,
  'canister' => (int)$base['canister'] + $dispatch
];
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Preisübersicht & Vertragspartner</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="header.css">
<style>
main{max-width:1100px;margin:120px auto;padding:20px;color:#fff;}
.tab-bar{display:flex;gap:10px;margin-bottom:22px;}
.tab-bar button{
  padding:10px 18px;border-radius:8px;border:0;
  background:#1a1a1a;color:#fff;cursor:pointer;transition:.2s;border:1px solid rgba(57,255,20,.4);
}
.tab-bar button.active{background:linear-gradient(90deg,#39ff14,#76ff65);}
.tab-content{display:none;}
.tab-content.active{display:block;}
.card{background:rgba(25,25,25,0.9);padding:25px;border-radius:12px;border:1px solid rgba(57,255,20,0.4);box-shadow:0 0 15px rgba(57,255,20,0.25);}
table{width:100%;border-collapse:collapse;margin-top:16px;}
th,td{padding:10px;border-bottom:1px solid rgba(57,255,20,0.25);vertical-align:top}
th{color:#76ff65;text-align:left;}
.logo{height:40px;border-radius:6px;margin-right:10px;}
.partner-card{margin-top:14px;border:1px solid rgba(57,255,20,.25);border-radius:10px;overflow:hidden;}
.partner-header{padding:14px;display:flex;align-items:center;gap:12px;cursor:pointer;background:rgba(57,255,20,.08);}
.partner-header:hover{background:rgba(57,255,20,.18);}
.badge{padding:2px 8px;border-radius:999px;border:1px solid rgba(57,255,20,.4);font-size:.85rem;}
.partner-body{max-height:0;overflow:hidden;transition:max-height .45s ease, opacity .35s ease;opacity:0;padding:0 14px;}
.partner-body.open{max-height:2000px;opacity:1;padding:14px;}

.tuning-box{margin-top:14px;padding:12px;border-radius:10px;background:rgba(0,0,0,0.35);border:1px solid rgba(57,255,20,.3);}
.tuning-box h4{margin:0 0 6px 0;color:#a8ffba;}
.style-note{margin:8px 0 12px 0;padding:10px;border:1px dashed rgba(57,255,20,.35);border-radius:8px;opacity:.9}

/* Fahrzeuge: 2-Spalten Grid */
.vehicle-toggle{
  background:linear-gradient(90deg,#39ff14,#76ff65);
  border:none;
  color:#fff;
  cursor:pointer;
  padding:8px 14px;
  border-radius:10px;
  font-weight:600;
  transition:.25s;
  margin-top:12px;
  display:inline-block;
}
.vehicle-toggle:hover{
  opacity:.85;
  transform:translateY(-1px);
}
.vehicle-list{max-height:0;overflow:hidden;opacity:0;transition:max-height .45s ease, opacity .35s ease;margin-top:6px;}
.vehicle-list.open{max-height:2000px;opacity:1;}
.vehicle-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:8px;}
@media(max-width:900px){.vehicle-grid{grid-template-columns:1fr}}
.vcard{padding:12px;border-radius:10px;background:rgba(0,0,0,.28);border:1px solid rgba(57,255,20,.25);}
.vcard h5{margin:0 0 8px 0;font-size:1rem;color:#fff}
.vtable{width:100%;border-collapse:collapse;margin-top:6px;}
.vtable td{padding:6px 8px;border-bottom:1px solid rgba(57,255,20,.18);}
.vtable td.key{width:45%;opacity:.9}
.vempty{opacity:.8;font-style:italic}
</style>
</head>
<body>

<main>
  <h1>📦 Preisübersicht & Vertragspartner</h1>

  <div class="tab-bar">
    <button data-tab="general" class="active">💲 Allgemeine Preisliste</button>
    <button data-tab="partners">🤝 Vertragspartner</button>
  </div>

  <!-- TAB 1: Allgemein -->
  <div id="general" class="tab-content active">
    <div class="card">
      <div style="display:flex;gap:30px;flex-wrap:wrap">
        <div style="flex:1">
          <h3>🏭 In der Werkstatt</h3>
          <table>
            <tr><th>Leistung</th><th>Preis</th></tr>
            <tr><td>🔧 Reparatur</td><td><b style="color:#76ff65"><?= (int)$base['repair'] ?> $</b></td></tr>
            <tr><td>🧼 Wäsche</td><td><b style="color:#9eff3d"><?= (int)$base['wash'] ?> $</b></td></tr>
            <tr><td>⛽ Benzinkanister</td><td><b style="color:#3dd6ff"><?= (int)$base['canister'] ?> $</b></td></tr>
            <tr><td>🚚 Abschleppen (innerorts)</td><td><?= (int)$base['tow_inside'] ?> $</td></tr>            
          </table>
        </div>

        <div style="flex:1">
          <h3>🚗 Außerhalb (Dispatch)</h3>
          <table>
            <tr><th>Leistung</th><th>Preis</th></tr>
            <tr><td>🔧 Reparatur</td><td><b style="color:#76ff65"><?= $outside['repair'] ?> $</b></td></tr>
            <tr><td>🧼 Wäsche</td><td><b style="color:#9eff3d"><?= $outside['wash'] ?> $</b></td></tr>
            <tr><td>⛽ Benzinkanister</td><td><b style="color:#3dd6ff"><?= $outside['canister'] ?> $</b></td></tr>
            <tr><td>🚚 Abschleppen (außerorts)</td><td><?= (int)$base['tow_outside'] ?> $</td></tr>
          </table>
        </div>
      </div>

      <p style="margin-top:15px;">💡 Tuning für Nicht-Vertragspartner: <b style="color:#a8ffba">+<?= (float)$base['tuning_markup_public'] ?>%</b></p>
    </div>
  </div>

  <!-- TAB 2: Vertragspartner -->
  <div id="partners" class="tab-content">
    <div class="card">
      <?php if(!$partners): ?>
        <p class="small">Keine Vertragspartner vorhanden.</p>
      <?php else: foreach($partners as $p): ?>

        <?php
          // Partnerpreise (Overrides)
          $stmt = $pdo->prepare("SELECT service, price FROM partner_prices WHERE partner_id=?");
          $stmt->execute([$p['id']]);
          $ovr = []; foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $ovr[$r['service']] = $r['price'];

          $pr = [
            'repair'   => $ovr['repair']     ?? (int)$base['repair'],
            'wash'     => $ovr['wash']       ?? (int)$base['wash'],
            'canister' => $ovr['canister']   ?? (int)$base['canister'],
            'in'       => $ovr['tow_inside'] ?? (int)$base['tow_inside'],
            'out'      => $ovr['tow_outside']?? (int)$base['tow_outside']
          ];
          // Partner-Overrides *inkl. Außerhalb*
          $pr_out = [
            'repair'   => isset($ovr['repair_out'])   ? (int)$ovr['repair_out']   : ((int)$pr['repair']   + $dispatch),
            'wash'     => isset($ovr['wash_out'])     ? (int)$ovr['wash_out']     : ((int)$pr['wash']     + $dispatch),
            'canister' => isset($ovr['canister_out']) ? (int)$ovr['canister_out'] : ((int)$pr['canister'] + $dispatch),
          ];

          // Fahrzeuge
          $carsStmt = $pdo->prepare("SELECT * FROM partner_cars WHERE partner_id=? ORDER BY car_name ASC");
          $carsStmt->execute([$p['id']]);
          $cars = $carsStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <div class="partner-card">
          <!-- einklappbarer Partner-Header -->
          <div class="partner-header">
            <?php if(!empty($p['logo_url'])): ?><img src="<?= htmlspecialchars($p['logo_url']) ?>" class="logo" alt=""><?php endif; ?>
            <h3 style="margin:0"><?= htmlspecialchars($p['name']) ?></h3>
            <span class="badge">Tuning: <?= (float)$p['tuning_modifier_percent'] ?>%</span>
          </div>

          <div class="partner-body">
            <?php if(!empty($p['remarks'])): ?>
              <p><?= nl2br(htmlspecialchars($p['remarks'])) ?></p>
            <?php endif; ?>

            <div style="display:flex;flex-wrap:wrap;gap:30px;margin-top:10px">
              <div style="flex:1;">
                <h4>🏭 Werkstatt</h4>
                <table>
                  <tr><td>Reparatur</td><td><b style="color:#76ff65"><?= $pr['repair'] ?> $</b></td></tr>
                  <tr><td>Wäsche</td><td><b style="color:#9eff3d"><?= $pr['wash'] ?> $</b></td></tr>
                  <tr><td>Benzinkanister</td><td><b style="color:#3dd6ff"><?= $pr['canister'] ?> $</b></td></tr>
                  <tr><td>Abschleppen (innerorts)</td><td><?= $pr['in'] ?> $</td></tr>                  
                </table>
              </div>

              <div style="flex:1;">
                <h4>🚗 Außerhalb</h4>
                <table>
                  <tr><td>Reparatur</td><td><b style="color:#76ff65"><?= $pr_out['repair'] ?> $</b></td></tr>
                  <tr><td>Wäsche</td><td><b style="color:#9eff3d"><?= $pr_out['wash'] ?> $</b></td></tr>
                  <tr><td>Benzinkanister</td><td><b style="color:#3dd6ff"><?= $pr_out['canister'] ?> $</b></td></tr>
                  <tr><td>Abschleppen (außerorts)</td><td><?= $pr['out'] ?> $</td></tr>
                </table>
              </div>
            </div>

            <?php if($cars): ?>
              
              <button class="vehicle-toggle">🚗 Fahrzeuge & Tuning anzeigen</button>
              <div class="vehicle-list">
                <div class="vehicle-grid">
                  <?php foreach($cars as $c): ?>
                    <div class="vcard">
                      <h5><?= htmlspecialchars($c['car_name']) ?></h5>
                      <?php
                        $tun = $pdo->prepare("SELECT part,value FROM partner_car_tuning WHERE car_id=? ORDER BY id ASC");
                        $tun->execute([$c['id']]);
                        $rows = $tun->fetchAll(PDO::FETCH_ASSOC);
                      ?>
                      <?php if(!$rows): ?>
                        <div class="vempty">Keine Tuningdaten hinterlegt.</div>
                      <?php else: ?>
                        <table class="vtable">
                          <?php foreach($rows as $t): ?>
                            <tr>
                              <td class="key"><b><?= htmlspecialchars($t['part']) ?></b></td>
                              <td><?= htmlspecialchars($t['value']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </table>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if($isAdmin): ?>
              <p style="margin-top:12px"><a class="btn-primary" href="admin/partner_admin.php">Bearbeiten</a></p>
            <?php endif; ?>
          </div>
        </div>

      <?php endforeach; endif; ?>
    </div>
  </div>

</main>

<script>
// Tabs
document.querySelectorAll(".tab-bar button").forEach(btn=>{
  btn.onclick=()=>{
    document.querySelectorAll(".tab-bar button").forEach(b=>b.classList.remove("active"));
    document.querySelectorAll(".tab-content").forEach(c=>c.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById(btn.dataset.tab).classList.add("active");
  };
});

// Partner ein/ausklappen
document.querySelectorAll(".partner-header").forEach(h=>{
  h.addEventListener("click",()=>{
    let b=h.nextElementSibling;
    b.classList.toggle("open");
  });
});

// Fahrzeugliste ein/ausklappen
document.addEventListener('click', (e)=>{
  if(e.target.classList.contains('vehicle-toggle')){
    const btn = e.target;
    const box = btn.nextElementSibling;
    box.classList.toggle('open');
    btn.textContent = box.classList.contains('open')
      ? "🚗 Fahrzeuge & Tuning verbergen"
      : "🚗 Fahrzeuge & Tuning anzeigen";
  }
});
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="script.js"></script>
</body>
</html>
