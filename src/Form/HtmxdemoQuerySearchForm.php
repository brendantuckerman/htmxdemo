<?php

namespace Drupal\htmxdemo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Override;

// A demo using either GET or QUERY
class HtmxdemoQuerySearchForm extends FormBase {


    #[Override]
    public function getFormId()
    {
      return 'htmxdemo_search_form';
    }

    #[Override]
    public function buildForm(array $form, FormStateInterface $form_state)
    {
      $form['search'] = [
        '#type' => 'search',
        '#title' => $this->t('Search'),
      ];

      $form['method'] = [
        '#type' => 'radios',
        '#title' => $this->t('Request method'),
        '#options' => [
          'query' => 'query',
          'get' => 'get',
        ],
        '#default_value' => 'get',
      ];

      return $form;
    }

    #[Override]
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
      throw new \Exception('Not implemented');

    }

}




