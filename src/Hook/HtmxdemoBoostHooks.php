<?php

namespace Drupal\htmxdemo\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hooks for the htmx boost demo route.
 */
class HtmxdemoBoostHooks {

  public function __construct(
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_theme_suggestions_page_alter().
   */
  #[Hook('theme_suggestions_page_alter')]
  public function themeSuggestionsPageAlter(array &$suggestions, array $variables): void {
    if ($this->routeMatch->getRouteName() === 'htmxdemo.boost') {
      $suggestions[] = 'page__htmxdemo_boost';
    }
  }

}
