<?php

namespace Drupal\htmxdemo\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[Block(
  id: 'htmxdemo-search-block',
  admin_label: new TranslatableMarkup('HtmxDemo Search Block')
)]
class HtmxDemoSearchBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build()  {
    $form = \Drupal::formBuilder()->getForm('\Drupal\htmxdemo\Form\HtmxdemoQuerySearchForm');
    return $form;
  }
}
