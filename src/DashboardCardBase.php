<?php

declare(strict_types=1);

namespace Drupal\htmxdemo;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Provides a base class for htmx dashboard card plugins.
 *
 * Plugins are created through PluginBase::create(), which autowires any
 * services declared after the standard plugin constructor arguments.
 */
abstract class DashboardCardBase extends PluginBase implements DashboardCardInterface, ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return (string) ($this->pluginDefinition['description'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function getSize(): string {
    return $this->pluginDefinition['size'] ?? 'small';
  }

  /**
   * {@inheritdoc}
   */
  public function allowsMultiple(): bool {
    return $this->pluginDefinition['multiple'] ?? TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrigger(): string {
    // Every card body loads as soon as htmx processes the card shell.
    $triggers = ['load'];
    if (!empty($this->pluginDefinition['poll'])) {
      $triggers[] = 'every ' . $this->pluginDefinition['poll'];
    }
    if (!empty($this->pluginDefinition['refreshOnContentChange'])) {
      $triggers[] = self::CONTENT_CHANGED_EVENT . ' from:body';
    }
    return implode(', ', $triggers);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * Returns the element ID of a card's outer shell.
   *
   * @param string $uuid
   *   The card instance UUID.
   *
   * @return string
   *   The element ID.
   */
  public static function cardElementId(string $uuid): string {
    return 'htmxdemo-card-' . $uuid;
  }

  /**
   * Returns the element ID of a card's body, the target of its htmx swaps.
   *
   * @param string $uuid
   *   The card instance UUID.
   *
   * @return string
   *   The element ID.
   */
  public static function bodyElementId(string $uuid): string {
    return self::cardElementId($uuid) . '-body';
  }

  /**
   * Returns the URL of this card's body route.
   *
   * @param string $uuid
   *   The card instance UUID.
   *
   * @return \Drupal\Core\Url
   *   The card body URL.
   */
  protected function bodyUrl(string $uuid): Url {
    return Url::fromRoute('htmxdemo.dashboard.card_body', ['uuid' => $uuid]);
  }

  /**
   * Builds a consistent empty state for cards with nothing to show.
   *
   * @param string|\Stringable $message
   *   The message to show.
   *
   * @return array
   *   A render array.
   */
  protected function buildEmpty(string|\Stringable $message): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $message,
      '#attributes' => ['class' => ['htmxdemo-card__empty']],
    ];
  }

}
