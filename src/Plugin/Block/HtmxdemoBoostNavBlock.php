<?php

namespace Drupal\htmxdemo\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A boosted list of links to ordinary pages.
 *
 * The block is placed on the boost demo page and on node pages, so it is in
 * every page the links lead to. Each boosted click swaps in the whole body of
 * the response, block included.
 */
#[Block(
  id: 'htmxdemo_boost_nav',
  admin_label: new TranslatableMarkup('HtmxDemo boosted navigation'),
)]
class HtmxdemoBoostNavBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The number of article links to list.
   */
  const ARTICLE_COUNT = 8;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $current_node = $this->routeMatch->getParameter('node');
    $current_nid = $current_node instanceof NodeInterface ? $current_node->id() : NULL;

    $links = [
      [
        'title' => $this->t('About boost'),
        'url' => Url::fromRoute('htmxdemo.boost'),
        'active' => $this->routeMatch->getRouteName() === 'htmxdemo.boost',
      ],
    ];

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('created', 'DESC')
      ->sort('nid', 'DESC')
      ->range(0, self::ARTICLE_COUNT)
      ->execute();
    foreach ($storage->loadMultiple($nids) as $nid => $node) {
      $links[] = [
        'title' => $node->label(),
        'url' => $node->toUrl(),
        'active' => $nid == $current_nid,
      ];
    }

    return [
      '#theme' => 'htmxdemo_boost_nav',
      '#links' => $links,
      // Node pages have no htmx of their own, so the block brings it, along
      // with the view transition styles.
      '#attached' => [
        'library' => ['htmxdemo/boost'],
      ],
      '#cache' => [
        'contexts' => ['route', 'user.node_grants:view'],
        'tags' => ['node_list:article'],
      ],
    ];
  }

}
