<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a dashboard card plugin attribute.
 *
 * Each card type on the htmx dashboard is a plugin. The definition describes
 * how the card shell behaves; the plugin class builds the card body.
 *
 * @see \Drupal\htmxdemo\DashboardCardInterface
 * @see \Drupal\htmxdemo\DashboardCardManager
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class DashboardCard extends Plugin {

  /**
   * Constructs a DashboardCard attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The human-readable card name, shown in the card header and add menu.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $description
   *   (optional) A short description of what the card shows.
   * @param string $size
   *   (optional) The grid footprint of the card: 'small' or 'wide'.
   * @param bool $multiple
   *   (optional) Whether the card may be added to a dashboard more than once.
   * @param string|null $poll
   *   (optional) An htmx interval, such as '10s', to reload the card body.
   * @param bool $refreshOnContentChange
   *   (optional) Whether the card reloads when content is created on the
   *   dashboard.
   * @param class-string|null $deriver
   *   (optional) The deriver class.
   */
  public function __construct(
    public readonly string $id,
    public readonly TranslatableMarkup $label,
    public readonly ?TranslatableMarkup $description = NULL,
    public readonly string $size = 'small',
    public readonly bool $multiple = TRUE,
    public readonly ?string $poll = NULL,
    public readonly bool $refreshOnContentChange = FALSE,
    public readonly ?string $deriver = NULL,
  ) {}

}
