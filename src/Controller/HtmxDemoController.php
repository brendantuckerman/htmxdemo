<?php

namespace Drupal\htmxdemo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\htmxdemo\HtmxdemoShippingCalculatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HtmxDemoController extends ControllerBase {

  // We use this in cases where we might have a page that renders normally AND
  // via Htmx. It allows us to access $this->isHtmxRequest
  use HtmxRequestInfoTrait;

  /**
   * Artificial response delay in microseconds, demo only.
   *
   * Local responses are near-instant, so the loading indicators would only
   * flash. Holding the response makes them visible. Set to 0 to disable.
   */
  protected const DEMO_LATENCY_US = 1500000;
  /**
   *  Shipping calculation service
   * @var \Drupal\htmxdemo\HtmxdemoShippingCalculatorInterface
   */
  protected $shippingCalculator;

  public function __construct(protected RequestStack $requestStack, HtmxdemoShippingCalculatorInterface $shippingCalculator)
  {
    $this->shippingCalculator = $shippingCalculator;
  }

  protected function getRequest() {
    return $this->requestStack->getCurrentRequest();
  }

  public function basic(): array {

    return [
      '#theme' => 'htmxdemo_basic',
    ];
  }

  public function basicResponse() {
    return [ '#theme' => 'htmxdemo_basic_response' ];
  }

  public function drupalBasic() {
    return [
      '#theme' => 'htmxdemo_drupal_basic',
    ];
  }

  public function drupalBasicResponse() {
      $random = random_int(0, 100);

      return $random;
  }

  public function postcodeLookup(Request $request): array {

    // We could use this logic to split a normal controller from a HTMX request
    if(!$this->isHtmxRequest()){
      // Respond 'normally'
      return [];
    }

    // Demo only: hold the response so the postcode spinner can be seen.
    usleep(self::DEMO_LATENCY_US);

    $userPostcode = $request->query->get('postcode', '');

    if($this->shippingCalculator->isPostcodeServiced($userPostcode)) {
      return [
      'postcode_result' => [
        '#theme' => 'htmxdemo_postcode_result',
        '#available' => true,
        '#postcode' => $userPostcode,
        '#cache' => [
          'contexts' => ['url.query_args:postcode'],
        ],
      ],
      'shipping_result' => $this->calculateShipping($this->getRequest()),
      ];
    }

    return  [
      '#theme' => 'htmxdemo_postcode_result',
      '#available' => false,
      '#postcode' => $userPostcode,
      '#cache' => [
        'contexts' => ['url.query_args:postcode'],
      ]


    ];
  }

  public function calculateShipping(Request $request): array {
    return [
      '#theme' => 'htmxdemo_shipping_calculator',
      '#total' => $this->getShippingCost($request),
    ];
  }

  protected function getShippingCost(Request $request): string {
    $userPostcode = $request->query->get('postcode') ?? '';
    $userSelection = $request->query->get('service') ?? '';

    return $this->shippingCalculator->calculateShippingCost($userPostcode, $userSelection);
  }

  public function wizard() {


    return [
        '#theme' => 'htmxdemo_wizard',
    ];
  }
}


