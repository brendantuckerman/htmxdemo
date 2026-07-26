<?php

namespace Drupal\htmxdemo\Form;

// @see https://github.com/hashbangcode/drupal_htmx_examples/blob/main/src/Form/AddAnotherForm.php

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Override;

/**
 * Form that uses htmx for and 'add more' feature.
 */
class HtmxdemoAddMoreForm extends FormBase {

  use HtmxRequestInfoTrait;

  /**
   * (@inheritdoc)
   */
  public function getFormId() {
    // The '_form' ending matters: core's FormBuilder only trusts the main
    // HTMX swap to carry the new form_build_id when the swap target looks
    // like a whole form. Otherwise it moves the build id out-of-band and
    // the swapped-in form loses it, orphaning the form state.
    return 'htmxdemo_add_more_form';
  }

  /**
   * {@inheritdoc}
   */
  #[Override]
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $userInput = $form_state->getUserInput();

    $count = (int) ($userInput['text_count'] ?? 1);

    if($this->isHtmxRequest()) {
      $count++;
    }

    $form['#prefix'] = '<div id="htmxdemo-add-more-form-wrapper" class="htmxdemo-form-wrapper">';
    $form['#suffix'] = '</div>';


    $form['text-wrapper'] = [
      '#type' => 'container',
      '#id' => 'add-more-area'
    ];

    for ($i=1; $i <= $count ; $i++ ) {
        $form['text-wrapper']['add_more_content_' . $i ] = [
        '#type' => 'textfield',
        '#title' => $this->t('Text %i', ['%i' => $i]),
        '#default_value' => isset($userInput['text_' . $i]) ? $userInput['text_' . $i] : NULL,
        '#wrapper_attributes' => [
          'id' => 'wrapper-text_' . $i,
        ],
      ];
    }

    $form['text_count'] = [
      '#type' => 'hidden',
      '#value' => $count,
      '#attributes' => [
        'id' => [
          'text-count'
        ],
      ],
    ];

    //Remove this from payload
    $htmx = new Htmx();
    $htmx->swapOob('true')
    ->applyTo($form['text_count']);

    $form['add'] = [
      '#type' => 'submit',
      '#id' => 'htmxdemo-add-another',
      '#value' => $this->t('Add another line')
    ];

    (new Htmx())
    ->post()
    ->target('#add-more-area')
    ->select('#wrapper-text_' . $count + 1)
    ->swapOob(TRUE)
    ->swap('beforeend')
    ->applyTo($form['add']);


    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit form')
    ];



    return $form;
  }

  /**
   * {@inheritdoc}
   */
  #[Override]
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    if($this->isHtmxRequest()) {
      return;
    }
  }


}
