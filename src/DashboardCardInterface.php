<?php

declare(strict_types=1);

namespace Drupal\htmxdemo;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the interface for htmx dashboard card plugins.
 */
interface DashboardCardInterface extends PluginInspectionInterface {

  /**
   * The event fired when content is created from the dashboard.
   *
   * Cards with refreshOnContentChange set listen for this event on the body.
   * Event names must not contain a colon, as htmx would read the colon as a
   * trigger modifier.
   */
  public const string CONTENT_CHANGED_EVENT = 'htmxdemo-content-changed';

  /**
   * Returns the card label.
   *
   * @return string
   *   The human-readable card name.
   */
  public function label(): string;

  /**
   * Returns the card description.
   *
   * @return string
   *   A short description of what the card shows.
   */
  public function description(): string;

  /**
   * Returns the grid footprint of the card.
   *
   * @return string
   *   Either 'small' or 'wide'.
   */
  public function getSize(): string;

  /**
   * Whether the card may appear on a dashboard more than once.
   *
   * @return bool
   *   TRUE if several instances are allowed.
   */
  public function allowsMultiple(): bool;

  /**
   * Returns the htmx trigger specification used to load the card body.
   *
   * @return string
   *   A value for the hx-trigger attribute.
   */
  public function getTrigger(): string;

  /**
   * Checks whether an account may see this card.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface;

  /**
   * Builds the card body.
   *
   * @param string $uuid
   *   The UUID of this card instance on the dashboard. Use it to keep element
   *   IDs unique and to build URLs back to the card body route.
   *
   * @return array
   *   A render array for the card body.
   */
  public function build(string $uuid): array;

}
