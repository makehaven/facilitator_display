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
    $refresh_interval = ($config->get('refresh_interval') ?: 30) * 1000; // Convert to milliseconds
    $background_image_url = $config->get('background_image_url');

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

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <title>Facilitator Display</title>
  <style>{$css}</style>
</head>
<body {$body_style}>
  <header>
    <h1>Facilitators On Site</h1>
    <div id="clock-container"><span id="clock"></span></div>
  </header>
  <div id="facilitator-grid" class="facilitator-grid"></div>

  <script>
    (function() {
      const feedUrl = '{$feed_url}';
      const grid = document.getElementById('facilitator-grid');
      const clockEl = document.getElementById('clock');

      function updateClock() {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      }
      setInterval(updateClock, 1000);
      updateClock();

      function timeAgo(timestamp, now) {
        if (!timestamp) return null;
        
        // Check if timestamp is from today
        const date = new Date(timestamp * 1000);
        const nowDate = new Date(now * 1000);
        
        if (date.getDate() !== nowDate.getDate() || 
            date.getMonth() !== nowDate.getMonth() || 
            date.getFullYear() !== nowDate.getFullYear()) {
          return null; // Not today
        }

        const diff = Math.floor((now - timestamp)); // Difference in seconds
        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return null;
      }

      async function updateDisplay() {
        try {
          const response = await fetch(feedUrl);
          if (!response.ok) throw new Error('Network response was not ok ' + response.statusText);
          const data = await response.json();
          const nowTs = data.now || Math.floor(Date.now() / 1000);

          grid.innerHTML = ''; // Clear the grid

          if (data.items.length === 0) {
            grid.innerHTML = '<p>No facilitators scheduled for today.</p>';
            return;
          }

          data.items.forEach(facilitator => {
            const card = document.createElement('div');
            card.className = 'facilitator-card' + (facilitator.present ? ' present' : '');

            const photoUrl = facilitator.photo || 'https://makehaven.org/sites/default/files/default_images/default-user-icon.png'; // Fallback image
            
            let statusHtml = '';
            const ago = timeAgo(facilitator.last_seen, nowTs);
            
            if (facilitator.present) {
              // Green Light
              statusHtml = `<div class="facilitator-status present">
                🟢 On-site: \${facilitator.door} \${ago ? '<span class="time-ago">(' + ago + ')</span>' : ''}
              </div>`;
            } else if (ago) {
              // Yellow/Gray Light - Was here earlier TODAY
              statusHtml = `<div class="facilitator-status last-seen">
                ⚪ Last seen: \${ago}
              </div>`;
            } else {
              // No show yet (or last seen was not today)
              statusHtml = `<div class="facilitator-status off-site">⚪ Off-site</div>`;
            }

            card.innerHTML = `
              <img src="\${photoUrl}" class="facilitator-photo" alt="\${facilitator.name}">
              <div class="facilitator-info">
                <div class="facilitator-name">\${facilitator.name}</div>
                <div class="facilitator-focus">\${facilitator.focus || ''}</div>
                <div class="facilitator-schedule">\${facilitator.schedule || ''}</div>
                \${statusHtml}
              </div>
            `;
            grid.appendChild(card);
          });
        } catch (error) {
          console.error('Error fetching facilitator feed:', error);
        }
      }

      updateDisplay();
      setInterval(updateDisplay, {$refresh_interval});
    })();
  </script>
</body>
</html>
HTML;

    return new Response($html);
  }
}