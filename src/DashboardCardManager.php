<?php

declare(strict_types=1);

namespace Drupal\htmxdemo;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Session\AccountInterface;
use Drupal\htmxdemo\Attribute\DashboardCard;

/**
 * Plugin manager for htmx dashboard cards.
 *
 * @see \Drupal\htmxdemo\Attribute\DashboardCard
 * @see \Drupal\htmxdemo\DashboardCardInterface
 * @see plugin_api
 */
class DashboardCardManager extends DefaultPluginManager {

  /**
   * Constructs a DashboardCardManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/DashboardCard', $namespaces, $module_handler, DashboardCardInterface::class, DashboardCard::class);
    $this->alterInfo('htmxdemo_dashboard_card_info');
    $this->setCacheBackend($cache_backend, 'htmxdemo_dashboard_card_plugins');
  }

  /**
   * Returns the card types that may currently be added to a dashboard.
   *
   * Card types the account cannot access are left out, as are single-instance
   * card types that are already on the dashboard.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account viewing the dashboard.
   * @param array<string, string> $layout
   *   The current layout, plugin IDs keyed by card UUID.
   *
   * @return array<string, string>
   *   Card labels keyed by plugin ID, sorted by label.
   */
  public function getAddableOptions(AccountInterface $account, array $layout): array {
    $options = [];
    foreach (array_keys($this->getDefinitions()) as $plugin_id) {
      /** @var \Drupal\htmxdemo\DashboardCardInterface $card */
      $card = $this->createInstance($plugin_id);
      if (!$card->access($account)->isAllowed()) {
        continue;
      }
      if (!$card->allowsMultiple() && in_array($plugin_id, $layout, TRUE)) {
        continue;
      }
      $options[$plugin_id] = $card->label();
    }
    asort($options, SORT_NATURAL | SORT_FLAG_CASE);
    return $options;
  }

}
