<?php

namespace Drupal\htmxdemo;


/**
 * Provides the interface for shipping calculations
 */
interface HtmxdemoShippingCalculatorInterface {

  /**
   * Returns whether postcode is serviced
   *
   * @return bool
   *
   */
  public function isPostcodeServiced(string $userPostcode);


  /**
   * Returns costs calculated with postcode and service type
   *
   * @param string $postcode. String of user postcode
   *
   * @param string $service. Delivery type option.
   *
   * @return string cost in form $0.00;
   *
   */
  public function calculateShippingCost(string $postcode = "0000", string $service = "standard" );


}
