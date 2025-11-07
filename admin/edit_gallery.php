<?php
// --- DEBUG MODUS ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once '../includes/db.php';

// Zentrale Admin-Zugriffskontrolle
require_once '../includes/admin_access.php';

// Galerie-Daten laden
$stmt = $pdo->query("SELECT * FROM gallery ORDER BY id DESC");
$images = $stmt->fetchAll();

// Bild löschen
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    // Bildpfad abrufen, um lokale Dateien zu löschen
    $stmt = $pdo->prepare("SELECT image_url FROM gallery WHERE id = ?");
    $stmt->execute([$id]);
    $imagePath = $stmt->fetchColumn();

    if ($imagePath && strpos($imagePath, 'pics/gallery/') === 0) {
        $fullPath = realpath(__DIR__ . '/../' . $imagePath);

        if ($fullPath !== false && strpos($fullPath, realpath(__DIR__ . '/..')) === 0 && file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: edit_gallery.php');
    exit;
};

// Feedback-Nachrichten
$errorMessage   = '';
$successMessage = '';

// Neues Bild speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageUrl = trim($_POST['image_url'] ?? '');
    $altText  = trim($_POST['alt_text'] ?? '');
    $finalUrl = '';

    // Prüfen, ob eine Datei hochgeladen wurde
    if (!empty($_FILES['image_file']['tmp_name'])) {
        $file    = $_FILES['image_file'];
        $maxSize = 5 * 1024 * 1024; // 5 MB
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if ($file['error'] === UPLOAD_ERR_OK) {
            if ($file['size'] <= $maxSize) {
                $mime = '';
                if (class_exists('finfo')) {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($file['tmp_name']) ?: '';
                } elseif (function_exists('mime_content_type')) {
                    $mime = mime_content_type($file['tmp_name']) ?: '';
                } else {
                    $mime = $file['type'] ?? '';
                }

                if (isset($allowed[$mime])) {
                    $uploadDir = __DIR__ . '/../pics/gallery/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    try {
                        $uniqueName = 'gallery_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
                    } catch (Exception $e) {
                        $uniqueName = 'gallery_' . date('Ymd_His') . '_' . uniqid();
                    }

                    $fileName  = $uniqueName . '.' . $allowed[$mime];
                    $target    = $uploadDir . $fileName;
                    $publicUrl = 'pics/gallery/' . $fileName;

                    if (move_uploaded_file($file['tmp_name'], $target)) {
                        $finalUrl       = $publicUrl;
                        $successMessage = 'Bild erfolgreich hochgeladen.';
                    } else {
                        $errorMessage = 'Fehler beim Speichern der Datei.';
                    }
                } else {
                    $errorMessage = 'Nur JPG, PNG, WEBP oder GIF Dateien sind erlaubt.';
                }
            } else {
                $errorMessage = 'Die Datei ist zu groß. Maximal 5 MB erlaubt.';
            }
        } else {
            $errorMessage = 'Fehler beim Hochladen der Datei (Code ' . (int)$file['error'] . ').';
        }
    }

    // Wenn keine Datei hochgeladen wurde, auf URL zurückgreifen
    if ($finalUrl === '' && $imageUrl !== '') {
        $finalUrl       = $imageUrl;
        $successMessage = 'Bild erfolgreich hinzugefügt.';
    }

    if ($finalUrl !== '') {
        $stmt = $pdo->prepare("INSERT INTO gallery (image_url, alt_text) VALUES (?, ?)");
        $stmt->execute([$finalUrl, $altText]);
        header('Location: edit_gallery.php');
        exit;
    } elseif ($errorMessage === '') {
        $errorMessage = 'Bitte gib eine Bild-URL an oder lade eine Bilddatei hoch.';
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Galerie bearbeiten | Benny’s Werkstatt</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../header.css">
<link rel="stylesheet" href="../styles.css">
<style>
main {
  padding: 120px 50px;
  max-width: 1000px;
  margin: 0 auto;
}
form {
  background: rgba(20, 20, 20, 0.9);
  border: 1px solid rgba(57,255,20,0.4);
  padding: 30px;
  border-radius: 12px;
  box-shadow: 0 0 25px rgba(57,255,20,0.3);
  margin-bottom: 50px;
}
form input {
  width: 100%;
  margin: 10px 0;
  padding: 10px;
  border: none;
  border-radius: 8px;
  background: #111;
  color: #fff;
}
form label {
  display: block;
  margin-top: 12px;
  font-weight: 600;
  color: #76ff65;
}
form button {
  background: linear-gradient(90deg, #39ff14, #76ff65);
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
  transition: 0.3s;
}
form button:hover {
  transform: scale(1.05);
  box-shadow: 0 0 15px rgba(57,255,20,0.5);
}
.notice {
  margin-bottom: 20px;
  padding: 12px 16px;
  border-radius: 8px;
  font-weight: 600;
  background: rgba(20, 20, 20, 0.85);
  border: 1px solid rgba(57,255,20,0.4);
  box-shadow: 0 0 15px rgba(57,255,20,0.3);
}
.notice-success {
  border-color: rgba(0, 200, 120, 0.5);
  box-shadow: 0 0 15px rgba(0, 200, 120, 0.2);
  color: #9dffc8;
}
.notice-error {
  border-color: rgba(255, 80, 80, 0.7);
  box-shadow: 0 0 15px rgba(255, 80, 80, 0.25);
  color: #ffbaba;
}
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 20px;
}
.gallery-item {
  background: rgba(30, 30, 30, 0.9);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 10px;
  overflow: hidden;
  text-align: center;
  padding: 10px;
  box-shadow: 0 0 20px rgba(57,255,20,0.25);
}
.gallery-item img {
  width: 100%;
  border-radius: 8px;
  transition: 0.3s;
  filter: brightness(0.9);
}
.gallery-item img:hover {
  transform: scale(1.05);
  filter: brightness(1);
}
.gallery-item a {
  display: inline-block;
  margin-top: 8px;
  color: #76ff65;
  text-decoration: none;
  font-weight: bold;
  transition: 0.3s;
}
.gallery-item a:hover {
  color: #39ff14;
  text-shadow: 0 0 10px #39ff14;
}
.back-link {
  display: inline-block;
  margin-top: 30px;
  color: #39ff14;
  text-decoration: none;
  font-weight: bold;
}
.back-link:hover {
  text-shadow: 0 0 10px #39ff14;
}
</style>
</head>
<body>

<?php include '../header.php'; ?>

<main>
  <section>
    <h2 class="section-title">🖼️ Galerie bearbeiten</h2>

    <?php if ($errorMessage !== ''): ?>
      <div class="notice notice-error">⚠️ <?= htmlspecialchars($errorMessage) ?></div>
    <?php elseif ($successMessage !== ''): ?>
      <div class="notice notice-success">✅ <?= htmlspecialchars($successMessage) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
      <h3>Neues Bild hinzufügen</h3>
      <label for="image_url">Bild-URL (optional)</label>
      <input type="url" name="image_url" id="image_url" placeholder="Bild-URL eingeben">
      <label for="image_file">oder Bilddatei hochladen</label>
      <input type="file" name="image_file" id="image_file" accept="image/*">
      <input type="text" name="alt_text" placeholder="Alternativtext (Beschreibung)">
      <button type="submit">Bild hinzufügen</button>
    </form>

    <div class="gallery-grid">
      <?php if (count($images) > 0): ?>
        <?php foreach ($images as $img): ?>
          <?php
            $imageSrc = $img['image_url'] ?? '';
            if ($imageSrc !== '') {
                $trimmed = ltrim($imageSrc);
                $hasProtocol = preg_match('/^(https?:)?\/\//i', $trimmed) === 1;
                $isAbsolutePath = strpos($trimmed, '/') === 0;
                $startsWithParent = strpos($trimmed, '../') === 0;
                $startsWithCurrent = strpos($trimmed, './') === 0;

                if (!$hasProtocol && !$isAbsolutePath && !$startsWithParent && !$startsWithCurrent) {
                    $imageSrc = '../' . ltrim($imageSrc, '/');
                }
            }
          ?>
          <div class="gallery-item">
            <img src="<?= htmlspecialchars($imageSrc) ?>" alt="<?= htmlspecialchars($img['alt_text']) ?>">
            <p><?= htmlspecialchars($img['alt_text']) ?></p>
            <a href="?delete=<?= $img['id'] ?>" onclick="return confirm('Bild wirklich löschen?')">🗑️ Löschen</a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>Keine Bilder in der Galerie vorhanden.</p>
      <?php endif; ?>
    </div>

    <a class="back-link" href="dashboard.php">← Zurück zum Dashboard</a>
  </section>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>


<script src="../script.js"></script>
</body>
</html>
