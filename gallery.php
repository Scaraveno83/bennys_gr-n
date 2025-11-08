<?php
// --- DEBUG optional ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once 'includes/db.php';

/**
 * Ensure the gallery table has the columns that the modern gallery requires.
 * Falls silently back if privileges are missing.
 *
 * @return array{media_type:bool,video_url:bool}
 */
function ensureGallerySchema(PDO $pdo): array
{
    $columns = [];
    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM gallery');
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        return ['media_type' => false, 'video_url' => false];
    }

    $hasMediaType = in_array('media_type', $columns, true);
    $hasVideoUrl  = in_array('video_url', $columns, true);

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

$columnInfo = ensureGallerySchema($pdo);
$hasMediaType = $columnInfo['media_type'];
$hasVideoUrl  = $columnInfo['video_url'];

$queryColumns = ['id', 'image_url', 'alt_text'];
if ($hasMediaType) {
    $queryColumns[] = 'media_type';
}
if ($hasVideoUrl) {
    $queryColumns[] = 'video_url';
}

$query = 'SELECT ' . implode(', ', $queryColumns) . ' FROM gallery ORDER BY id DESC';
$stmt = $pdo->query($query);
$galleryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Convert a given video URL into an embeddable configuration.
 *
 * @param string $url
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

    // YouTube (long + short URLs)
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

    // Vimeo
    if (strpos($host, 'vimeo.com') !== false) {
        $path = parse_url($cleanUrl, PHP_URL_PATH) ?: '';
        if (preg_match('~/([0-9]+)~', $path, $matches)) {
            $embedUrl = 'https://player.vimeo.com/video/' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            return ['mode' => 'iframe', 'src' => $embedUrl];
        }
    }

    // Direct video file
    if (preg_match('/\.(mp4|webm|ogg|ogv)(\?.*)?$/i', $cleanUrl)) {
        return ['mode' => 'video', 'src' => htmlspecialchars($cleanUrl, ENT_QUOTES, 'UTF-8')];
    }

    return ['mode' => 'iframe', 'src' => htmlspecialchars($cleanUrl, ENT_QUOTES, 'UTF-8')];
}

function escapeAttr(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Galerie | Benny’s Werkstatt</title>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="header.css">
  <link rel="stylesheet" href="styles.css">
</head>
<body class="gallery-body">
<?php include 'header.php'; ?>

<main class="gallery-main">
  <section class="gallery-hero">
    <div class="gallery-hero-inner">
      <h1>Galerie</h1>
      <p>Einblicke in Projekte, Fahrzeuge und Events – jetzt auch mit Video-Highlights.</p>
    </div>
  </section>

  <section class="gallery-controls">
    <div class="card glass control-card">
      <span class="control-label">Anzeige:</span>
      <div class="control-buttons">
        <button type="button" class="filter-btn is-active" data-filter="all">Alles</button>
        <button type="button" class="filter-btn" data-filter="image">Bilder</button>
        <button type="button" class="filter-btn" data-filter="video">Videos</button>
      </div>
    </div>
  </section>

  <section class="media-grid" id="galleryGrid">
    <?php if (!empty($galleryItems)): ?>
      <?php foreach ($galleryItems as $item): ?>
        <?php
          $mediaType = $hasMediaType ? ($item['media_type'] ?? 'image') : 'image';
          $mediaType = $mediaType === 'video' ? 'video' : 'image';
          $caption   = trim($item['alt_text'] ?? '');

          $imageUrl = escapeAttr($item['image_url'] ?? '');
          $videoUrl = $hasVideoUrl ? trim((string)($item['video_url'] ?? '')) : '';
          $embedConfig = null;
          $modalType = 'image';
          $modalSrc  = $imageUrl;
          $modalLabel = 'Bild vergrößern';

          if ($mediaType === 'video') {
              $embedConfig = buildVideoEmbed($videoUrl !== '' ? $videoUrl : ($item['image_url'] ?? ''));
              if ($embedConfig) {
                  if ($embedConfig['mode'] === 'iframe') {
                      $modalType = 'video-iframe';
                      $modalSrc  = $embedConfig['src'];
                      $modalLabel = 'Video abspielen';
                  } else {
                      $modalType = 'video-file';
                      $modalSrc  = $embedConfig['src'];
                      $modalLabel = 'Video abspielen';
                  }
              } else {
                  $mediaType = 'image';
                  $embedConfig = null;
              }
          }

          $badgeLabel = $mediaType === 'video' ? 'Video' : 'Bild';
        ?>
        <article class="media-card glass" data-media-type="<?= $mediaType ?>">
          <figure class="media-figure">
            <div class="media-frame">
              <span class="media-badge" aria-hidden="true"><?= $badgeLabel ?></span>
              <?php if ($mediaType === 'image'): ?>
                <?php if ($imageUrl !== ''): ?>
                  <img src="<?= $imageUrl ?>" alt="<?= escapeAttr($caption ?: 'Galeriebild') ?>" loading="lazy">
                <?php else: ?>
                  <div class="media-placeholder">Keine Vorschau</div>
                <?php endif; ?>
              <?php elseif ($embedConfig && $embedConfig['mode'] === 'iframe'): ?>
                <div class="video-embed">
                  <iframe src="<?= $embedConfig['src'] ?>" loading="lazy" allowfullscreen title="Video"></iframe>
                </div>
              <?php elseif ($embedConfig): ?>
                <video preload="metadata" muted playsinline loop aria-hidden="true">
                  <source src="<?= $embedConfig['src'] ?>">
                  Dein Browser unterstützt das Video-Format nicht.
                </video>
              <?php endif; ?>
              <?php if ($modalSrc !== ''): ?>
                <button class="media-open" type="button"
                        data-type="<?= $modalType ?>"
                        data-src="<?= $modalSrc ?>"
                        data-caption="<?= escapeAttr($caption) ?>"
                        aria-label="<?= escapeAttr($modalLabel) ?>">
                  <span class="media-open-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
                      <path d="M12 5a7 7 0 1 0 7 7a7 7 0 0 0-7-7m0-2a9 9 0 1 1-9 9a9 9 0 0 1 9-9m0 4a1 1 0 0 1 1 1v3h3a1 1 0 0 1 0 2h-4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1" />
                    </svg>
                  </span>
                  <span class="sr-only">Ansehen</span>
                </button>
              <?php endif; ?>
            </div>
            <?php if ($caption !== ''): ?>
              <figcaption class="media-caption"><?= htmlspecialchars($caption, ENT_QUOTES, 'UTF-8') ?></figcaption>
            <?php endif; ?>
          </figure>
        </article>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-state glass">
        <h2>Noch keine Medien vorhanden</h2>
        <p>Sobald Bilder oder Videos veröffentlicht sind, erscheinen sie hier automatisch.</p>
      </div>
    <?php endif; ?>
  </section>
</main>

<div class="media-modal" id="mediaModal" hidden>
  <button class="modal-close" type="button" aria-label="Schließen">×</button>
  <div class="media-modal-content" role="dialog" aria-modal="true"></div>
  <p class="modal-caption" id="modalCaption"></p>
</div>

<footer id="main-footer">
  <p>&copy; <?= date('Y'); ?> Benny's Werkstatt – Alle Rechte vorbehalten.</p>
  <a href="#top" id="toTop" class="footer-btn">Nach oben ↑</a>
</footer>

<script src="script.js"></script>
<script>
(function(){
  const filterButtons = document.querySelectorAll('.filter-btn');
  const cards = document.querySelectorAll('.media-card');
  const modal = document.getElementById('mediaModal');
  const modalClose = modal ? modal.querySelector('.modal-close') : null;
  const modalContent = modal ? modal.querySelector('.media-modal-content') : null;
  const modalCaption = modal ? document.getElementById('modalCaption') : null;

  function applyFilter(type) {
    cards.forEach(card => {
      const cardType = card.getAttribute('data-media-type');
      const visible = type === 'all' || cardType === type;
      card.style.display = visible ? '' : 'none';
    });
  }

  filterButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      filterButtons.forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      applyFilter(btn.getAttribute('data-filter'));
    });
  });

  const openButtons = document.querySelectorAll('.media-open');
  openButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      if (!modal || !modalContent) return;
      const type = btn.getAttribute('data-type');
      const src = btn.getAttribute('data-src');
      const caption = btn.getAttribute('data-caption') || '';

      modalContent.innerHTML = '';
      let element;
      if (type === 'image') {
        element = document.createElement('img');
        element.src = src;
        element.alt = caption || 'Galeriebild';
      } else if (type === 'video-iframe') {
        element = document.createElement('iframe');
        element.src = src;
        element.loading = 'lazy';
        element.allowFullscreen = true;
        element.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
        element.title = caption || 'Video';
      } else if (type === 'video-file') {
        element = document.createElement('video');
        element.controls = true;
        element.preload = 'metadata';
        const source = document.createElement('source');
        source.src = src;
        element.appendChild(source);
      }

      if (element) {
        modalContent.appendChild(element);
        modal.removeAttribute('hidden');
        document.body.classList.add('modal-open');
      }
      if (modalCaption) {
        modalCaption.textContent = caption;
      }
    });
  });

  function closeModal() {
    if (!modal) return;
    modal.setAttribute('hidden', 'hidden');
    document.body.classList.remove('modal-open');
    if (modalContent) {
      modalContent.innerHTML = '';
    }
  }

  if (modalClose) {
    modalClose.addEventListener('click', closeModal);
  }
  if (modal) {
    modal.addEventListener('click', (event) => {
      if (event.target === modal) {
        closeModal();
      }
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && !modal.hasAttribute('hidden')) {
        closeModal();
      }
    });
  }
})();
</script>
</body>
</html>