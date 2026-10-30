<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;

/**
 * Shows a random article, with a button to shuffle to another.
 */
#[DashboardCard(
  id: 'random_article',
  label: new TranslatableMarkup('Random article'),
  description: new TranslatableMarkup('A random article, with a shuffle button.'),
)]
class RandomArticle extends DashboardCardBase {

  /**
   * Constructs a RandomArticle card.
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
      ->execute();

    // A different article on every request, so never cache the result.
    $cache = ['max-age' => 0];

    if (!$nids) {
      return $this->buildEmpty($this->t('No articles found. Create some article content to see this card.')) + ['#cache' => $cache];
    }

    $node = $storage->load($nids[array_rand($nids)]);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-random']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        '#attributes' => ['class' => ['htmxdemo-random__title']],
        'link' => $node->toLink()->toRenderable(),
      ],
      'summary' => $node->hasField('body') ? $node->get('body')->view([
        'label' => 'hidden',
        // Works for text_long and text_with_summary bodies alike.
        'type' => 'text_trimmed',
        'settings' => ['trim_length' => 180],
      ]) : [],
      'shuffle' => [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Shuffle'),
        '#attributes' => [
          'type' => 'button',
          'class' => ['htmxdemo-button', 'htmxdemo-button--ghost'],
        ],
      ],
      '#cache' => $cache,
    ];

    // The button reloads this card body, so the response replaces itself.
    (new Htmx())
      ->get($this->bodyUrl($uuid))
      ->target('#' . self::bodyElementId($uuid))
      ->swap('innerHTML')
      ->applyTo($build['shuffle']);

    return $build;
  }

}
