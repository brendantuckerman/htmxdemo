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
 * Shows the newest published article, with buttons to step through older ones.
 *
 * The buttons send the offset with hx-vals rather than building it into the
 * URL, so every button shares the plain card body URL.
 */
#[DashboardCard(
  id: 'latest_node',
  label: new TranslatableMarkup('Latest articles'),
  description: new TranslatableMarkup('Step through published articles, newest first.'),
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
    $storage = $this->entityTypeManager->getStorage('node');
    $query = fn () => $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1);

    $cache = [
      'tags' => ['node_list'],
      'contexts' => ['url.query_args:offset', 'user.node_grants:view', 'user.permissions'],
    ];

    $total = (int) $query()->count()->execute();
    if ($total === 0) {
      return $this->buildEmpty($this->t('No articles found. Create some article content to see this card.')) + ['#cache' => $cache];
    }

    // Clamp the offset, so a stale button cannot step past either end.
    $offset = (int) $this->requestStack->getCurrentRequest()?->query->get('offset', 0);
    $offset = min(max(0, $offset), $total - 1);

    $nids = $query()
      ->sort('created', 'DESC')
      ->sort('nid', 'DESC')
      ->range($offset, 1)
      ->execute();
    $node = $storage->load(reset($nids));

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-pager-card']],
      'teaser' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-teaser']],
        'node' => $this->entityTypeManager->getViewBuilder('node')->view($node, 'teaser'),
      ],
      'nav' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['htmxdemo-pager']],
        'newer' => $this->buildStepButton($uuid, $this->t('‹ Newer'), $offset - 1, $offset === 0),
        'position' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t('@position of @total', ['@position' => $offset + 1, '@total' => $total]),
          '#attributes' => ['class' => ['htmxdemo-pager__position']],
        ],
        'older' => $this->buildStepButton($uuid, $this->t('Older ›'), $offset + 1, $offset >= $total - 1),
      ],
      '#cache' => $cache,
    ];
  }

  /**
   * Builds a Newer or Older button.
   *
   * @param string $uuid
   *   The card instance UUID.
   * @param \Stringable $label
   *   The button label.
   * @param int $offset
   *   The offset the button loads.
   * @param bool $disabled
   *   Whether the button is disabled, at either end of the list.
   *
   * @return array
   *   A render array.
   */
  protected function buildStepButton(string $uuid, \Stringable $label, int $offset, bool $disabled): array {
    $button = [
      '#type' => 'html_tag',
      '#tag' => 'button',
      '#value' => $label,
      '#attributes' => [
        'type' => 'button',
        'class' => ['htmxdemo-button', 'htmxdemo-button--ghost'],
      ],
    ];
    if ($disabled) {
      $button['#attributes']['disabled'] = 'disabled';
      return $button;
    }

    // The teaser and the pager both change, so the whole body is replaced.
    (new Htmx())
      ->get($this->bodyUrl($uuid))
      ->vals(['offset' => $offset])
      ->target('#' . self::bodyElementId($uuid))
      ->swap('innerHTML')
      ->applyTo($button);

    return $button;
  }

}
