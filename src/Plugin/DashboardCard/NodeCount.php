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
 * Shows the number of nodes, filterable by content type.
 *
 * The select triggers on change. A GET from a form control sends its own
 * name and value, so no form or hx-include is needed.
 */
#[DashboardCard(
  id: 'node_count',
  label: new TranslatableMarkup('Content count'),
  description: new TranslatableMarkup('How many nodes the site has, by content type and status.'),
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
    $types = [];
    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $id => $node_type) {
      $types[$id] = $node_type->label();
    }
    asort($types, SORT_NATURAL | SORT_FLAG_CASE);

    // Ignore anything that is not a real content type.
    $type = (string) $this->requestStack->getCurrentRequest()?->query->get('type', '');
    $type = isset($types[$type]) ? $type : '';

    $select_id = self::cardElementId($uuid) . '-type';
    $stat_id = self::cardElementId($uuid) . '-stat';
    $total = $this->count($type);
    $published = $this->count($type, TRUE);

    $build = [
      'filter' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-filter']],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'label',
          '#value' => $this->t('Content type'),
          '#attributes' => ['for' => $select_id, 'class' => ['visually-hidden']],
        ],
        'select' => $this->buildSelect($select_id, $types, $type),
      ],
      'stat' => [
        '#type' => 'container',
        '#attributes' => ['id' => $stat_id, 'class' => ['htmxdemo-stat']],
        'value' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => number_format($total),
          '#attributes' => ['class' => ['htmxdemo-stat__value']],
        ],
        'label' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $type
            ? $this->formatPlural($total, '@type node', '@type nodes', ['@type' => $types[$type]])
            : $this->formatPlural($total, 'node in total', 'nodes in total'),
          '#attributes' => ['class' => ['htmxdemo-stat__label']],
        ],
        'breakdown' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['htmxdemo-stat__pills']],
          'published' => $this->buildPill($this->t('@count published', ['@count' => $published]), 'success'),
          'unpublished' => $this->buildPill($this->t('@count unpublished', ['@count' => $total - $published]), 'muted'),
        ],
      ],
      '#cache' => [
        'tags' => ['node_list', 'config:node_type_list'],
        'contexts' => ['url.query_args:type', 'user.node_grants:view', 'user.permissions'],
      ],
    ];

    // Only the numbers are swapped, so the select keeps its focus.
    (new Htmx())
      ->get($this->bodyUrl($uuid))
      ->trigger('change')
      ->target('#' . $stat_id)
      ->select('#' . $stat_id)
      ->swap('outerHTML')
      ->applyTo($build['filter']['select']);

    return $build;
  }

  /**
   * Builds the content type select.
   *
   * @param string $id
   *   The element ID.
   * @param array<string, string> $types
   *   Content type labels keyed by machine name.
   * @param string $selected
   *   The selected content type, or an empty string for all types.
   *
   * @return array
   *   A render array.
   */
  protected function buildSelect(string $id, array $types, string $selected): array {
    $select = [
      '#type' => 'html_tag',
      '#tag' => 'select',
      '#attributes' => [
        'id' => $id,
        'name' => 'type',
        'class' => ['htmxdemo-input', 'htmxdemo-select'],
      ],
    ];
    foreach (['' => $this->t('All content types')] + $types as $value => $label) {
      $select[$value ?: 'all'] = [
        '#type' => 'html_tag',
        '#tag' => 'option',
        '#value' => $label,
        '#attributes' => ['value' => $value] + ($value === $selected ? ['selected' => 'selected'] : []),
      ];
    }
    return $select;
  }

  /**
   * Counts the nodes the current user can see.
   *
   * @param string $type
   *   (optional) Limit the count to this content type.
   * @param bool|null $published
   *   (optional) Limit the count to this published status.
   *
   * @return int
   *   The number of nodes.
   */
  protected function count(string $type = '', ?bool $published = NULL): int {
    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->count();
    if ($type !== '') {
      $query->condition('type', $type);
    }
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
