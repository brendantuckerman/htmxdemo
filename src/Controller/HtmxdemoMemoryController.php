<?php

namespace Drupal\htmxdemo\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Override;


class HtmxdemoMemoryController extends ControllerBase {

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


  #[Override]
  protected function getRequest()
  {
    return $this->requestStack->getCurrentRequest();
  }


  public function action() {

    $output = []; //To be rendered
    $lastChoice = '';

    // css for transitions etc
    $output['#attached']['library'][] = 'htmxdemo/memory';

    if($this->isHtmxRequest()) {
      //The first element that caused the firing.
      $triggerName = $this->getHtmxTriggerName();
      // Check match??

      $triggerValue = substr($triggerName, -1);
      $lastChoiceValue = substr($lastChoice, -1);

        if($triggerValue == $lastChoiceValue) {
          $output['tab_content'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => 'Match!',
          '#attributes' => [
            'id' => 'results'
          ],

        ];
        $lastChoice = $triggerName;
      // This returns a htmxrespons, avoiding the
        return $this->htmxRenderer->renderResponse(
          $output,
          $this->requestStack->getCurrentRequest(),
          $this->currentRouteMatch
        );
      }



      $lastChoiceRecord = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Last choice: '.  $lastChoice,
        '#attributes' => [
          'id' => 'lastChoice',
        ],
      ];

       $output['tab_content'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Fired by: ') . $triggerName,
        '#attributes' => [
          'id' => 'results'
        ],
        'children' => $lastChoiceRecord ?? ''
      ];

      // This returns a htmxrespons, avoiding the
      return $this->htmxRenderer->renderResponse(
        $output,
        $this->requestStack->getCurrentRequest(),
        $this->currentRouteMatch
      );
    }

    $output['tab_content'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Fired by: '),
      '#attributes' => [
        'id' => 'results'
      ],
    ];


    $rows = []; //Board representation
    $columns = []; //Board representation

    $range = range(1,2); //Must stay as 2 (i.e pairs)
    $values = range(1,4); // The values held by the cards

    $items = [];//Represents the cards

    foreach ($range as $item) {
      foreach($values as $value) {
        $id = 'card_' . $item . '_' . $value;
        $items[$id] = [
          '#type' => 'html_tag',
          '#tag' => 'a',
          '#value' => $this->t('@number', ['@number' => $value]),
          '#attributes' => [
            'name' => $id,
            'href' => '#',
            'class' => 'htmxdemo-memory-tiles',
            'data-value' => $value
          ],
        ];

         (new Htmx())
          ->get()
          ->swap('outerHTML')
          ->target('#results')
          ->trigger('click')
          ->applyTo($items[$id]);
      }
    }

    shuffle($items); // Randomise tiles

     $output['list_of_items'] = [
      '#theme' => 'item_list',
      '#title' => 'Links',
      '#items' => $items,
      '#type' => 'ul',
    ];

     // Set up the cache for this request.
    $output['#cache'] = [
      'contexts' => [
        'url:path',
        'url:path.query',
      ]
    ];


    return $output;
  }

}
