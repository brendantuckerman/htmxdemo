<?php

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Scroll-loaded article cards with boosted page links.
 */
class HtmxdemoBoostController extends ControllerBase {

  /**
   * The number of article cards on each page.
   */
  const ITEMS_PER_PAGE = 6;

  /**
   * Builds one page of article placeholders.
   *
   * Each placeholder loads its article card from the card route when it is
   * scrolled into view. The previous and next links are boosted in the
   * template.
   *
   * @param int $page
   *   The 1-based page number.
   *
   * @return array
   *   The render array.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   When the page number is past the last page.
   */
  public function action(int $page): array {
    $total = (int) $this->articleQuery()->count()->execute();
    $page_count = max(1, (int) ceil($total / self::ITEMS_PER_PAGE));
    if ($page < 1 || $page > $page_count) {
      throw new NotFoundHttpException();
    }

    $nids = $this->articleQuery()
      ->sort('created', 'DESC')
      ->sort('nid', 'DESC')
      ->range(($page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE)
      ->execute();

    $cards = [];
    foreach ($nids as $nid) {
      $cards[$nid] = [
        '#type' => 'html_tag',
        '#tag' => 'article',
        '#attributes' => [
          'class' => ['htmxdemo-boost__card', 'htmxdemo-boost__card--placeholder'],
          'aria-busy' => 'true',
        ],
      ];
      // Fetch the card once the placeholder scrolls into view, then replace
      // the placeholder with it.
      (new Htmx())
        ->get(Url::fromRoute('htmxdemo.boost_card', ['node' => $nid]))
        ->trigger('intersect once')
        ->swap('outerHTML')
        ->applyTo($cards[$nid]);
    }

    return [
      '#theme' => 'htmxdemo_boost',
      '#cards' => $cards,
      '#page' => $page,
      '#page_count' => $page_count,
      '#previous_url' => $page > 1 ? Url::fromRoute('htmxdemo.boost', ['page' => $page - 1]) : NULL,
      '#next_url' => $page < $page_count ? Url::fromRoute('htmxdemo.boost', ['page' => $page + 1]) : NULL,
      '#attached' => [
        'library' => ['htmxdemo/boost'],
      ],
      '#cache' => [
        'tags' => ['node_list:article'],
        'contexts' => ['user.node_grants:view'],
      ],
    ];
  }

  /**
   * Builds a single article card, swapped in over its placeholder.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The article node.
   *
   * @return array
   *   The render array.
   */
  public function card(NodeInterface $node): array {
    $build = [
      '#theme' => 'htmxdemo_boost_card',
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
    CacheableMetadata::createFromObject($node)->applyTo($build);
    return $build;
  }

  /**
   * Gets a query for published articles the current user can view.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function articleQuery() {
    return $this->entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', NodeInterface::PUBLISHED);
  }

}
