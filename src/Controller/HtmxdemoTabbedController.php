<?php

namespace Drupal\htmxdemo\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

// @see https://github.com/hashbangcode/drupal_htmx_examples/blob/main/src/Controller/TabbedController.php

class HtmxdemoTabbedController extends ControllerBase {

  use HtmxRequestInfoTrait;

  /**
   * The request stack service
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The htmx renderer service
   *
   * @var \Drupal\Core\Render\MainContent\HtmxRenderer
   */
  protected $htmxRenderer;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;


  public static function create(ContainerInterface $container) {
    $instance = new self();
    $instance->requestStack = $container->get('request_stack');
    $instance->htmxRenderer = $container->get('main_content_renderer.htmx');
    $instance->currentRouteMatch = $container->get('current_route_match');
    return $instance;
  }

  protected function getRequest() {
    return $this->requestStack->getCurrentRequest();
  }

  public function action() {
    $output = [];

    if($this->isHtmxRequest()) {
      // Because this is a htmxRequest, we can respond with a
      // full htmx renderer response.

      //First grab the element that cause the firing
      $trigger = $this->getHtmxTriggerName();

      //Map to the node by findung the nth number
      $number = str_replace('page_', '', $trigger);

      $node = $this->loadNthNode($number);
      if (!$node) {
        return $this->htmxRenderer->renderResponse(
          $this->buildEmpty(),
          $this->requestStack->getCurrentRequest(),
          $this->currentRouteMatch
        );
      }

      $viewBuilder = $this->entityTypeManager()->getViewBuilder('node');
      $renderArray = $viewBuilder->view($node, 'teaser');

      //Then, set up the detail div and render it.
      $output['tab_content'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => [
          'id' => 'detail'
        ],
        'children' => $renderArray,
      ];

      $output['#cache'] = [
        'contexts' => [
          'url.path',
          'url.query_args'
        ],
        'tags' => $node->getCacheTags(),
      ];

      return $this->htmxRenderer->renderResponse(
        $output,
        $this->requestStack->getCurrentRequest(),
        $this->currentRouteMatch
      );
    }

    $range = range(1,5);

    $items = [];

    foreach ($range as $item) {
      $id = 'page_' . $item;
      $items[$id] = [
        '#type' => 'html_tag',
        '#tag' => 'a',
        '#value' => $this->t('Page @number', ['@number' => $item]),
        '#attributes' => [
          'name' => $id,
          'href' => '#',
        ],
      ];

      (new Htmx())
        ->get()
        ->swap('outerHTML')
        ->target('#detail')
        ->trigger('click')
        ->applyTo($items[$id]);
    }

    $output['list_of_items'] = [
      '#theme' => 'item_list',
      '#title' => 'Links',
      '#items' => $items,
      '#type' => 'ul',
      '#attributes' => ['class' => ['htmxdemo-tabs']],
      '#attached' => ['library' => ['htmxdemo/tabs']],
    ];

    //Load the 1st node i db
    $node = $this->loadNthNode(1);
    if (!$node) {
      return $output + $this->buildEmpty();
    }

    // Convert the node to a render array for the view mode "teaser".
    $viewBuilder = $this->entityTypeManager()->getViewBuilder('node');
    $renderArray = $viewBuilder->view($node, 'teaser');

    $output['tab_content'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => '',
      '#attributes' => [
        'id' => 'detail',
      ],
      'children' => $renderArray,
    ];

    // Set up the cache for this request.
    $output['#cache'] = [
      'contexts' => [
        'url.path',
        'url.query_args'
      ],
      'tags' => $node->getCacheTags(),
    ];

    return $output;

  }

   /**
   * Load the node in position "nth", ordered by date created descending.
   *
   * @param int $nth
   *   The position of the node to load, ordered by date created descending.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The node, or null if the node failed to load.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadNthNode(int $nth): ?EntityInterface {
    $nodeStorage = $this->entityTypeManager()->getStorage('node');
    $nids = $nodeStorage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'article')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->range($nth - 1, 1)
      ->execute();

    // Then we load the data accordingly.
    return $nids ? $nodeStorage->load(reset($nids)) : NULL;
  }

  /**
   * Builds the render array shown when there is no content to display.
   */
  protected function buildEmpty(): array {
    return [
      'tab_content' => [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this->t('No articles found. Create some article content to see this demo.'),
        '#attributes' => ['id' => 'detail'],
      ],
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args'],
        'tags' => ['node_list'],
      ],
    ];
  }
}
