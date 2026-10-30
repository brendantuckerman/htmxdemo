<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;

/**
 * Lists the most recently updated content.
 */
#[DashboardCard(
  id: 'recent_content',
  label: new TranslatableMarkup('Recent content'),
  description: new TranslatableMarkup('The five most recently updated pages.'),
  refreshOnContentChange: TRUE,
)]
class RecentContent extends DashboardCardBase {

  /**
   * The number of items to list.
   */
  protected const int LIMIT = 5;

  /**
   * Constructs a RecentContent card.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $uuid): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->range(0, self::LIMIT)
      ->execute();

    $cache = [
      'tags' => ['node_list'],
      'contexts' => ['user.node_grants:view', 'user.permissions'],
      // The "time ago" labels go stale, so do not cache them for long.
      'max-age' => 60,
    ];

    if (!$nids) {
      return $this->buildEmpty($this->t('No published content yet.')) + ['#cache' => $cache];
    }

    $items = [];
    /** @var \Drupal\node\NodeInterface $node */
    foreach ($storage->loadMultiple($nids) as $node) {
      $items[] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-list__item']],
        'title' => $node->toLink()->toRenderable() + [
          '#attributes' => ['class' => ['htmxdemo-list__title']],
        ],
        'meta' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('@type · @ago', [
            '@type' => $node->type->entity?->label() ?? $node->bundle(),
            '@ago' => $this->dateFormatter->formatTimeDiffSince($node->getChangedTime(), ['granularity' => 1]),
          ]),
          '#attributes' => ['class' => ['htmxdemo-list__meta']],
        ],
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['htmxdemo-list']],
      '#cache' => $cache,
    ];
  }

}
