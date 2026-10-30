<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;

/**
 * Shows the total number of nodes, split by published status.
 */
#[DashboardCard(
  id: 'node_count',
  label: new TranslatableMarkup('Content count'),
  description: new TranslatableMarkup('How many nodes the site has, published and unpublished.'),
  refreshOnContentChange: TRUE,
)]
class NodeCount extends DashboardCardBase {

  /**
   * Constructs a NodeCount card.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $uuid): array {
    $total = $this->count();
    $published = $this->count(TRUE);

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-stat']],
      'value' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => number_format($total),
        '#attributes' => ['class' => ['htmxdemo-stat__value']],
      ],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->formatPlural($total, 'node in total', 'nodes in total'),
        '#attributes' => ['class' => ['htmxdemo-stat__label']],
      ],
      'breakdown' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-stat__pills']],
        'published' => $this->buildPill($this->t('@count published', ['@count' => $published]), 'success'),
        'unpublished' => $this->buildPill($this->t('@count unpublished', ['@count' => $total - $published]), 'muted'),
      ],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['user.node_grants:view', 'user.permissions'],
      ],
    ];
  }

  /**
   * Counts the nodes the current user can see.
   *
   * @param bool|null $published
   *   (optional) Limit the count to this published status.
   *
   * @return int
   *   The number of nodes.
   */
  protected function count(?bool $published = NULL): int {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->count();
    if ($published !== NULL) {
      $query->condition('status', (int) $published);
    }
    return (int) $query->execute();
  }

  /**
   * Builds a small status pill.
   *
   * @param \Stringable $text
   *   The pill text.
   * @param string $variant
   *   The pill colour variant.
   *
   * @return array
   *   A render array.
   */
  protected function buildPill(\Stringable $text, string $variant): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $text,
      '#attributes' => ['class' => ['htmxdemo-pill', 'htmxdemo-pill--' . $variant]],
    ];
  }

}
