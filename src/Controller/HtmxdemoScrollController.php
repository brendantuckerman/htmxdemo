<?php

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * A feed of articles that load and draw in as the page scrolls.
 */
class HtmxdemoScrollController extends ControllerBase {

  /**
   * The number of cards the feed loads at a time.
   */
  const BATCH_SIZE = 1;

  /**
   * The number of batches to load before the feed stops.
   *
   * Random batches never run out, so this keeps the page from growing
   * forever.
   */
  const MAX_BATCHES = 10;

  /**
   * Builds the page with a loader for the first feed batch.
   *
   * @return array
   *   The render array.
   */
  public function page(): array {
    return [
      '#theme' => 'htmxdemo_scroll',
      '#feed' => $this->buildLoader(0),
      '#attached' => [
        'library' => ['htmxdemo/scroll'],
      ],
    ];
  }

  /**
   * Returns a batch of random cards, plus a loader for the next batch.
   *
   * Each batch is a fresh random pick, so an article can appear again in a
   * later batch.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request. The "page" query value is the 0-based batch number.
   *
   * @return array
   *   The render array.
   */
  public function feed(Request $request): array {
    $page = max(0, $request->query->getInt('page'));
    $nids = $this->articleQuery()->execute();
    shuffle($nids);
    $nids = array_slice($nids, 0, self::BATCH_SIZE);

    $build = [];
    foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($nids) as $nid => $node) {
      $build[$nid] = [
        '#theme' => 'htmxdemo_scroll_card',
        '#title' => $node->label(),
        '#url' => $node->toUrl(),
        '#image' => $node->get('field_main_image')->view([
          'label' => 'hidden',
          'type' => 'image',
          'settings' => ['image_style' => 'wide'],
        ]),
        '#summary' => $node->get('body')->view([
          'label' => 'hidden',
          'type' => 'text_trimmed',
          'settings' => ['trim_length' => 300],
        ]),
      ];
      CacheableMetadata::createFromObject($node)->applyTo($build[$nid]);
    }

    if ($nids && $page + 1 < self::MAX_BATCHES) {
      $build['next'] = $this->buildLoader($page + 1);
    }

    // Every response is a new random pick, so never cache it. The route's
    // no_cache option keeps it out of the anonymous page cache, which ignores
    // max-age.
    $build['#cache'] = ['max-age' => 0];
    return $build;
  }

  /**
   * Builds a placeholder that loads a batch when scrolled into view.
   *
   * The response replaces the placeholder, and ends with the placeholder for
   * the batch after it, so the feed keeps loading until the articles run out.
   *
   * @param int $page
   *   The 0-based batch number to load.
   *
   * @return array
   *   The render array.
   */
  protected function buildLoader(int $page): array {
    $loader = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'class' => ['htmxdemo-scroll__loader'],
        'aria-busy' => 'true',
      ],
    ];
    // The negative bottom root margin shrinks the observed area by a third of
    // the viewport, so the batch loads once the loader is a third of the way
    // up the screen rather than as soon as its top edge appears. The cards
    // then slide in where the user is looking.
    (new Htmx())
      ->get(Url::fromRoute('htmxdemo.scroll_feed', [], ['query' => ['page' => $page]]))
      ->trigger("intersect once rootMargin:'0px 0px -20% 0px'")
      ->swap('outerHTML')
      ->applyTo($loader);
    return $loader;
  }

  /**
   * Gets a query for published articles the current user can view.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function articleQuery(): QueryInterface {
    return $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', NodeInterface::PUBLISHED);
  }

}
