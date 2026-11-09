<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * A dice roll whose single response updates several parts of the page.
 *
 * @see templates/htmxdemo-partials-response.html.twig
 */
class HtmxdemoPartialsController extends ControllerBase {

  /**
   * Builds the page with the roll button and the areas it updates.
   *
   * @return array
   *   The render array.
   */
  public function page(): array {
    return [
      '#theme' => 'htmxdemo_partials',
    ];
  }

  /**
   * Returns a new roll as a response containing only hx-partial elements.
   *
   * @return array
   *   The render array.
   */
  public function roll(): array {
    return [
      '#theme' => 'htmxdemo_partials_response',
      '#roll' => random_int(1, 6),
      // Every response is a new roll, so never cache it. The route's no_cache
      // option keeps it out of the anonymous page cache, which ignores
      // max-age.
      '#cache' => ['max-age' => 0],
    ];
  }

}
