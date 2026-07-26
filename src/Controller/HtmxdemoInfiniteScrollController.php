<?php

namespace Drupal\htmxdemo\Controller;

//@see https://github.com/hashbangcode/drupal_htmx_examples/blob/main/src/Controller/HtmxInfiniteScrollController.php

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\Url;
use Override;
use Symfony\Component\HttpFoundation\RequestStack;

class HtmxdemoInfiniteScrollController extends ControllerBase {

  use HtmxRequestInfoTrait;

  public function __construct(protected RequestStack $requestStack)
  {
  }

  /**
   * {@inheritdoc}
   */
  #[Override]
  protected function getRequest()
  {
    return $this->requestStack->getCurrentRequest();
  }

  /**
   * Callback for inifnite load route htmxdemo_infinite_scroll
   *
   * @return array
   *  The render array
   *
   */
  public function action() {
    $output = [];

    // We can use this helper from Htmxtrait to dtermine whether
    // requrest is htmx. If not, we might like to handle it normally.
    if ($this->isHtmxRequest()) {
      $page = $this->getRequest()->query->get('page');
      $startCount = $page * 10 + 1;
      $nextPage = $page + 1;
    } else {
      $startCount = 1;
      $nextPage = 1;
    }

    for ($i=$startCount; $i <= $startCount + 9; $i++) {
     if($i == $startCount + 9 ) {
      $output['paragraph_' . $i] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $i,
      ];
      $htmx = new Htmx();
      $htmx->get(Url::fromRoute('htmxdemo.infinite_scroll', options: [
        'query' => [
            'page' => $nextPage,
            '_wrapper_format' => 'drupal_htmx',
          ],
      ]))
        ->swap('afterend')
        ->trigger('revealed once');

        $htmx->applyTo($output['paragraph_' . $i]);

     } else {
      $output['paragraph_' . $i] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $i,
      ];
     }
    }

    // Set up cache
    $output['#cache'] = [
      'contexts' => [
        'url:path',
        'url:path.query',
      ],
    ];

    return $output;
  }
}
