<?php

namespace Drupal\htmxdemo\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\TempStore;


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

  /**
   * Pruvate temp store service
   *
   * @var \Drupal\Core\TempStore;
   */
  protected $privateTempStore;

  public static function create(ContainerInterface $container) {
    $instance = new self();
    $instance->requestStack = $container->get('request_stack');
    $instance->htmxRenderer = $container->get('main_content_renderer.htmx');
    $instance->currentRouteMatch = $container->get('current_route_match');
    $instance->privateTempStore = $container->get('tempstore.private');
    return $instance;
  }


  /**
   * {@inheritdoc}
   */
  protected function getRequest()
  {
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Controller action for the /memory path
   */
  public function action() {

    $output = []; //To be rendered
    $output['#attached']['library'][] = 'htmxdemo/memory';

    $store = $this->privateTempStore->get('htmxdemo'); // collection name
    $lastChoice = $store->get('lastChoice') ?? '';
    $isFirstChoice = $store->get('isFirstChocie') ?? false;

    // Both isFirstChoice true/false will follow this path. Only initial load
    // will not.
    if($this->isHtmxRequest()) {
      //The first element that caused the firing.
      $triggerName = $this->getHtmxTriggerName();
      $store->set('lastChoice', $triggerName);

      // This should be replaced by isFirstChoice branching.
      if (strlen($lastChoice) < 1) {
        // First click of a round: nothing to compare against yet.
        $output['tab_content'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('Fired by: ') . $triggerName,
          '#attributes' => [
            'id' => 'results'
          ],
        ];

        return $this->htmxRenderer->renderResponse(
          $output,
          $this->requestStack->getCurrentRequest(),
          $this->currentRouteMatch
        );
      }

      preg_match('/_([^_]*)$/', $triggerName, $triggerValue);
      preg_match('/_([^_]*)$/', $lastChoice, $lastChoiceValue);

      if($triggerValue[1] === $lastChoiceValue[1]) {
        //Match - put in a helper function to repace the element with
        // a greyed out version of the card.
        $output['tab_content'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Match!',
        '#attributes' => [
          'id' => 'results'
          ],
        ];
        // This returns a htmxrespons, avoiding renderign the entire HTMX object
        return $this->htmxRenderer->renderResponse(
          $output,
          $this->requestStack->getCurrentRequest(),
          $this->currentRouteMatch
        );
      }
      else {

        $lastChoiceRecord = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => 'Last choice: '. $lastChoiceValue[1],
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
    }

    //Not a htmx request, so initial set up:
    $store->delete('lastChoice');

    $output['tab_content'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Fired by: '),
      '#attributes' => [
        'id' => 'results'
      ],
    ];


    // Memory hame actually works in groups of two:
    // initial choice + second choice.

    //Initiate flag for cardsRemaining to track whether game is still active.

    //Initiate flag for isFirstChoice to track whether this is first or second.

    // Record and display initial choice.

    // isFirstChoice = false;

    // User clicks again-  i

    //if a match, then swap those elements out with a blank unclicable card.

    // Update number of matches & check to see whether the game is over  with
    // does matches * 2 = range.

    // else we continue. Either way,  isFirstChoice = true


    // If not match, then return to initial


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
    // $output['#cache'] = [
    //   'contexts' => [
    //     'url:path',
    //     'url:path.query',
    //   ]
    // ];


    return $output;
  }

  public function createBoard() {
    return [0,1,2,3,4,5];
  }

  public function updateBoard() {

  }

}
