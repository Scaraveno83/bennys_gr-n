<?php
require_once __DIR__ . '/forum_helpers.php';
function forum_can_post(PDO $pdo, array $user) : bool {
    if (forum_is_admin($user)) return true;
    try{ $has = (bool)$pdo->query("SHOW TABLES LIKE 'forum_permissions'")->fetchColumn(); } catch(Exception $e){ $has=false; }
    if ($has) {
        $rang = $user['rang'] ?? null; if(!$rang) return false;
        $stmt=$pdo->prepare("SELECT can_post FROM forum_permissions WHERE rang=? LIMIT 1");
        $stmt->execute([$rang]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if($row!==false) return (int)$row['can_post']===1;
    }
    $allowed=["Geschäftsführung","Stv. Geschäftsleitung","Personalleitung","Ausbilder/in","Tuner/in","Meister/in","Mechaniker/in","Geselle/Gesellin","Azubi 3.Jahr","Azubi 2.Jahr"];
    return in_array($user['rang'] ?? '', $allowed, true);
}
?>