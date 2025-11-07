<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/db.php';

/**
 * Feste Rangliste passend zu deiner Seite.
 */
$RANG_LIST = [
  "Geschäftsführung",
  "Stv. Geschäftsleitung",
  "Personalleitung",
  "Ausbilder/in",
  "Tuner/in",
  "Meister/in",
  "Mechaniker/in",
  "Geselle/Gesellin",
  "Azubi 3.Jahr",
  "Azubi 2.Jahr",
  "Azubi 1.Jahr",
  "Praktikant/in"
];

/** Erzwingt Login (nutzt bestehenden Admin-Login-Flow) */
function forum_require_login(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: /admin/login.php');
    exit;
  }
}

/** Liefert User-Zusammenfassung + Rang + zugehörige Mitarbeiter-ID */
function forum_get_user_summary(PDO $pdo, int $user_account_id): array {
  $stmt = $pdo->prepare("
    SELECT ua.id AS user_id, ua.role, ua.mitarbeiter_id,
           m.name AS mitarbeiter_name, m.rang, m.id AS mid, m.bild_url
      FROM user_accounts ua
      LEFT JOIN mitarbeiter m ON m.id = ua.mitarbeiter_id
     WHERE ua.id = ?
  ");
  $stmt->execute([$user_account_id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) return ['user_id'=>$user_account_id,'role'=>'user','mitarbeiter_id'=>null,'mid'=>null,'mitarbeiter_name'=>null,'rang'=>null,'bild_url'=>null];
  return $row;
}

/** Admin? */
function forum_is_admin(array $me): bool { return ($me['role'] ?? '') === 'admin'; }

/** Darf in Raum schreiben? (Admin immer ja) */
function forum_can_write(PDO $pdo, array $me, int $room_id): bool {
  if (forum_is_admin($me)) return true;
  $rang = $me['rang'] ?? null;
  if (!$rang) return false;
  $st = $pdo->prepare("SELECT can_write FROM forum_room_permissions WHERE room_id=? AND rang=?");
  $st->execute([$room_id, $rang]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  return $row ? (int)$row['can_write'] === 1 : false;
}

/** 10-Minuten-Editfenster für Autoren (Admin jederzeit) */
function forum_can_edit_post(array $me, array $post): bool {
  if (forum_is_admin($me)) return true;
  if ((int)($post['author_id'] ?? 0) !== (int)($me['mid'] ?? 0)) return false; // Autor = mitarbeiter.id
  $created = strtotime($post['created_at'] ?? 'now');
  return (time() - $created) <= 600; // 10 Minuten
}

/** Avatar-URL ermitteln (nutzt m.bild_url ODER /pics/profile/{id}.jpg ODER Default) */
function forum_avatar_url(?string $bild_url, ?int $mitarbeiter_id): string {
  if (!empty($bild_url)) {
    // Wenn schon absolute/relative URL gespeichert: direkt nutzen
    return htmlspecialchars($bild_url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
  if (!empty($mitarbeiter_id)) {
    // Konvention: /bennys/pics/profile/{id}.jpg (anpassbar)
    $try = "/bennys/pics/profile/{$mitarbeiter_id}.jpg";
    return $try;
  }
  return "/bennys/pics/default-avatar.png";
}

/** HTML Escape */
function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
