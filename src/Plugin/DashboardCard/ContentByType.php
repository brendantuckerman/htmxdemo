<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shows a bar chart of nodes per content type, drawn with CSS only.
 *
 * Each bar is a button. Clicking it loads the latest titles of that type
 * into a detail panel below the chart; clicking the open bar closes it. The
 * open state lives on the server, which renders each bar's next action.
 */
#[DashboardCard(
  id: 'content_by_type',
  label: new TranslatableMarkup('Content by type'),
  description: new TranslatableMarkup('A bar chart of nodes per content type. Select a bar for details.'),
  refreshOnContentChange: TRUE,
)]
class ContentByType extends DashboardCardBase {

  /**
   * The number of titles shown in the detail panel.
   */
  protected const int DETAIL_LIMIT = 5;

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
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RequestStack $requestStack,
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
      'contexts' => ['url.query_args:type', 'user.node_grants:view', 'user.permissions'],
    ];

    $counts = array_column($rows, 'nid_count', 'type');
    if (!$counts) {
      return $this->buildEmpty($this->t('No content yet.')) + ['#cache' => $cache];
    }
    arsort($counts);
    $max = max($counts);
    $types = $this->entityTypeManager->getStorage('node_type')->loadMultiple(array_keys($counts));

    $open = (string) $this->requestStack->getCurrentRequest()?->query->get('type', '');
    $open = isset($counts[$open]) ? $open : '';
    $chart_id = self::cardElementId($uuid) . '-chart';
    $detail_id = self::cardElementId($uuid) . '-detail';

    $bars = [];
    foreach ($counts as $type => $count) {
      $label = isset($types[$type]) ? $types[$type]->label() : $type;
      $is_open = $type === $open;
      $bars[$type] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#attributes' => [
          'type' => 'button',
          'class' => ['htmxdemo-bars__row'],
          // The bar width is a custom property, so the CSS owns the drawing.
          'style' => '--share: ' . round($count / $max * 100) . '%',
          'aria-expanded' => $is_open ? 'true' : 'false',
          'aria-controls' => $detail_id,
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $label,
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

      // The open bar's next click closes the panel, so it sends no type.
      // The chart is swapped as well as the panel, so every bar's
      // aria-expanded and next action stay in step with the server.
      (new Htmx())
        ->get($this->bodyUrl($uuid))
        ->vals(['type' => $is_open ? '' : $type])
        ->target('#' . $chart_id)
        ->select('#' . $chart_id)
        ->swap('outerHTML')
        ->indicator('#' . $chart_id)
        ->applyTo($bars[$type]);
    }

    return [
      '#type' => 'container',
      '#attributes' => ['id' => $chart_id, 'class' => ['htmxdemo-bars']],
      'bars' => $bars,
      'detail' => [
        '#type' => 'container',
        '#attributes' => ['id' => $detail_id, 'class' => ['htmxdemo-bars__detail']],
        'content' => $open ? $this->buildDetail($open, isset($types[$open]) ? $types[$open]->label() : $open) : [],
      ],
      '#cache' => $cache,
    ];
  }

  /**
   * Builds the latest titles of one content type.
   *
   * @param string $type
   *   The content type machine name.
   * @param string $label
   *   The content type label.
   *
   * @return array
   *   A render array.
   */
  protected function buildDetail(string $type, string $label): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $type)
      ->sort('changed', 'DESC')
      ->range(0, self::DETAIL_LIMIT)
      ->execute();

    $items = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      $items[] = $node->toLink()->toRenderable();
    }

    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Latest @type', ['@type' => $label]),
      '#items' => $items,
      '#empty' => $this->t('Nothing to show.'),
      '#attributes' => ['class' => ['htmxdemo-list', 'htmxdemo-list--compact']],
    ];
  }

}
