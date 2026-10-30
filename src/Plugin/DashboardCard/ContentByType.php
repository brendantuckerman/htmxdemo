<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;

/**
 * Shows a bar chart of nodes per content type, drawn with CSS only.
 */
#[DashboardCard(
  id: 'content_by_type',
  label: new TranslatableMarkup('Content by type'),
  description: new TranslatableMarkup('A bar chart of nodes per content type.'),
  refreshOnContentChange: TRUE,
)]
class ContentByType extends DashboardCardBase {

  /**
   * Constructs a ContentByType card.
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
    $rows = $this->entityTypeManager->getStorage('node')->getAggregateQuery()
      ->accessCheck(TRUE)
      ->groupBy('type')
      ->aggregate('nid', 'COUNT')
      ->execute();

    $cache = [
      'tags' => ['node_list', 'config:node_type_list'],
      'contexts' => ['user.node_grants:view', 'user.permissions'],
    ];

    $counts = array_column($rows, 'nid_count', 'type');
    if (!$counts) {
      return $this->buildEmpty($this->t('No content yet.')) + ['#cache' => $cache];
    }
    arsort($counts);
    $max = max($counts);
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple(array_keys($counts));

    $bars = [];
    foreach ($counts as $type => $count) {
      $bars[$type] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['htmxdemo-bars__row'],
          // The bar width is a custom property, so the CSS owns the drawing.
          'style' => '--share: ' . round($count / $max * 100) . '%',
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => isset($types[$type]) ? $types[$type]->label() : $type,
          '#attributes' => ['class' => ['htmxdemo-bars__label']],
        ],
        'bar' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => '',
          '#attributes' => ['class' => ['htmxdemo-bars__bar'], 'aria-hidden' => 'true'],
        ],
        'count' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => (string) $count,
          '#attributes' => ['class' => ['htmxdemo-bars__count']],
        ],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-bars']],
      'bars' => $bars,
      '#cache' => $cache,
    ];
  }

}
