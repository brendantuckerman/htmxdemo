<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;

/**
 * Shows the newest published article as a teaser.
 */
#[DashboardCard(
  id: 'latest_node',
  label: new TranslatableMarkup('Latest article'),
  description: new TranslatableMarkup('A summary of the newest published article.'),
  size: 'wide',
  refreshOnContentChange: TRUE,
)]
class LatestNode extends DashboardCardBase {

  /**
   * Constructs a LatestNode card.
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
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();

    $node = $nids ? $storage->load(reset($nids)) : NULL;
    if (!$node) {
      return $this->buildEmpty($this->t('No articles found. Create some article content to see this card.')) + [
        '#cache' => ['tags' => ['node_list']],
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-teaser']],
      'node' => $this->entityTypeManager->getViewBuilder('node')->view($node, 'teaser'),
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['user.node_grants:view', 'user.permissions'],
      ],
    ];
  }

}
