<?php

namespace Drupal\htmxdemo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Url;
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
      // On an htmx rebuild the radio value is only in the user input.
      $method = $form_state->getValue('method')
        ?? $form_state->getUserInput()['method']
        ?? 'get';

      // The wrapper is what the radio's htmx response swaps.
      $form['search_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'search-wrapper'],
      ];
      $form['search_wrapper']['search'] = [
        '#type' => 'search',
        '#title' => $this->t('Search'),
      ];

      $form['method'] = [
        '#type' => 'radios',
        '#title' => $this->t('Request method'),
        '#options' => [
          'query' => 'QUERY',
          'get' => 'GET',
        ],
        '#default_value' => 'get',
      ];

      $form['results'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#attributes' => ['id' => 'search-results'],
      ];

      // The search input sends GET or QUERY, depending on the radio.
      $searchUrl = Url::fromRoute('htmxdemo.search_route');
      $search = new Htmx();
      if ($method === 'query') {
        $search->query($searchUrl);
      }
      else {
        $search->get($searchUrl);
      }
      $search
        ->onlyMainContent()
        ->trigger('input changed delay:300ms')
        ->target('#search-results')
        ->swap('innerHTML')
        ->pushUrl(TRUE)
        ->applyTo($form['search_wrapper']['search']);

      // Changing the radio posts the form back, and only the search wrapper
      // is swapped, so the input is rebuilt with the chosen method.
      (new Htmx())
        ->post(Url::fromRoute('htmxdemo.query_search_form'))
        ->onlyMainContent()
        ->trigger('change')
        ->select('#search-wrapper')
        ->target('#search-wrapper')
        ->swap('outerHTML')
        ->applyTo($form['method']);

      return $form;
    }

    #[Override]
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
      // Searching is handled by htmx requests to the search route.
    }

}




