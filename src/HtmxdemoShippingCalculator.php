<?php

namespace Drupal\htmxdemo;

use Override;

class HtmxdemoShippingCalculator implements HtmxdemoShippingCalculatorInterface {

  /**
   * {@inheritdoc}
   */
  #[Override]
  public function isPostcodeServiced(string $userPostcode): bool
  {
    $servicedPostcodes = ['3000', '2000', '1000'];
    if(in_array($userPostcode, $servicedPostcodes)) {
      return true;
    }

    return false;

  }

  public function calculateShippingCost(string $postcode = "0000", string $service = "standard" ): string {
    $currentCost = 0.00;

    // Use information to claculate costs.
    $postCodeCost = $this->calculatePostcodeCost($postcode);
    $serviceCost = $this->calculateServiceCost($service);

    if($postCodeCost > 0.00) {
      $currentCost += $postCodeCost;

      if( $serviceCost > 0.00) {
        $currentCost += $serviceCost;
      }
    }

    return "$" . strval($currentCost);
  }

  private function calculatePostCodeCost(string $postcode): float {
    switch ($postcode) {
      case '2000':
        return 9.50;
      case '3000':
        return 4.75;
      case '1000':
        return 100.00;
      default:
        return 0.00;
    }
  }

  private function calculateServiceCost(string $service): float {
    switch ($service) {
      case 'standard':
        return 2.50;
      case 'express':
        return 10.75;
      case 'bulk':
        return 23.20;
      default:
        return 0.00;
    }
  }
}


