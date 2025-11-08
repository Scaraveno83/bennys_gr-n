<?php
// --- DEBUG ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once '../includes/db.php';
require_once '../includes/admin_access.php';

/**
 * Stellt sicher, dass die Galerie-Tabelle die benötigten Spalten besitzt.
 *
 * @return array{media_type:bool,video_url:bool}
 */
function ensureGallerySchema(PDO $pdo): array
{
    try {
        $columnsStmt = $pdo->query('SHOW COLUMNS FROM gallery');
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return ['media_type' => false, 'video_url' => false];
    }

    $hasMediaType = in_array('media_type', $columns, true);
    $hasVideoUrl = in_array('video_url', $columns, true);

    if (!$hasMediaType) {
        try {
            $pdo->exec("ALTER TABLE gallery ADD COLUMN media_type ENUM('image','video') NOT NULL DEFAULT 'image' AFTER alt_text");
            $hasMediaType = true;
        } catch (PDOException $e) {
            $hasMediaType = false;
        }
    }

    if (!$hasVideoUrl) {
        try {
            $pdo->exec("ALTER TABLE gallery ADD COLUMN video_url TEXT DEFAULT NULL AFTER media_type");
            $hasVideoUrl = true;
        } catch (PDOException $e) {
            $hasVideoUrl = false;
        }
    }

    return ['media_type' => $hasMediaType, 'video_url' => $hasVideoUrl];
}

function escapeAttr(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Wandelt einen Video-Link in eine einbettbare Konfiguration um.
 *
 * @return array{mode:string,src:string}|null
 */
function buildVideoEmbed(string $url): ?array
{
    $cleanUrl = trim($url);
    if ($cleanUrl === '') {
        return null;
    }

    $host = parse_url($cleanUrl, PHP_URL_HOST) ?? '';
    $host = strtolower($host);

    if (strpos($host, 'youtube.com') !== false || strpos($host, 'youtu.be') !== false) {
        $videoId = null;
        if (strpos($host, 'youtu.be') !== false) {
            $path = parse_url($cleanUrl, PHP_URL_PATH) ?: '';
            $videoId = ltrim($path, '/');
        } else {
            parse_str(parse_url($cleanUrl, PHP_URL_QUERY) ?? '', $params);
            if (!empty($params['v'])) {
                $videoId = $params['v'];
            } else {
                $path = parse_url($cleanUrl, PHP_URL_PATH) ?: '';
                if (preg_match('~/embed/([\w-]{6,})~i', $path, $matches)) {
                    $videoId = $matches[1];
                }
            }
        }

        if ($videoId) {
            $embedUrl = 'https://www.youtube.com/embed/' . htmlspecialchars($videoId, ENT_QUOTES, 'UTF-8');
            return ['mode' => 'iframe', 'src' => $embedUrl];
        }
    }

    if (strpos($host, 'vimeo.com') !== false) {
        $path = parse_url($cleanUrl, PHP_URL_PATH) ?: '';
        if (preg_match('~/([0-9]+)~', $path, $matches)) {
            $embedUrl = 'https://player.vimeo.com/video/' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            return ['mode' => 'iframe', 'src' => $embedUrl];
        }
    }

    if (preg_match('/\.(mp4|webm|ogg|ogv)(\?.*)?$/i', $cleanUrl)) {
        return ['mode' => 'video', 'src' => htmlspecialchars($cleanUrl, ENT_QUOTES, 'UTF-8')];
    }

    return ['mode' => 'iframe', 'src' => htmlspecialchars($cleanUrl, ENT_QUOTES, 'UTF-8')];
}

$columnInfo = ensureGallerySchema($pdo);
$hasMediaType = $columnInfo['media_type'];
$hasVideoUrl = $columnInfo['video_url'];

$errorMessage = '';
$successMessage = '';

// --------- Eintrag löschen ---------
if (isset($_GET['delete'])) {
    $deleteId = (int) $_GET['delete'];

    $selectCols = ['image_url'];
    if ($hasMediaType) {
        $selectCols[] = 'media_type';
    }
    if ($hasVideoUrl) {
        $selectCols[] = 'video_url';
    }

    try {
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $selectCols) . ' FROM gallery WHERE id = ?');
        $stmt->execute([$deleteId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($entry) {
            $mediaType = $hasMediaType ? ($entry['media_type'] ?? 'image') : 'image';
            $imagePath = $entry['image_url'] ?? '';

            if ($mediaType === 'image' && $imagePath && strpos($imagePath, 'pics/gallery/') === 0) {
                $fullPath = realpath(__DIR__ . '/../' . $imagePath);
                if ($fullPath !== false && strpos($fullPath, realpath(__DIR__ . '/..')) === 0 && file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $deleteStmt = $pdo->prepare('DELETE FROM gallery WHERE id = ?');
            $deleteStmt->execute([$deleteId]);
            header('Location: edit_gallery.php');
            exit;
        }
    } catch (PDOException $e) {
        $errorMessage = 'Eintrag konnte nicht gelöscht werden: ' . $e->getMessage();
    }
}

// --------- Eintrag speichern ---------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mediaType = $_POST['media_type'] ?? 'image';
    $mediaType = $mediaType === 'video' ? 'video' : 'image';
    $altText = trim($_POST['alt_text'] ?? '');

    if ($mediaType === 'video' && (!$hasMediaType || !$hasVideoUrl)) {
        $errorMessage = 'Video-Links können erst genutzt werden, wenn die Datenbank aktualisiert wurde.';
    } else {
        if ($mediaType === 'image') {
            $imageUrl = trim($_POST['image_url'] ?? '');
            $finalUrl = '';

            if (!empty($_FILES['image_file']['tmp_name'])) {
                $file = $_FILES['image_file'];
                $maxSize = 5 * 1024 * 1024;
                $allowed = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'image/gif' => 'gif',
                ];

                if ($file['error'] === UPLOAD_ERR_OK) {
                    if ($file['size'] <= $maxSize) {
                        $mime = '';
                        if (class_exists('finfo')) {
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $finfo->file($file['tmp_name']) ?: '';
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

                            $fileName = $uniqueName . '.' . $allowed[$mime];
                            $target = $uploadDir . $fileName;
                            $publicUrl = '/pics/gallery/' . $fileName;

                            if (move_uploaded_file($file['tmp_name'], $target)) {
                                $finalUrl = $publicUrl;
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

            if ($finalUrl === '' && $imageUrl !== '') {
                $finalUrl = $imageUrl;
                $successMessage = 'Bild erfolgreich hinzugefügt.';
            }

            if ($finalUrl !== '' && $errorMessage === '') {
                $columns = ['image_url', 'alt_text'];
                $values = [$finalUrl, $altText];

                if ($hasMediaType) {
                    $columns[] = 'media_type';
                    $values[] = 'image';
                }
                if ($hasVideoUrl) {
                    $columns[] = 'video_url';
                    $values[] = null;
                }

                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $sql = 'INSERT INTO gallery (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                header('Location: edit_gallery.php');
                exit;
            } elseif ($errorMessage === '') {
                $errorMessage = 'Bitte gib eine Bild-URL an oder lade eine Bilddatei hoch.';
            }
        } else {
            $videoUrl = trim($_POST['video_url'] ?? '');
            $posterUrl = trim($_POST['poster_url'] ?? '');

            if ($videoUrl === '' || !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                $errorMessage = 'Bitte gib einen gültigen Video-Link an (z. B. YouTube, Vimeo oder MP4).';
            } elseif ($posterUrl !== '' && !filter_var($posterUrl, FILTER_VALIDATE_URL)) {
                $errorMessage = 'Der Vorschaulink muss eine gültige URL sein.';
            } else {
                $columns = ['image_url', 'alt_text'];
                $values = [$posterUrl !== '' ? $posterUrl : '', $altText];

                if ($hasMediaType) {
                    $columns[] = 'media_type';
                    $values[] = 'video';
                }
                if ($hasVideoUrl) {
                    $columns[] = 'video_url';
                    $values[] = $videoUrl;
                }

                $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                $sql = 'INSERT INTO gallery (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
                $successMessage = 'Video erfolgreich gespeichert.';
                header('Location: edit_gallery.php');
                exit;
            }
        }
    }
}

$selectColumns = ['id', 'image_url', 'alt_text'];
if ($hasMediaType) {
    $selectColumns[] = 'media_type';
}
if ($hasVideoUrl) {
    $selectColumns[] = 'video_url';
}

$stmt = $pdo->query('SELECT ' . implode(', ', $selectColumns) . ' FROM gallery ORDER BY id DESC');
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
body {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}
main {
  flex: 1;
  padding: 140px 40px 80px;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}
.admin-headline {
  text-align: center;
  margin-bottom: 30px;
}
.admin-headline h1 {
  margin: 0;
  font-size: clamp(2.2rem, 3vw + 1rem, 3.4rem);
  text-shadow: 0 0 20px rgba(57,255,20,0.6);
}
.admin-headline p {
  color: #d8ffd8;
  max-width: 680px;
  margin: 10px auto 0;
  line-height: 1.6;
}
.notice {
  margin-bottom: 20px;
  padding: 14px 18px;
  border-radius: 12px;
  font-weight: 600;
  background: rgba(20, 20, 20, 0.88);
  border: 1px solid rgba(57,255,20,0.4);
  box-shadow: 0 0 18px rgba(57,255,20,0.25);
}
.notice-success {
  border-color: rgba(0, 200, 120, 0.5);
  box-shadow: 0 0 18px rgba(0, 200, 120, 0.25);
  color: #9dffc8;
}
.notice-error {
  border-color: rgba(255, 80, 80, 0.7);
  box-shadow: 0 0 18px rgba(255, 80, 80, 0.25);
  color: #ffbaba;
}
.form-card {
  background: rgba(20,20,20,0.9);
  border: 1px solid rgba(57,255,20,0.45);
  border-radius: 16px;
  padding: 32px;
  box-shadow: 0 0 25px rgba(57,255,20,0.25);
  margin-bottom: 50px;
}
.form-card h2 {
  margin-top: 0;
  color: #76ff65;
}
.form-toggle {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  margin-bottom: 24px;
}
.toggle-pill {
  background: rgba(57,255,20,0.1);
  border: 1px solid rgba(57,255,20,0.45);
  border-radius: 999px;
  padding: 10px 20px;
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-weight: 600;
  color: #c8ffc8;
  transition: all .3s ease;
}
.toggle-pill input {
  accent-color: #39ff14;
}
.toggle-pill.active {
  background: linear-gradient(90deg,#39ff14,#76ff65);
  border-color: transparent;
  color: #111;
  box-shadow: 0 0 18px rgba(57,255,20,0.45);
}
.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 18px;
  margin-bottom: 18px;
}
.form-row label {
  display: block;
  font-weight: 600;
  color: #76ff65;
  margin-bottom: 6px;
}
.form-row input,
.form-row textarea,
.form-row select {
  width: 100%;
  background: rgba(15,15,15,0.85);
  border: 1px solid rgba(57,255,20,0.35);
  border-radius: 10px;
  padding: 12px;
  color: #f0fff0;
  font-family: inherit;
}
.form-row input[type="file"] {
  padding: 10px;
}
.form-actions {
  display: flex;
  justify-content: flex-end;
}
.form-actions button {
  background: linear-gradient(90deg,#39ff14,#76ff65);
  color: #111;
  border: none;
  font-weight: 700;
  padding: 12px 26px;
  border-radius: 12px;
  cursor: pointer;
  transition: transform .25s ease, box-shadow .25s ease;
}
.form-actions button:hover {
  transform: translateY(-2px);
  box-shadow: 0 0 20px rgba(57,255,20,0.55);
}
.media-type-panel {
  display: none;
}
.media-type-panel.active {
  display: block;
}
.media-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 24px;
}
.media-card-admin {
  position: relative;
  background: rgba(25,25,25,0.85);
  border: 1px solid rgba(57,255,20,0.4);
  border-radius: 16px;
  padding: 18px;
  box-shadow: 0 0 20px rgba(57,255,20,0.2);
}
.media-card-admin h3 {
  margin-top: 0;
  display: flex;
  align-items: center;
  gap: 10px;
  color: #76ff65;
}
.media-card-admin .media-preview {
  position: relative;
  margin: 16px 0;
  aspect-ratio: 16 / 9;
  border-radius: 12px;
  overflow: hidden;
  background: rgba(0,0,0,0.7);
}
.media-card-admin img,
.media-card-admin iframe,
.media-card-admin video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  border: none;
}
.media-card-admin iframe {
  background: #000;
}
.media-card-admin .media-meta {
  font-size: 0.95rem;
  color: #d7ffd7;
}
.media-card-admin .media-meta strong {
  color: #39ff14;
}
.media-card-admin .delete-btn {
  position: absolute;
  top: 18px;
  right: 18px;
  background: rgba(255,80,80,0.15);
  border: 1px solid rgba(255,80,80,0.4);
  color: #ffb0b0;
  padding: 8px 14px;
  border-radius: 999px;
  font-weight: 600;
  text-decoration: none;
  transition: all .25s ease;
}
.media-card-admin .delete-btn:hover {
  background: rgba(255,80,80,0.35);
  color: #fff;
}
.type-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(57,255,20,0.15);
  border: 1px solid rgba(57,255,20,0.4);
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: .05em;
}
@media (max-width: 768px) {
  main { padding: 120px 20px 60px; }
  .form-card { padding: 24px; }
  .media-card-admin { padding: 16px; }
}
</style>
</head>
<body>
<?php include '../header.php'; ?>

<main>
  <div class="admin-headline">
    <h1>Galerie verwalten</h1>
    <p>Pflege hier die Mediengalerie der Startseite. Du kannst hochauflösende Bilder hochladen oder Videos per Link einbetten.</p>
  </div>

  <?php if ($errorMessage !== ''): ?>
    <div class="notice notice-error">⚠️ <?= htmlspecialchars($errorMessage) ?></div>
  <?php elseif ($successMessage !== ''): ?>
    <div class="notice notice-success">✅ <?= htmlspecialchars($successMessage) ?></div>
  <?php endif; ?>

  <section class="form-card">
    <h2>Neuen Eintrag hinzufügen</h2>
    <p>Wähle zuerst den Medientyp und fülle anschließend die entsprechenden Felder aus. Bilder können wahlweise hochgeladen oder per URL eingebunden werden.</p>

    <form method="POST" enctype="multipart/form-data" id="mediaForm">
      <div class="form-toggle">
        <label class="toggle-pill active" data-target="image">
          <input type="radio" name="media_type" value="image" checked>
          <span>🖼️ Bild</span>
        </label>
        <label class="toggle-pill" data-target="video">
          <input type="radio" name="media_type" value="video" <?= $hasMediaType && $hasVideoUrl ? '' : 'disabled'; ?>>
          <span>🎬 Video</span>
          <?php if (!$hasMediaType || !$hasVideoUrl): ?>
            <small style="font-size:0.75rem;color:#ffbaba;">(DB-Upgrade nötig)</small>
          <?php endif; ?>
        </label>
      </div>

      <div class="media-type-panel active" data-panel="image">
        <div class="form-row">
          <div>
            <label for="image_url">Bild-URL (optional)</label>
            <input type="url" name="image_url" id="image_url" placeholder="https://...">
          </div>
          <div>
            <label for="image_file">Oder Bilddatei hochladen</label>
            <input type="file" name="image_file" id="image_file" accept="image/*">
          </div>
        </div>
      </div>

      <div class="media-type-panel" data-panel="video">
        <div class="form-row">
          <div>
            <label for="video_url">Video-Link *</label>
            <input type="url" name="video_url" id="video_url" placeholder="https://youtu.be/...">
          </div>
          <div>
            <label for="poster_url">Vorschaubild (optional)</label>
            <input type="url" name="poster_url" id="poster_url" placeholder="https://... (Thumbnail oder Standbild)">
          </div>
        </div>
      </div>

      <div class="form-row">
        <div>
          <label for="alt_text">Beschreibung / Alt-Text</label>
          <textarea name="alt_text" id="alt_text" rows="2" placeholder="Kurze Beschreibung für Screenreader und Tooltips"></textarea>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit">Speichern</button>
      </div>
    </form>
  </section>

  <section>
    <h2 class="section-title">Vorhandene Medien</h2>
    <?php if (count($items) > 0): ?>
      <div class="media-preview-grid">
        <?php foreach ($items as $item): ?>
          <?php
            $type = $hasMediaType ? ($item['media_type'] ?? 'image') : 'image';
            $type = $type === 'video' ? 'video' : 'image';
            $caption = trim($item['alt_text'] ?? '');
            $imageUrl = $item['image_url'] ?? '';
            $videoUrl = $hasVideoUrl ? trim((string)($item['video_url'] ?? '')) : '';

            $previewHtml = '';
            if ($type === 'video' && $videoUrl !== '') {
                $embed = buildVideoPreview($videoUrl);
                $previewHtml = $embed;
            } elseif ($imageUrl !== '') {
                $src = escapeAttr($imageUrl);
                $previewSrc = $src;
if (strpos($previewSrc, 'http') !== 0) {
    $previewSrc = '/' . ltrim($previewSrc, '/');
}
$previewHtml = '<img src="' . $previewSrc . '" alt="Vorschau">';
            } else {
                $previewHtml = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#888;">Keine Vorschau</div>';
            }
          ?>
          <article class="media-card-admin">
            <a class="delete-btn" href="?delete=<?= (int)$item['id'] ?>" onclick="return confirm('Eintrag wirklich löschen?')">🗑️ Löschen</a>
            <h3>
              <?php if ($type === 'video'): ?>
                <span class="type-badge">🎬 Video</span>
              <?php else: ?>
                <span class="type-badge">🖼️ Bild</span>
              <?php endif; ?>
              #<?= (int)$item['id'] ?>
            </h3>
            <div class="media-preview">
              <?= $previewHtml ?>
            </div>
            <div class="media-meta">
              <?php if ($caption !== ''): ?>
                <p><strong>Beschreibung:</strong> <?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
              <?php if ($imageUrl !== ''): ?>
                <p><strong>Bild/Poster:</strong> 
<a href="/<?= escapeAttr($imageUrl) ?>" target="_blank" rel="noopener">Link öffnen</a>
</p>
              <?php endif; ?>
              <?php if ($type === 'video' && $videoUrl !== ''): ?>
                <p><strong>Video:</strong> <a href="<?= escapeAttr($videoUrl) ?>" target="_blank" rel="noopener">Video öffnen</a></p>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="notice" style="text-align:center;">Noch keine Medien vorhanden.</div>
    <?php endif; ?>
  </section>

  <div style="text-align:center; margin-top:40px;">
    <a class="btn btn-ghost" href="dashboard.php">← Zurück zum Dashboard</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script>
(function(){
  const pills = document.querySelectorAll('.toggle-pill');
  const panels = document.querySelectorAll('.media-type-panel');

  function switchPanel(target) {
    pills.forEach(pill => {
      pill.classList.toggle('active', pill.dataset.target === target);
      const input = pill.querySelector('input[type="radio"]');
      if (input) {
        input.checked = pill.dataset.target === target;
      }
    });

    panels.forEach(panel => {
      panel.classList.toggle('active', panel.dataset.panel === target);
    });
  }

  pills.forEach(pill => {
    pill.addEventListener('click', (event) => {
      if (pill.classList.contains('active')) return;
      const radio = pill.querySelector('input[type="radio"]');
      if (radio && radio.disabled) {
        event.preventDefault();
        return;
      }
      switchPanel(pill.dataset.target);
    });
  });
})();
</script>
</body>
</html>
<?php
/**
 * Gibt eine HTML-Vorschau für einen Video-Link zurück (YouTube, Vimeo, Direktlink).
 */
function buildVideoPreview(string $url): string
{
    $config = buildVideoEmbed($url);
    if (!$config) {
        return '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#888;">Video Vorschau nicht möglich</div>';
    }

    if ($config['mode'] === 'iframe') {
        $src = htmlspecialchars($config['src'], ENT_QUOTES, 'UTF-8');
        return '<iframe src="' . $src . '" allowfullscreen loading="lazy" title="Video"></iframe>';
    }

    if ($config['mode'] === 'video') {
        $src = htmlspecialchars($config['src'], ENT_QUOTES, 'UTF-8');
        return '<video controls preload="metadata"><source src="' . $src . '">Video kann nicht geladen werden.</video>';
    }

    return '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#888;">Video Vorschau nicht möglich</div>';
}
?>
<script src="../script.js"></script>