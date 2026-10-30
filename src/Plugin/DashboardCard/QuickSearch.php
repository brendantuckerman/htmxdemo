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
 * Searches published content by title as the user types.
 */
#[DashboardCard(
  id: 'quick_search',
  label: new TranslatableMarkup('Quick search'),
  description: new TranslatableMarkup('Search content titles as you type.'),
)]
class QuickSearch extends DashboardCardBase {

  /**
   * The shortest search term that runs a query.
   */
  protected const int MIN_LENGTH = 2;

  /**
   * The number of results to list.
   */
  protected const int LIMIT = 8;

  /**
   * Constructs a QuickSearch card.
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
    $term = trim((string) $this->requestStack->getCurrentRequest()?->query->get('q', ''));
    $input_id = self::cardElementId($uuid) . '-q';
    $results_id = self::cardElementId($uuid) . '-results';

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-search']],
      'label' => [
        '#type' => 'html_tag',
        '#tag' => 'label',
        '#value' => $this->t('Search titles'),
        '#attributes' => ['for' => $input_id, 'class' => ['visually-hidden']],
      ],
      'input' => [
        '#type' => 'html_tag',
        '#tag' => 'input',
        '#attributes' => [
          'type' => 'search',
          'name' => 'q',
          'id' => $input_id,
          'value' => $term,
          'placeholder' => $this->t('Search titles…'),
          'autocomplete' => 'off',
          'class' => ['htmxdemo-input'],
        ],
      ],
      'results' => [
        '#type' => 'container',
        '#attributes' => ['id' => $results_id, 'class' => ['htmxdemo-search__results'], 'aria-live' => 'polite'],
        'list' => $this->buildResults($term),
      ],
      '#cache' => [
        'tags' => ['node_list'],
        'contexts' => ['url.query_args:q', 'user.node_grants:view', 'user.permissions'],
      ],
    ];

    // The input requests the whole card body but hx-select keeps only the
    // results, so the input keeps its focus and caret while the user types.
    (new Htmx())
      ->get($this->bodyUrl($uuid))
      ->trigger('input changed delay:300ms, search')
      ->sync('this:replace')
      ->target('#' . $results_id)
      ->select('#' . $results_id)
      ->swap('outerHTML')
      ->applyTo($build['input']);

    return $build;
  }

  /**
   * Builds the list of matching content.
   *
   * @param string $term
   *   The search term.
   *
   * @return array
   *   A render array.
   */
  protected function buildResults(string $term): array {
    if (mb_strlen($term) < self::MIN_LENGTH) {
      return $this->buildEmpty($this->t('Type at least @count characters.', ['@count' => self::MIN_LENGTH]));
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('title', $term, 'CONTAINS')
      ->sort('title')
      ->range(0, self::LIMIT)
      ->execute();

    if (!$nids) {
      return $this->buildEmpty($this->t('No titles match “@term”.', ['@term' => $term]));
    }

    $items = [];
    foreach ($storage->loadMultiple($nids) as $node) {
      $items[] = $node->toLink()->toRenderable();
    }
    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => ['class' => ['htmxdemo-list', 'htmxdemo-list--compact']],
    ];
  }

}
