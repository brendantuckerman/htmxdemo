<?php

namespace Drupal\htmxdemo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\Url;
use Override;
use Symfony\Component\HttpFoundation\Response;

/**
 * A four-page wizard form driven by HTMX swaps.
 */
class HtmxdemoForm extends FormBase {

  use HtmxRequestInfoTrait;

  /**
   * Total number of wizard pages.
   */
  protected const TOTAL_PAGES = 4;

  /**
   * Artificial response delay in microseconds, demo only.
   *
   * Local responses are near-instant, so the loading indicators would only
   * flash. Holding the response makes them visible. Set to 0 to disable.
   */
  protected const DEMO_LATENCY_US = 1500000;

  /**
   * Form value keys belonging to each page.
   *
   * Used to limit validation on each Next button to the fields the user
   * can currently see.
   */
  protected const PAGE_FIELDS = [
    1 => ['postcode'],
    2 => ['name', 'email', 'phone', 'address'],
    3 => ['service', 'urgency', 'extras', 'preferred_date', 'weight', 'website'],
    4 => ['details', 'newsletter'],
  ];

  #[Override]
  public function getFormId() {
    // The '_form' ending matters: core's FormBuilder only trusts the main
    // HTMX swap to carry the new form_build_id when the swap target looks
    // like a whole form. Otherwise it moves the build id out-of-band and
    // the swapped-in form loses it, orphaning the form state.
    return 'htmxdemo_wizard_form';
  }

  #[Override]
  public function buildForm(array $form, FormStateInterface $form_state) {
    $page = $form_state->get('page') ?? 1;
    // We are going to stash the values when moving between 'pages'
    $stored = $form_state->get('stashed_values') ?? [];

    $form['#prefix'] = '<div id="htmxdemo-form-wrapper" class="htmxdemo-form-wrapper">';
    $form['#suffix'] = '</div>';
    // This css provides the default transitions
    $form['#attached']['library'][] = 'htmxdemo/htmxdemo';

    if ($this->isHtmxRequest()) {
      // Errors and status messages must travel inside the swapped fragment;
      // block regions are not part of a main-content-only response.
      $form['messages'] = [
        '#type' => 'status_messages',
        '#weight' => -100,
      ];
      // Out-of-band fragment, picked up by hx-select-oob on the buttons and
      // swapped into the indicator block. Placed outside the wrapper so the
      // main hx-select does not include it.
      $form['#suffix'] = '</div><div id="htmxdemo-page-indicator">'
        . $this->t('Step @page of @total', [
          '@page' => $page,
          '@total' => self::TOTAL_PAGES,
        ])
        . '</div>';
    }

    match ($page) {
      2 => $this->buildPageTwo($form, $stored),
      3 => $this->buildPageThree($form, $stored),
      4 => $this->buildPageFour($form, $stored),
      default => $this->buildPageOne($form, $stored),
    };

    if($page === 1) {
      $form['actions'] = ['#type' => 'actions'];
        $form['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next'),
      '#submit' => ['::nextPage'],
      '#limit_validation_errors' => [['postcode']],
      '#attributes' => [
        // A stable id the OOB fragment can target. Do NOT use '#disabled':
        // that also makes Form API ignore the button server-side, so it
        // would never register as the triggering element once enabled.
        'id' => 'htmxdemo-next',
        'disabled' => 'disabled',
      ],
    ];

    } else {
      $this->buildActions($form, $page);
    }

    // Referenced by hx-indicator on the form element: htmx adds the
    // .htmx-request class here while a navigation POST is in flight, and
    // the spinner is revealed purely by CSS.
    $form['actions']['busy'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '',
      '#attributes' => [
        'id' => 'htmxdemo-form-busy',
        'class' => ['htmxdemo-spinner'],
        'aria-hidden' => 'true',
      ],
    ];

    // The wizard behaviour lives on the <form> element itself: any submit
    // button posts the form and the whole form is swapped. Targeting the
    // form (id ending in '-form', target === trigger) tells FormBuilder the
    // form_build_id is inside the main swap, so it is not moved out-of-band.
    (new Htmx())
      ->post()
      ->onlyMainContent()
      ->select('#htmxdemo-wizard-form')
      ->selectOob('#htmxdemo-page-indicator')
      ->target('#htmxdemo-wizard-form')
      // Stop the postcode field's own hx-get from inheriting these.
      ->disinherit('hx-select hx-select-oob')
      ->indicator('#htmxdemo-form-busy')
      ->swap('outerHTML', 'transition:true')
      ->applyTo($form);

    return $form;
  }

  #[Override]
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Combine the values stashed by earlier pages with the final page's.
    $values = array_merge(
      $form_state->get('stashed_values') ?? [],
      $form_state->cleanValues()->getValues(),
    );
    $this->messenger()->addStatus($this->t('Your form was submitted. Thanks, @name.', [
      '@name' => $values['name'] ?? '',
    ]));

    $url = Url::fromRoute('entity.node.canonical', ['node' => 1]);

    if ($this->isHtmxRequest()) {
      // A background (XHR) request cannot navigate the browser; a normal
      // redirect would be followed silently and swallowed by the swap. The
      // HX-Redirect header tells htmx to perform the navigation itself.
      $htmx = new Htmx();
      $htmx->redirectHeader($url);
      $response = new Response();
      $response->headers->add($htmx->getHeaders()->all());
      $form_state->setResponse($response);
    }
    else {
      // Non-JavaScript fallback: an ordinary form redirect.
      $form_state->setRedirect('entity.node.canonical', ['node' => 1]);
    }
  }

  /**
   * Submit handler for the Next buttons: stash values and advance a page.
   */
  public function nextPage(array &$form, FormStateInterface $form_state): void {
    // Demo only: hold the response so the actions spinner can be seen.
    usleep(self::DEMO_LATENCY_US);
    $stored = $form_state->get('stashed_values') ?? [];
    $form_state->set('stashed_values', array_merge($stored, $form_state->cleanValues()->getValues()));
    $form_state->set('page', ($form_state->get('page') ?? 1) + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the Back buttons: step back without validating.
   */
  public function previousPage(array &$form, FormStateInterface $form_state): void {
    // Demo only: hold the response so the actions spinner can be seen.
    usleep(self::DEMO_LATENCY_US);
    $form_state->set('page', max(1, ($form_state->get('page') ?? 1) - 1));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Page 1: postcode check.
   */
  protected function buildPageOne(array &$form, array $stored): void {
    $form['postcode_result'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'postcode-result'],
    ];

    $form['postcode'] = [
      '#type' => 'number',
      '#title' => $this->t('Please enter your postcode'),
      '#required' => TRUE,
      '#default_value' => $stored['postcode'] ?? NULL,
    ];

    (new Htmx())
      ->get(Url::fromRoute('htmxdemo.postcode_lookup'))
      ->onlyMainContent()
      ->trigger('input changed delay:900ms')
      ->sync('this:replace')
      ->target('#postcode-result')
      ->selectOob('#htmxdemo-shipping-block-value')
      ->swap('innerHTML')
      ->indicator('#postcode-result')
      ->applyTo($form['postcode']);

  }

  /**
   * Page 2: contact details.
   */
  protected function buildPageTwo(array &$form, array $stored): void {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $stored['name'] ?? '',
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#default_value' => $stored['email'] ?? '',
    ];

    $form['phone'] = [
      '#type' => 'tel',
      '#title' => $this->t('Phone'),
      '#default_value' => $stored['phone'] ?? '',
    ];

    $form['address'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Your address'),
      '#default_value' => $stored['address'] ?? '',
    ];
  }

  /**
   * Page 3: shipping options.
   */
  protected function buildPageThree(array &$form, array $stored): void {
    $form['service'] = [
      '#type' => 'select',
      '#title' => $this->t('Service'),
      '#attributes' => ['id' => 'htmxdemo-service'],
      '#empty_option' => $this->t('- Select -'),
      '#options' => [
        'standard' => $this->t('Standard'),
        'express' => $this->t('Express'),
        'bulk' => $this->t('Bulk'),
      ],
      '#default_value' => $stored['service'] ?? NULL,
    ];

    $postcode = $stored['postcode'];

    (new Htmx())
      ->get(Url::fromRoute('htmxdemo.shipping_calculator', options: [
        'query' => [
          '_wrapper_format' => 'drupal_htmx'
        ],
      ]))
      ->onlyMainContent()
      ->trigger('click')
      ->vals(['postcode' => $postcode])
      ->target('this')
      ->selectOob('#htmxdemo-shipping-block-value')
      ->swap('none')
      ->applyTo($form['service']);

    $form['urgency'] = [
      '#type' => 'radios',
      '#title' => $this->t('How urgent is this?'),
      '#required' => TRUE,
      '#options' => [
        'whenever' => $this->t('Whenever'),
        'soon' => $this->t('Soon'),
        'yesterday' => $this->t('Yesterday'),
      ],
      '#default_value' => $stored['urgency'] ?? NULL,
    ];

    $form['extras'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Extras'),
      '#options' => [
        'insurance' => $this->t('Insurance'),
        'tracking' => $this->t('Tracking'),
        'giftwrap' => $this->t('Gift wrap'),
      ],
      '#default_value' => $stored['extras'] ?? [],
    ];

    $form['preferred_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Preferred date'),
      '#default_value' => $stored['preferred_date'] ?? '',
    ];

    $form['weight'] = [
      '#type' => 'range',
      '#title' => $this->t('Weight of package (in kg)'),
      '#min' => 0,
      '#max' => 1000,
      '#step' => 50,
      '#default_value' => $stored['weight'] ?? 0,
    ];

    $form['website'] = [
      '#type' => 'url',
      '#title' => $this->t('Website'),
      '#default_value' => $stored['website'] ?? '',
    ];
  }

  /**
   * Page 4: final details and submission.
   */
  protected function buildPageFour(array &$form, array $stored): void {
    $form['details'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Anything we should know?'),
      '#default_value' => $stored['details'] ?? '',
    ];

    $form['newsletter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sign me up for the newsletter'),
      '#default_value' => $stored['newsletter'] ?? 0,
    ];
  }

  /**
   * Adds the Back/Next/Submit buttons appropriate to the current page.
   */
  protected function buildActions(array &$form, int $page): void {
    $form['actions'] = ['#type' => 'actions'];

    if ($page > 1) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#submit' => ['::previousPage'],
        // Going backwards skips validation and discards current input.
        '#limit_validation_errors' => [],
      ];
    }

    if ($page < self::TOTAL_PAGES) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#submit' => ['::nextPage'],
        '#limit_validation_errors' => array_map(
          static fn (string $field): array => [$field],
          self::PAGE_FIELDS[$page],
        ),
      ];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Request a quote'),
      ];
    }
  }

}
