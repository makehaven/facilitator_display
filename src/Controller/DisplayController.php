<?php

namespace Drupal\facilitator_display\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Controller for the facilitator display page.
 */
class DisplayController extends ControllerBase {

  /**
   * Renders the facilitator display page.
   *
   * @param string $code_word
   * The secret code word for access.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   * The rendered page.
   */
  public function displayPage($code_word = '') {
    $config = $this->config('facilitator_display.settings');
    $config_code_word = $config->get('code_word');

    if ($config_code_word && $code_word !== $config_code_word) {
      throw new AccessDeniedHttpException();
    }

    $feed_url = '/facilitator-display/feed';
    $fallback_feed_url = '/facilitator-display/fallback-feed';
    $refresh_interval = ($config->get('refresh_interval') ?: 30) * 1000; // Convert to milliseconds
    $background_image_url = $config->get('background_image_url');

    $fallback_enabled = $config->get('fallback_enabled');
    if ($fallback_enabled === NULL) {
      $fallback_enabled = TRUE;
    }
    $fallback_threshold = (int) ($config->get('fallback_threshold') ?: 5);
    $qr_target_url = $config->get('qr_target_url') ?: '/facilitator/schedules';
    $qr_caption = $config->get('qr_caption') ?: 'Scan for the facilitator schedule';

    $fallback_enabled_js = $fallback_enabled ? 'true' : 'false';
    $qr_target_url_js = json_encode($qr_target_url);
    $qr_caption_js = json_encode($qr_caption);

    $body_style = '';
    if ($background_image_url) {
      $body_style = "style=\"background-image: url('{$background_image_url}'); background-size: cover; background-position: center;\"";
    }

    $default_css = "
      body { font-family: sans-serif; background-color: #f0f0f0; margin: 0; padding: 20px; }
      header { position: relative; margin-bottom: 20px; display: flex; justify-content: center; align-items: center; height: 60px; }
      h1 { margin: 0; font-size: 2.5rem; text-shadow: 0 1px 2px rgba(255,255,255,0.8); color: #333; }
      #clock-container { position: absolute; right: 0; background: #333; color: #fff; padding: 5px 15px; border-radius: 6px; font-family: monospace; font-size: 2rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
      .facilitator-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; padding-top: 20px; }
      .facilitator-card { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; display: flex; flex-direction: column; }
      .facilitator-card.present { border-left: 8px solid #4CAF50; }
      .facilitator-photo { width: 100%; height: 250px; object-fit: cover; }
      .facilitator-info { padding: 15px; flex-grow: 1; display: flex; flex-direction: column; }
      .facilitator-name { font-size: 1.4em; font-weight: bold; margin-bottom: 5px; }
      .facilitator-focus { color: #666; margin-bottom: 10px; font-style: italic; }
      .facilitator-schedule { font-weight: 500; margin-top: auto; padding-top: 10px; border-top: 1px solid #eee; }
      .facilitator-status { margin-top: 8px; font-weight: bold; padding: 5px; border-radius: 4px; display: inline-block; }
      .facilitator-status.present { color: #2e7d32; background: #e8f5e9; }
      .facilitator-status.last-seen { color: #f57f17; background: #fffde7; }
      .facilitator-status.off-site { color: #757575; background: #f5f5f5; }
      .time-ago { font-weight: normal; font-size: 0.9em; }
    ";

    $css = $config->get('custom_css') ?: $default_css;

    // Fallback panel styles are always appended so they can't be lost when
    // an admin overrides custom_css.
    $fallback_css = "
      #fallback-panel { display: none; gap: 24px; margin-top: 24px; padding: 24px; background: rgba(255,255,255,0.95); border-radius: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); align-items: stretch; }
      #fallback-panel.visible { display: flex; }
      #fallback-panel.fullscreen { min-height: 78vh; align-items: center; }
      .fallback-qr { flex: 0 0 320px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; }
      .fallback-qr h2 { font-size: 1.6rem; margin: 0 0 16px 0; color: #333; }
      .fallback-qr #qrcode { padding: 14px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: inline-block; }
      .fallback-qr #qrcode img, .fallback-qr #qrcode canvas { display: block; }
      .fallback-qr .qr-caption { margin-top: 14px; font-size: 1.05rem; color: #555; max-width: 280px; line-height: 1.3; }
      .fallback-tile { flex: 1; min-width: 0; display: flex; flex-direction: column; }
      .fallback-tile h2 { font-size: 1.6rem; margin: 0 0 14px 0; color: #333; }
      .fallback-tile ul { list-style: none; padding: 0; margin: 0; }
      .fallback-tile li { padding: 10px 4px; border-bottom: 1px solid #eee; font-size: 1.15rem; display: flex; justify-content: space-between; gap: 16px; align-items: baseline; }
      .fallback-tile li:last-child { border-bottom: none; }
      .fallback-tile .tile-label { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
      .fallback-tile .tile-meta { color: #888; font-size: 0.95rem; flex-shrink: 0; }
      .fallback-tile .tile-empty { color: #888; font-style: italic; padding: 8px 0; }
      .fallback-panel.fullscreen .fallback-qr { flex: 0 0 420px; }
      .fallback-panel.fullscreen .fallback-qr h2 { font-size: 2rem; }
      .fallback-panel.fullscreen .fallback-tile h2 { font-size: 2rem; }
      .fallback-panel.fullscreen .fallback-tile li { font-size: 1.4rem; padding: 14px 4px; }
    ";

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <title>Facilitator Display</title>
  <style>{$css}
{$fallback_css}</style>
  <script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs/qrcode.min.js"></script>
</head>
<body {$body_style}>
  <header>
    <h1>Facilitators On Site</h1>
    <div id="clock-container"><span id="clock"></span></div>
  </header>
  <div id="facilitator-grid" class="facilitator-grid"></div>
  <div id="fallback-panel">
    <div class="fallback-qr">
      <h2>Facilitator Schedule</h2>
      <div id="qrcode"></div>
      <div class="qr-caption" id="qr-caption"></div>
    </div>
    <div class="fallback-tile" id="fallback-tile"></div>
  </div>

  <script>
    (function() {
      const feedUrl = '{$feed_url}';
      const fallbackFeedUrl = '{$fallback_feed_url}';
      const fallbackEnabled = {$fallback_enabled_js};
      const fallbackThreshold = {$fallback_threshold};
      const qrTargetUrl = {$qr_target_url_js};
      const qrCaption = {$qr_caption_js};

      const grid = document.getElementById('facilitator-grid');
      const clockEl = document.getElementById('clock');
      const fallbackPanel = document.getElementById('fallback-panel');
      const fallbackTile = document.getElementById('fallback-tile');
      const qrCaptionEl = document.getElementById('qr-caption');

      function updateClock() {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', timeZone: 'America/New_York' });
      }
      setInterval(updateClock, 1000);
      updateClock();

      function timeAgo(timestamp, now) {
        if (!timestamp) return null;
        const date = new Date(timestamp * 1000);
        const nowDate = new Date(now * 1000);
        if (date.getDate() !== nowDate.getDate() ||
            date.getMonth() !== nowDate.getMonth() ||
            date.getFullYear() !== nowDate.getFullYear()) {
          return null;
        }
        const diff = Math.floor((now - timestamp));
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return null;
      }

      function relTime(ts) {
        if (!ts) return '';
        const diff = Math.floor(Date.now() / 1000) - ts;
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff/60) + 'm ago';
        if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
        return Math.floor(diff/86400) + 'd ago';
      }

      function formatEventStart(ts) {
        if (!ts) return '';
        const d = new Date(ts * 1000);
        const day = d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
        const tm = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        return day + ' · ' + tm;
      }

      function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, function(c) {
          return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
        });
      }

      let qrReady = false;
      function ensureQr() {
        if (qrReady || !window.QRCode) return;
        try {
          const target = qrTargetUrl.indexOf('http') === 0
            ? qrTargetUrl
            : window.location.origin + qrTargetUrl;
          new QRCode(document.getElementById('qrcode'), {
            text: target,
            width: 240,
            height: 240,
            correctLevel: QRCode.CorrectLevel.M
          });
          qrCaptionEl.textContent = qrCaption;
          qrReady = true;
        } catch (e) {
          console.error('QR render failed', e);
        }
      }

      let fallbackData = { top_badges: [], recent_badges: [], upcoming_events: [] };
      let tileIndex = 0;
      const tileTypes = ['top_badges', 'recent_badges', 'upcoming_events'];

      async function loadFallback() {
        try {
          const r = await fetch(fallbackFeedUrl);
          if (!r.ok) throw new Error('fallback feed http ' + r.status);
          fallbackData = await r.json();
        } catch (e) {
          console.error('fallback feed error', e);
        }
      }

      function renderTile() {
        const type = tileTypes[tileIndex % tileTypes.length];
        let html = '';
        if (type === 'top_badges') {
          const items = fallbackData.top_badges || [];
          html = '<h2>Top Badge Earners</h2>';
          html += items.length
            ? '<ul>' + items.map(b => '<li><span class="tile-label">' + escapeHtml(b.name) + '</span><span class="tile-meta">' + b.count + ' badges</span></li>').join('') + '</ul>'
            : '<div class="tile-empty">No badge data yet.</div>';
        } else if (type === 'recent_badges') {
          const items = fallbackData.recent_badges || [];
          html = '<h2>Recently Earned Badges</h2>';
          html += items.length
            ? '<ul>' + items.map(b => '<li><span class="tile-label">' + escapeHtml(b.member) + ' — ' + escapeHtml(b.badge) + '</span><span class="tile-meta">' + escapeHtml(relTime(b.when)) + '</span></li>').join('') + '</ul>'
            : '<div class="tile-empty">No recent badges.</div>';
        } else if (type === 'upcoming_events') {
          const items = fallbackData.upcoming_events || [];
          html = '<h2>Upcoming Workshops &amp; Events</h2>';
          html += items.length
            ? '<ul>' + items.map(e => '<li><span class="tile-label">' + escapeHtml(e.title) + '</span><span class="tile-meta">' + escapeHtml(formatEventStart(e.start)) + '</span></li>').join('') + '</ul>'
            : '<div class="tile-empty">No upcoming events.</div>';
        }
        fallbackTile.innerHTML = html;
        tileIndex++;
      }

      function applyFallbackVisibility(count) {
        if (!fallbackEnabled) {
          fallbackPanel.classList.remove('visible', 'fullscreen');
          return;
        }
        if (count >= fallbackThreshold) {
          fallbackPanel.classList.remove('visible', 'fullscreen');
          return;
        }
        fallbackPanel.classList.add('visible');
        if (count === 0) {
          fallbackPanel.classList.add('fullscreen');
        } else {
          fallbackPanel.classList.remove('fullscreen');
        }
        ensureQr();
        if (!fallbackTile.innerHTML) {
          renderTile();
        }
      }

      async function updateDisplay() {
        try {
          const response = await fetch(feedUrl);
          if (!response.ok) throw new Error('Network response was not ok ' + response.statusText);
          const data = await response.json();
          const nowTs = data.now || Math.floor(Date.now() / 1000);

          grid.innerHTML = '';
          const items = data.items || [];

          items.forEach(facilitator => {
            const card = document.createElement('div');
            card.className = 'facilitator-card' + (facilitator.present ? ' present' : '');

            const photoUrl = facilitator.photo || 'https://makehaven.org/sites/default/files/default_images/default-user-icon.png';

            let statusHtml = '';
            const ago = timeAgo(facilitator.last_seen, nowTs);

            if (facilitator.present) {
              statusHtml = '<div class="facilitator-status present">🟢 On-site: ' + escapeHtml(facilitator.door || '') + (ago ? ' <span class="time-ago">(' + ago + ')</span>' : '') + '</div>';
            } else if (ago) {
              statusHtml = '<div class="facilitator-status last-seen">⚪ Last seen: ' + ago + '</div>';
            } else {
              statusHtml = '<div class="facilitator-status off-site">⚪ Off-site</div>';
            }

            card.innerHTML =
              '<img src="' + photoUrl + '" class="facilitator-photo" alt="' + escapeHtml(facilitator.name) + '">' +
              '<div class="facilitator-info">' +
                '<div class="facilitator-name">' + escapeHtml(facilitator.name) + '</div>' +
                '<div class="facilitator-focus">' + escapeHtml(facilitator.focus || '') + '</div>' +
                '<div class="facilitator-schedule">' + escapeHtml(facilitator.schedule || '') + '</div>' +
                statusHtml +
              '</div>';
            grid.appendChild(card);
          });

          applyFallbackVisibility(items.length);
        } catch (error) {
          console.error('Error fetching facilitator feed:', error);
        }
      }

      // Kick everything off.
      loadFallback().then(() => {
        renderTile();
      });
      updateDisplay();

      // Refresh cadences.
      setInterval(updateDisplay, {$refresh_interval});
      setInterval(renderTile, 10000);        // rotate tile every 10s
      setInterval(loadFallback, 300000);     // refresh fallback data every 5 min
    })();
  </script>
</body>
</html>
HTML;

    return new Response($html);
  }
}
