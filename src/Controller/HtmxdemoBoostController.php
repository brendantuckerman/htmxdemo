<?php

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * The boost demo page.
 */
class HtmxdemoBoostController extends ControllerBase {

  /**
   * Builds the page explaining the boosted navigation block.
   *
   * @return array
   *   The render array.
   */
  public function page(): array {
    return [
      '#theme' => 'htmxdemo_boost',
    ];
  }

}
