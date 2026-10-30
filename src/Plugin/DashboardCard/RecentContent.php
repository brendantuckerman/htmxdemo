<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lists the most recently updated content, with a Load more button.
 *
 * Load more appends the next page to the list with hx-swap="beforeend". The
 * same response swaps the button out-of-band, pointing it at the following
 * page or removing it when there is nothing left.
 */
#[DashboardCard(
  id: 'recent_content',
  label: new TranslatableMarkup('Recent content'),
  description: new TranslatableMarkup('The most recently updated pages, five at a time.'),
  refreshOnContentChange: TRUE,
)]
class RecentContent extends DashboardCardBase {

  /**
   * The number of items per page.
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
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $uuid): array {
    $page = max(0, (int) $this->requestStack->getCurrentRequest()?->query->get('page', 0));
    $list_id = self::cardElementId($uuid) . '-list';
    $more_id = self::cardElementId($uuid) . '-more';

    $storage = $this->entityTypeManager->getStorage('node');
    // Fetch one extra item to learn whether there is another page.
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->sort('nid', 'DESC')
      ->range($page * self::LIMIT, self::LIMIT + 1)
      ->execute();
    $has_more = count($nids) > self::LIMIT;
    $nids = array_slice($nids, 0, self::LIMIT, TRUE);

    $cache = [
      'tags' => ['node_list'],
      'contexts' => ['url.query_args:page', 'user.node_grants:view', 'user.permissions'],
      // The "time ago" labels go stale, so do not cache them for long.
      'max-age' => 60,
    ];

    if (!$nids && $page === 0) {
      return $this->buildEmpty($this->t('No published content yet.')) + ['#cache' => $cache];
    }

    $items = [];
    foreach ($storage->loadMultiple($nids) as $nid => $node) {
      $items[$nid] = $this->buildItem($node);
    }
    $more = $this->buildMore($uuid, $more_id, $list_id, $has_more ? $page + 1 : NULL);

    if ($page > 0) {
      // A Load more request: the main content is just the new <li> items,
      // which htmx appends to the list. The button travels out-of-band.
      (new Htmx())->swapOob(TRUE)->applyTo($more);
      return ['items' => $items, 'more' => $more, '#cache' => $cache];
    }

    return [
      'list' => [
        '#type' => 'html_tag',
        '#tag' => 'ul',
        '#attributes' => ['id' => $list_id, 'class' => ['htmxdemo-list']],
        'items' => $items,
      ],
      'more' => $more,
      '#cache' => $cache,
    ];
  }

  /**
   * Builds one list item.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to list.
   *
   * @return array
   *   A render array for an <li> element.
   */
  protected function buildItem(NodeInterface $node): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'li',
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

  /**
   * Builds the Load more wrapper, which keeps its ID even when empty.
   *
   * @param string $uuid
   *   The card instance UUID.
   * @param string $more_id
   *   The ID of the wrapper.
   * @param string $list_id
   *   The ID of the list that new items are appended to.
   * @param int|null $next_page
   *   The page the button loads, or NULL when there are no more pages.
   *
   * @return array
   *   A render array.
   */
  protected function buildMore(string $uuid, string $more_id, string $list_id, ?int $next_page): array {
    $more = [
      '#type' => 'container',
      '#attributes' => ['id' => $more_id, 'class' => ['htmxdemo-more']],
    ];
    if ($next_page === NULL) {
      return $more;
    }

    $more['button'] = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $this->t('Load more'),
      '#attributes' => [
        'type' => 'button',
        'class' => ['htmxdemo-button', 'htmxdemo-button--ghost', 'htmxdemo-button--block'],
      ],
    ];
    (new Htmx())
      ->get(Url::fromRoute('htmxdemo.dashboard.card_body', ['uuid' => $uuid], ['query' => ['page' => $next_page]]))
      ->target('#' . $list_id)
      ->swap('beforeend')
      ->applyTo($more['button']);

    return $more;
  }

}
