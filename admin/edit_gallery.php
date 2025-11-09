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

/**
 * Gibt eine HTML-Vorschau für einen Video-Link zurück (YouTube, Vimeo, Direktlink).
 */
function buildVideoPreview(string $url): string
{
    $config = buildVideoEmbed($url);
    if (!$config) {
        return '<div class="gallery-admin-placeholder">Video Vorschau nicht möglich</div>';
    }

    if ($config['mode'] === 'iframe') {
        $src = htmlspecialchars($config['src'], ENT_QUOTES, 'UTF-8');
        return '<iframe src="' . $src . '" allowfullscreen loading="lazy" title="Video"></iframe>';
    }

    if ($config['mode'] === 'video') {
        $src = htmlspecialchars($config['src'], ENT_QUOTES, 'UTF-8');
        return '<video controls preload="metadata"><source src="' . $src . '">Video kann nicht geladen werden.</video>';
    }

    return '<div class="gallery-admin-placeholder">Video Vorschau nicht möglich</div>';
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
</head>
<body>
<?php include '../header.php'; ?>

<main class="inventory-page gallery-admin-page">
  <section class="inventory-header gallery-admin-header">
    <h1 class="inventory-title">Galerie verwalten</h1>
    <p class="inventory-description">
      Pflege hier die Mediengalerie der Startseite. Du kannst hochauflösende Bilder hochladen oder Videos per Link einbetten.
    </p>
    <p class="inventory-info">Aktuelle Einträge: <?= count($items); ?></p>
  </section>

  <?php if ($errorMessage !== ''): ?>
    <div class="inventory-alert inventory-alert--error">⚠️ <?= htmlspecialchars($errorMessage) ?></div>
  <?php elseif ($successMessage !== ''): ?>
    <div class="inventory-alert inventory-alert--success">✅ <?= htmlspecialchars($successMessage) ?></div>
  <?php endif; ?>

  <section class="inventory-section gallery-admin-section">
    <header>
      <h2>Neuen Eintrag hinzufügen</h2>
      <p class="inventory-section__intro">
        Wähle zuerst den Medientyp und fülle anschließend die entsprechenden Felder aus. Bilder können wahlweise hochgeladen oder per URL eingebunden werden.
      </p>
    </header>

    <form method="POST" enctype="multipart/form-data" id="mediaForm" class="inventory-form gallery-admin-form">
      <div class="inventory-radio-group gallery-admin-toggle" role="radiogroup">
        <label class="inventory-radio is-active" data-target="image">
          <input type="radio" name="media_type" value="image" checked>
          <span>🖼️ Bild</span>
        </label>
        <label class="inventory-radio" data-target="video">
          <input type="radio" name="media_type" value="video" <?= $hasMediaType && $hasVideoUrl ? '' : 'disabled'; ?>>
          <span>
            🎬 Video
            <?php if (!$hasMediaType || !$hasVideoUrl): ?>
              <small>(DB-Upgrade nötig)</small>
            <?php endif; ?>
          </span>
        </label>
      </div>

      <div class="gallery-admin-panel is-active" data-panel="image">
        <div class="form-grid two-column">
          <div class="input-control">
            <label for="image_url">Bild-URL (optional)</label>
            <input type="url" name="image_url" id="image_url" class="input-field" placeholder="https://...">
          </div>
          <div class="input-control">
            <label for="image_file">Oder Bilddatei hochladen</label>
            <input type="file" name="image_file" id="image_file" accept="image/*" class="input-field">
          </div>
        </div>
      </div>

      <div class="gallery-admin-panel" data-panel="video">
        <div class="form-grid two-column">
          <div class="input-control">
            <label for="video_url">Video-Link *</label>
            <input type="url" name="video_url" id="video_url" class="input-field" placeholder="https://youtu.be/...">
          </div>
          <div class="input-control">
            <label for="poster_url">Vorschaubild (optional)</label>
            <input type="url" name="poster_url" id="poster_url" class="input-field" placeholder="https://... (Thumbnail oder Standbild)">
          </div>
        </div>
      </div>

      <div class="form-grid">
        <div class="input-control input-control--full">
          <label for="alt_text">Beschreibung / Alt-Text</label>
          <textarea name="alt_text" id="alt_text" rows="2" placeholder="Kurze Beschreibung für Screenreader und Tooltips"></textarea>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="inventory-submit">Speichern</button>
      </div>
    </form>
  </section>

  <section class="inventory-section gallery-admin-existing">
    <header>
      <h2>Vorhandene Medien</h2>
      <p class="inventory-section__intro">Alle vorhandenen Einträge der Galerie mit direktem Zugriff auf Vorschau und Löschfunktion.</p>
    </header>
    <?php if (count($items) > 0): ?>
      <div class="gallery-admin-grid">
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
                $previewHtml = '<div class="gallery-admin-placeholder">Keine Vorschau</div>';
            }
          ?>
          <article class="gallery-admin-card">
            <header class="gallery-admin-card__header">
              <span class="gallery-admin-badge"><?= $type === 'video' ? '🎬 Video' : '🖼️ Bild' ?></span>
              <a class="gallery-admin-delete" href="?delete=<?= (int)$item['id'] ?>" onclick="return confirm('Eintrag wirklich löschen?')">🗑️ Löschen</a>
            </header>
            <div class="gallery-admin-preview">
              <?= $previewHtml ?>
            </div>
            <div class="gallery-admin-meta">
              <p class="gallery-admin-meta__id">Eintrag #<?= (int)$item['id'] ?></p>
              <?php if ($caption !== ''): ?>
                <p><strong>Beschreibung:</strong> <?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?></p>
              <?php endif; ?>
              <?php if ($imageUrl !== ''): ?>
                <p><strong>Bild/Poster:</strong> <a href="/<?= escapeAttr($imageUrl) ?>" target="_blank" rel="noopener">Link öffnen</a></p>
              <?php endif; ?>
              <?php if ($type === 'video' && $videoUrl !== ''): ?>
                <p><strong>Video:</strong> <a href="<?= escapeAttr($videoUrl) ?>" target="_blank" rel="noopener">Video öffnen</a></p>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="inventory-note">Noch keine Medien vorhanden.</p>
    <?php endif; ?>
  </section>

  <div class="gallery-admin-actions">
    <a class="inventory-submit inventory-submit--ghost" href="dashboard.php">← Zurück zum Dashboard</a>
  </div>
</main>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script>
(function(){
  const radios = document.querySelectorAll('.gallery-admin-toggle .inventory-radio');
  const panels = document.querySelectorAll('.gallery-admin-panel');

  function switchPanel(target) {
    radios.forEach(radio => {
      radio.classList.toggle('is-active', radio.dataset.target === target);
      const input = radio.querySelector('input[type="radio"]');
      if (input) {
        input.checked = radio.dataset.target === target;
      }
    });

    panels.forEach(panel => {
      panel.classList.toggle('is-active', panel.dataset.panel === target);
    });
  }

  radios.forEach(radio => {
    radio.addEventListener('click', (event) => {
      if (radio.classList.contains('is-active')) {
        return;
      }

      const input = radio.querySelector('input[type="radio"]');
      if (input && input.disabled) {
        event.preventDefault();
        return;
      }

      switchPanel(radio.dataset.target);
    });
  });
})();
</script>
<script src="../script.js"></script>
</body>
</html>