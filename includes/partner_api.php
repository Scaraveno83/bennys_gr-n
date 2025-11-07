<?php
// /bennys/includes/partner_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

// Admin-Rechte wie admin_access.php
$isAdmin = false;

if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
    $isAdmin = true;
} elseif (!empty($_SESSION['admin_logged_in'])) {
    $isAdmin = true;
} elseif (!empty($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("
        SELECT m.rang 
        FROM mitarbeiter m
        JOIN user_accounts u ON u.mitarbeiter_id = m.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $rang = $stmt->fetchColumn();

    $erlaubteRollen = [
        'Geschäftsführung',
        'Stv. Geschäftsleitung',
        'Personalleitung'
    ];

    if ($rang && in_array($rang, $erlaubteRollen)) {
        $isAdmin = true;
    }
}
$method = $_SERVER['REQUEST_METHOD'];
$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true) ?: [];
$action = $_GET['action'] ?? ($input['action'] ?? '');

function ok($arr = []) { echo json_encode(['ok'=>true] + $arr); exit; }
function err($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

// Upload-Verzeichnis für Logos
$uploadDir = realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
$logoDir   = $uploadDir . '/partner_logos';
if (!is_dir($logoDir)) @mkdir($logoDir, 0775, true);

/* =========================
 * 1) BASIS-PREISE
 * ========================= */
if ($action === 'get_base_prices') {
  $row = $pdo->query("SELECT * FROM price_base WHERE id=1")->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    $pdo->exec("INSERT INTO price_base (id,repair,wash,canister,dispatch_fee,tow_inside,tow_outside,tuning_markup_public)
                VALUES (1,650,350,650,200,1000,1200,10)");
    $row = $pdo->query("SELECT * FROM price_base WHERE id=1")->fetch(PDO::FETCH_ASSOC);
  }
  // Falls dispatch_fee-Spalte noch nicht existiert, hinzufügen
  if (!array_key_exists('dispatch_fee', $row)) {
    try { $pdo->exec("ALTER TABLE price_base ADD COLUMN dispatch_fee INT NOT NULL DEFAULT 200 AFTER canister"); } catch(Exception $e){}
    $row = $pdo->query("SELECT * FROM price_base WHERE id=1")->fetch(PDO::FETCH_ASSOC);
  }
  ok(['base'=>$row]);
}

if ($action === 'save_base_prices') {
  if (!$isAdmin) err('Unauthorized', 403);
  $repair  = (int)($input['repair'] ?? 650);
  $wash    = (int)($input['wash'] ?? 350);
  $can     = (int)($input['canister'] ?? 650);
  $dfee    = (int)($input['dispatch_fee'] ?? 200);
  $tin     = (int)($input['tow_inside'] ?? 1000);
  $tout    = (int)($input['tow_outside'] ?? 1200);
  $pubUp   = (float)($input['tuning_markup_public'] ?? 10);

  $sql = "INSERT INTO price_base (id,repair,wash,canister,dispatch_fee,tow_inside,tow_outside,tuning_markup_public)
          VALUES (1,?,?,?,?,?,?,?)
          AS new
          ON DUPLICATE KEY UPDATE
            repair=new.repair, wash=new.wash, canister=new.canister,
            dispatch_fee=new.dispatch_fee,
            tow_inside=new.tow_inside, tow_outside=new.tow_outside,
            tuning_markup_public=new.tuning_markup_public";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$repair, $wash, $can, $dfee, $tin, $tout, $pubUp]);
  ok();
}

/* =========================
 * 2) PARTNER CRUD
 * ========================= */
if ($action === 'list_partners') {
  $rows = $pdo->query("SELECT * FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
  ok(['items'=>$rows]);
}

if ($action === 'create_partner') {
  if (!$isAdmin) err('Unauthorized', 403);
  $name  = trim($input['name'] ?? '');
  $mod   = (float)($input['tuning_modifier_percent'] ?? 0);
  $rem   = trim($input['remarks'] ?? '');
  if ($name === '') err('Name fehlt');

  $stmt = $pdo->prepare("INSERT INTO partners (name, tuning_modifier_percent, remarks) VALUES (?, ?, ?)");
  $stmt->execute([$name, $mod, $rem]);
  ok(['id'=>$pdo->lastInsertId()]);
}

if ($action === 'update_partner') {
  if (!$isAdmin) err('Unauthorized', 403);
  $id    = (int)($input['id'] ?? 0);
  $name  = trim($input['name'] ?? '');
  $mod   = (float)($input['tuning_modifier_percent'] ?? 0);
  $rem   = trim($input['remarks'] ?? '');
  if ($id<=0 || $name==='') err('Ungültig');

  $stmt = $pdo->prepare("UPDATE partners SET name=?, tuning_modifier_percent=?, remarks=? WHERE id=?");
  $stmt->execute([$name,$mod,$rem,$id]);
  ok();
}

if ($action === 'delete_partner') {
  if (!$isAdmin) err('Unauthorized', 403);
  $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
  if ($id<=0) err('Ungültige ID');
  $pdo->prepare("DELETE FROM partners WHERE id=?")->execute([$id]);
  ok();
}

/* =========================
 * 3) LOGO UPLOAD
 * ========================= */
if ($action === 'upload_logo' && $method === 'POST') {
  if (!$isAdmin) err('Unauthorized', 403);
  $partner_id = (int)($_POST['partner_id'] ?? 0);
  if ($partner_id<=0) err('partner_id fehlt');

  if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    err('Kein Datei-Upload erhalten');
  }
  $f = $_FILES['logo'];
  $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['png','jpg','jpeg','webp'])) err('Nur png/jpg/jpeg/webp erlaubt');

  $safeName = 'partner_' . $partner_id . '_' . time() . '.' . $ext;
  $targetAbs = $logoDir . '/' . $safeName;
  if (!move_uploaded_file($f['tmp_name'], $targetAbs)) err('Upload fehlgeschlagen');

  $logoUrl = '/bennys/uploads/partner_logos/' . $safeName;
  $pdo->prepare("UPDATE partners SET logo_url=? WHERE id=?")->execute([$logoUrl, $partner_id]);
  ok(['logo_url'=>$logoUrl]);
}

/* =========================
 * 4) FAHRZEUGE
 * ========================= */
if ($action === 'list_cars') {
  $pid = (int)($_GET['partner_id'] ?? 0);
  if ($pid<=0) err('partner_id fehlt');
  $stmt = $pdo->prepare("SELECT * FROM partner_cars WHERE partner_id=? ORDER BY car_name ASC");
  $stmt->execute([$pid]);
  ok(['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'create_car') {
  if (!$isAdmin) err('Unauthorized', 403);
  $pid  = (int)($input['partner_id'] ?? 0);
  $name = trim($input['car_name'] ?? '');
  $notes= trim($input['notes'] ?? '');
  if ($pid<=0 || $name==='') err('Ungültig');

  $stmt = $pdo->prepare("INSERT INTO partner_cars (partner_id, car_name, notes) VALUES (?,?,?)");
  $stmt->execute([$pid,$name,$notes]);
  ok(['id'=>$pdo->lastInsertId()]);
}

if ($action === 'delete_car') {
  if (!$isAdmin) err('Unauthorized', 403);
  $id = (int)($input['id'] ?? 0);
  if ($id<=0) err('Ungültige ID');
  $pdo->prepare("DELETE FROM partner_cars WHERE id=?")->execute([$id]);
  ok();
}

/* =========================
 * 5) TUNING (Key/Value)
 * ========================= */
if ($action === 'list_tuning') {
  $car = (int)($_GET['car_id'] ?? 0);
  if ($car<=0) err('car_id fehlt');
  $stmt = $pdo->prepare("SELECT * FROM partner_car_tuning WHERE car_id=? ORDER BY id ASC");
  $stmt->execute([$car]);
  ok(['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'add_tuning') {
  if (!$isAdmin) err('Unauthorized', 403);
  $car = (int)($input['car_id'] ?? 0);
  $part= trim($input['part'] ?? '');
  $val = trim($input['value'] ?? '');
  if ($car<=0 || $part==='' || $val==='') err('Ungültig');
  $stmt=$pdo->prepare("INSERT INTO partner_car_tuning (car_id,part,value) VALUES (?,?,?)");
  $stmt->execute([$car,$part,$val]);
  ok(['id'=>$pdo->lastInsertId()]);
}

if ($action === 'delete_tuning') {
  if (!$isAdmin) err('Unauthorized', 403);
  $id = (int)($input['id'] ?? 0);
  if ($id<=0) err('Ungültige ID');
  $pdo->prepare("DELETE FROM partner_car_tuning WHERE id=?")->execute([$id]);
  ok();
}

/* =========================
 * 6) PARTNER-PREISE (Overrides)
 * ========================= */
if ($action === 'get_partner_prices') {
  $pid = (int)($_GET['partner_id'] ?? 0);
  if ($pid<=0) err('partner_id fehlt');

  $prices = $pdo->prepare("SELECT service, price FROM partner_prices WHERE partner_id=?");
  $prices->execute([$pid]);
  $map = [
    'repair'=>null,'repair_out'=>null,
    'wash'=>null,'wash_out'=>null,
    'canister'=>null,'canister_out'=>null,
    'tow_inside'=>null,'tow_outside'=>null
  ];
  foreach ($prices->fetchAll(PDO::FETCH_ASSOC) as $r) $map[$r['service']] = $r['price'];

  $p = $pdo->prepare("SELECT tuning_modifier_percent FROM partners WHERE id=?");
  $p->execute([$pid]);
  $partner = $p->fetch(PDO::FETCH_ASSOC);

  $base = $pdo->query("SELECT * FROM price_base WHERE id=1")->fetch(PDO::FETCH_ASSOC);
  ok(['override'=>$map,'partner'=>$partner,'base'=>$base]);
}

if ($action === 'save_partner_prices') {
  if (!$isAdmin) err('Unauthorized', 403);
  $pid = (int)($input['partner_id'] ?? 0);
  if ($pid<=0) err('partner_id fehlt');

  $services = [
    'repair','repair_out',
    'wash','wash_out',
    'canister','canister_out',
    'tow_inside','tow_outside'
  ];

  foreach ($services as $s) {
    $val = $input[$s] ?? null;
    if ($val === '' || $val === null) {
      $pdo->prepare("DELETE FROM partner_prices WHERE partner_id=? AND service=?")->execute([$pid,$s]);
    } else {
      $num = (int)$val;
      $sql = "INSERT INTO partner_prices (partner_id,service,price) VALUES (?,?,?)
              AS new
              ON DUPLICATE KEY UPDATE price=new.price";
      $stmt=$pdo->prepare($sql);
      $stmt->execute([$pid,$s,$num]);
    }
  }

  if (isset($input['tuning_modifier_percent'])) {
    $m = (float)$input['tuning_modifier_percent'];
    $pdo->prepare("UPDATE partners SET tuning_modifier_percent=? WHERE id=?")->execute([$m,$pid]);
  }

  ok();
}

/* =========================
 * 7) ÖFFENTL. LISTE (Mitarbeiter)
 * ========================= */
if ($action === 'public_partner_list') {
  $rows = $pdo->query("SELECT id,name,logo_url,tuning_modifier_percent FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
  ok(['items'=>$rows]);
}

if ($action === 'public_partner_detail') {
  $pid = (int)($_GET['partner_id'] ?? 0);
  if ($pid<=0) err('partner_id fehlt');

  $partner = $pdo->prepare("SELECT * FROM partners WHERE id=?");
  $partner->execute([$pid]);
  $p = $partner->fetch(PDO::FETCH_ASSOC);
  if (!$p) err('not found',404);

  $cars = $pdo->prepare("SELECT * FROM partner_cars WHERE partner_id=? ORDER BY car_name ASC");
  $cars->execute([$pid]);
  $cars = $cars->fetchAll(PDO::FETCH_ASSOC);

  foreach ($cars as &$c) {
    $tun = $pdo->prepare("SELECT part,value FROM partner_car_tuning WHERE car_id=? ORDER BY id ASC");
    $tun->execute([$c['id']]);
    $c['tuning'] = $tun->fetchAll(PDO::FETCH_ASSOC);
  }

  // Preise zusammenstellen: Basis + Partner-Overrides
  $base = $pdo->query("SELECT * FROM price_base WHERE id=1")->fetch(PDO::FETCH_ASSOC);
  if (!array_key_exists('dispatch_fee',$base)) $base['dispatch_fee']=200;

  $ovrS = $pdo->prepare("SELECT service, price FROM partner_prices WHERE partner_id=?");
  $ovrS->execute([$pid]);
  $ovr = [];
  foreach($ovrS->fetchAll(PDO::FETCH_ASSOC) as $r) $ovr[$r['service']] = $r['price'];

  // Werkstattpreise
  $price = [
    'repair'     => $ovr['repair']     ?? (int)$base['repair'],
    'wash'       => $ovr['wash']       ?? (int)$base['wash'],
    'canister'   => $ovr['canister']   ?? (int)$base['canister'],
    'tow_inside' => $ovr['tow_inside'] ?? (int)$base['tow_inside'],
    'tow_outside'=> $ovr['tow_outside']?? (int)$base['tow_outside'],
  ];
  // Außerhalb (entweder Override *_out, sonst Basis+dispatch_fee)
  $price_out = [
    'repair_out'   => $ovr['repair_out']   ?? ((int)$base['repair']   + (int)$base['dispatch_fee']),
    'wash_out'     => $ovr['wash_out']     ?? ((int)$base['wash']     + (int)$base['dispatch_fee']),
    'canister_out' => $ovr['canister_out'] ?? ((int)$base['canister'] + (int)$base['dispatch_fee']),
  ];

  ok([
    'partner'=>$p,
    'cars'=>$cars,
    'price'=>$price,
    'price_out'=>$price_out,
    'tuning_modifier_partner' => (float)$p['tuning_modifier_percent'],
    'tuning_modifier_public'  => (float)$base['tuning_markup_public']
  ]);
}

err('Unknown action');
