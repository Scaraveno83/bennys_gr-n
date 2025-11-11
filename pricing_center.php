<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/visibility.php';

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
$partnerCount = count($partners);
$publicMarkup = (float)($base['tuning_markup_public'] ?? 0);
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Preisübersicht & Vertragspartner</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="header.css">
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>

<main class="inventory-page pricing-center-page">
  <header class="inventory-header">
    <h1 class="inventory-title">📦 Preisübersicht &amp; Vertragspartner</h1>
    <p class="inventory-description">
      Aktuelle Konditionen für Benny’s Werkstatt sowie individuelle Vereinbarungen mit unseren Vertragspartnern.
    </p>
    <div class="inventory-metrics">
      <div class="inventory-metric">
        <span class="inventory-metric__label">Dispatch-Aufschlag</span>
        <span class="inventory-metric__value"><?= number_format($dispatch, 0, ',', '.') ?> $</span>
        <span class="inventory-metric__hint">für Außeneinsätze</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Vertragspartner</span>
        <span class="inventory-metric__value"><?= $partnerCount ?></span>
        <span class="inventory-metric__hint">aktiv hinterlegt</span>
      </div>
      <div class="inventory-metric">
        <span class="inventory-metric__label">Öffentlicher Tuningaufschlag</span>
        <span class="inventory-metric__value">+<?= rtrim(rtrim(number_format($publicMarkup, 2, ',', '.'), '0'), ',') ?>%</span>
        <span class="inventory-metric__hint">für Nicht-Vertragspartner</span>
      </div>
    </div>
  </header>

  <div class="pricing-center-tabs" role="tablist">
    <button type="button" class="pricing-tab is-active" data-tab="tab-general">💲 Allgemeine Preisliste</button>
    <button type="button" class="pricing-tab" data-tab="tab-partners">🤝 Vertragspartner</button>
  </div>

  <section id="tab-general" class="inventory-section pricing-panel is-active" role="tabpanel">
    <h2 class="pricing-panel__title">Allgemeine Preisliste</h2>
    <p class="inventory-section__intro">
      Grundlage für alle Leistungen innerhalb und außerhalb unserer Werkstatt.
    </p>

    <div class="pricing-grid">
      <article class="pricing-card">
        <h3 class="pricing-card__title">🏭 In der Werkstatt</h3>
        <table class="pricing-table">
          <thead>
            <tr><th>Leistung</th><th>Preis</th></tr>
          </thead>
          <tbody>
            <tr><td>Reparatur</td><td><span class="pricing-value pricing-value--repair"><?= (int)$base['repair'] ?> $</span></td></tr>
            <tr><td>Wäsche</td><td><span class="pricing-value pricing-value--wash"><?= (int)$base['wash'] ?> $</span></td></tr>
            <tr><td>Benzinkanister</td><td><span class="pricing-value pricing-value--fuel"><?= (int)$base['canister'] ?> $</span></td></tr>
            <tr><td>Abschleppen (innerorts)</td><td><?= (int)$base['tow_inside'] ?> $</td></tr>
          </tbody>
        </table>
      </article>

      <article class="pricing-card">
        <h3 class="pricing-card__title">🚗 Außerhalb (Dispatch)</h3>
        <table class="pricing-table">
          <thead>
            <tr><th>Leistung</th><th>Preis</th></tr>
          </thead>
          <tbody>
            <tr><td>Reparatur</td><td><span class="pricing-value pricing-value--repair"><?= $outside['repair'] ?> $</span></td></tr>
            <tr><td>Wäsche</td><td><span class="pricing-value pricing-value--wash"><?= $outside['wash'] ?> $</span></td></tr>
            <tr><td>Benzinkanister</td><td><span class="pricing-value pricing-value--fuel"><?= $outside['canister'] ?> $</span></td></tr>
            <tr><td>Abschleppen (außerorts)</td><td><?= (int)$base['tow_outside'] ?> $</td></tr>
          </tbody>
        </table>
      </article>
    </div>
  </section>

  <section id="tab-partners" class="inventory-section pricing-panel" role="tabpanel" hidden>
    <h2 class="pricing-panel__title">Vertragspartner</h2>

    <?php if(!$partners): ?>
      <p class="inventory-section__intro">Derzeit sind keine Vertragspartner hinterlegt.</p>
    <?php else: foreach($partners as $p): ?>

      <?php
        // Partnerpreise (Overrides)
        $stmt = $pdo->prepare("SELECT service, price FROM partner_prices WHERE partner_id=?");
        $stmt->execute([$p['id']]);
        $ovr = [];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
          $ovr[$r['service']] = $r['price'];
        }

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

      <?php $bodyId = 'partner-' . $p['id'] . '-body'; ?>
      <article class="pricing-partner">
        <button class="pricing-partner__header" type="button" aria-expanded="false" aria-controls="<?= $bodyId ?>">
          <div class="pricing-partner__identity">
            <?php if(!empty($p['logo_url'])): ?>
              <img src="<?= htmlspecialchars($p['logo_url']) ?>" class="pricing-partner__logo" alt="Logo von <?= htmlspecialchars($p['name']) ?>">
            <?php endif; ?>
            <div>
              <span class="pricing-partner__name"><?= htmlspecialchars($p['name']) ?></span>
              <span class="pricing-partner__tuning">Tuning: <?= (float)$p['tuning_modifier_percent'] ?>%</span>
            </div>
          </div>
          <span class="pricing-partner__toggle" aria-hidden="true"></span>
        </button>

        <div id="<?= $bodyId ?>" class="pricing-partner__body" hidden>
          <?php if(!empty($p['remarks'])): ?>
            <p class="pricing-partner__remarks"><?= nl2br(htmlspecialchars($p['remarks'])) ?></p>
          <?php endif; ?>

          <div class="pricing-grid pricing-grid--compact">
            <div class="pricing-card">
              <h3 class="pricing-card__title">🏭 Werkstatt</h3>
              <table class="pricing-table">
                <tbody>
                  <tr><td>Reparatur</td><td><span class="pricing-value pricing-value--repair"><?= $pr['repair'] ?> $</span></td></tr>
                  <tr><td>Wäsche</td><td><span class="pricing-value pricing-value--wash"><?= $pr['wash'] ?> $</span></td></tr>
                  <tr><td>Benzinkanister</td><td><span class="pricing-value pricing-value--fuel"><?= $pr['canister'] ?> $</span></td></tr>
                  <tr><td>Abschleppen (innerorts)</td><td><?= $pr['in'] ?> $</td></tr>
                </tbody>
              </table>
            </div>

            <div class="pricing-card">
              <h3 class="pricing-card__title">🚗 Außerhalb</h3>
              <table class="pricing-table">
                <tbody>
                  <tr><td>Reparatur</td><td><span class="pricing-value pricing-value--repair"><?= $pr_out['repair'] ?> $</span></td></tr>
                  <tr><td>Wäsche</td><td><span class="pricing-value pricing-value--wash"><?= $pr_out['wash'] ?> $</span></td></tr>
                  <tr><td>Benzinkanister</td><td><span class="pricing-value pricing-value--fuel"><?= $pr_out['canister'] ?> $</span></td></tr>
                  <tr><td>Abschleppen (außerorts)</td><td><?= $pr['out'] ?> $</td></tr>
                </tbody>
              </table>
            </div>
          </div>

          <?php if($cars): ?>
            <button type="button" class="pricing-vehicles__toggle">🚗 Fahrzeuge &amp; Tuning anzeigen</button>
            <div class="pricing-vehicles" hidden>
              <div class="pricing-vehicles__grid">
                <?php foreach($cars as $c): ?>
                  <article class="pricing-vehicle">
                    <h4 class="pricing-vehicle__title"><?= htmlspecialchars($c['car_name']) ?></h4>
                    <?php
                      $tun = $pdo->prepare("SELECT part,value FROM partner_car_tuning WHERE car_id=? ORDER BY id ASC");
                      $tun->execute([$c['id']]);
                      $rows = $tun->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <?php if(!$rows): ?>
                      <p class="pricing-vehicle__empty">Keine Tuningdaten hinterlegt.</p>
                    <?php else: ?>
                      <table class="pricing-vehicle__table">
                        <tbody>
                          <?php foreach($rows as $t): ?>
                            <tr>
                              <td class="pricing-vehicle__key"><?= htmlspecialchars($t['part']) ?></td>
                              <td><?= htmlspecialchars($t['value']) ?></td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <?php if($isAdmin): ?>
            <div class="pricing-partner__actions">
              <a class="btn-primary" href="admin/partner_admin.php">Vertrag bearbeiten</a>
            </div>
          <?php endif; ?>
        </div>
      </article>

    <?php endforeach; endif; ?>
  </section>
</main>

<script>
const tabButtons = document.querySelectorAll('.pricing-tab');
const tabPanels = document.querySelectorAll('.pricing-panel');

function setVisibility(element, visible) {
  if (visible) {
    element.classList.add('is-active');
    element.removeAttribute('hidden');
  } else {
    element.classList.remove('is-active');
    element.setAttribute('hidden', '');
  }
}

tabButtons.forEach((button) => {
  button.addEventListener('click', () => {
    const targetId = button.dataset.tab;
    tabButtons.forEach((btn) => btn.classList.toggle('is-active', btn === button));
    tabPanels.forEach((panel) => {
      const isMatch = panel.id === targetId;
      setVisibility(panel, isMatch);
    });
  });
});

document.querySelectorAll('.pricing-partner__header').forEach((header) => {
  const partner = header.closest('.pricing-partner');
  const controlledId = header.getAttribute('aria-controls');
  let body = null;

  if (controlledId) {
    body = document.getElementById(controlledId);
  } else if (partner) {
    body = partner.querySelector('.pricing-partner__body');
  }

  if (!partner || !body) return;

  const setPartnerState = (open) => {
    partner.classList.toggle('is-open', open);
    header.setAttribute('aria-expanded', open ? 'true' : 'false');
    body.hidden = !open;
  };

  const isInitiallyOpen = header.getAttribute('aria-expanded') === 'true' && !body.hasAttribute('hidden');
  setPartnerState(isInitiallyOpen);

  header.addEventListener('click', () => {
    const willOpen = header.getAttribute('aria-expanded') !== 'true';
    setPartnerState(willOpen);
  });
});

document.querySelectorAll('.pricing-vehicles__toggle').forEach((toggle) => {
  toggle.addEventListener('click', () => {
    const wrapper = toggle.nextElementSibling;
    const isHidden = wrapper.hasAttribute('hidden');
    if (isHidden) {
      wrapper.removeAttribute('hidden');
      toggle.classList.add('is-open');
      toggle.textContent = '🚗 Fahrzeuge & Tuning verbergen';
    } else {
      wrapper.setAttribute('hidden', '');
      toggle.classList.remove('is-open');
      toggle.textContent = '🚗 Fahrzeuge & Tuning anzeigen';
    }
  });
});
</script>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="script.js"></script>
</body>
</html>
