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

    $css = "
      body { font-family: sans-serif; background-color: #f0f0f0; }
      .facilitator-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; padding: 20px; }
      .facilitator-card { background-color: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
      .facilitator-card.present { border-left: 5px solid #4CAF50; }
      .facilitator-photo { width: 100%; height: 200px; object-fit: cover; }
      .facilitator-info { padding: 15px; }
      .facilitator-name { font-size: 1.2em; font-weight: bold; }
      .facilitator-focus, .facilitator-schedule, .facilitator-status { margin-top: 10px; }
    ";

    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <title>Facilitator Display</title>
  <style>{$css}</style>
</head>
<body {$body_style}>
  <h1>Facilitators On Site</h1>
  <div id="facilitator-grid" class="facilitator-grid"></div>

  <script>
    (function() {
      const feedUrl = '{$feed_url}';
      const grid = document.getElementById('facilitator-grid');

      async function updateDisplay() {
        const response = await fetch(feedUrl);
        const data = await response.json();

        grid.innerHTML = ''; // Clear the grid

        data.items.forEach(facilitator => {
          const card = document.createElement('div');
          card.className = 'facilitator-card' + (facilitator.present ? ' present' : '');

          card.innerHTML = `
            <img src="\${facilitator.photo || '/path/to/default/photo.jpg'}" class="facilitator-photo" alt="\${facilitator.name}">
            <div class="facilitator-info">
              <div class="facilitator-name">\${facilitator.name}</div>
              <div class="facilitator-focus">\${facilitator.focus || ''}</div>
              <div class="facilitator-schedule">\${facilitator.schedule || ''}</div>
              <div class="facilitator-status">\${facilitator.present ? 'On-site: ' + facilitator.door : 'Off-site'}</div>
            </div>
          `;
          grid.appendChild(card);
        });
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