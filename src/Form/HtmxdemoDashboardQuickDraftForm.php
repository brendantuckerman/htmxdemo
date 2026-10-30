<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\Url;
use Drupal\filter\FilterFormatRepositoryInterface;
use Drupal\htmxdemo\DashboardCardInterface;

/**
 * A three-step wizard, shown in a dashboard card, that creates a draft.
 *
 * It follows the same htmx pattern as HtmxdemoForm: every button posts the
 * whole form and the response replaces the form in place.
 *
 * @see \Drupal\htmxdemo\Form\HtmxdemoForm
 * @see \Drupal\htmxdemo\Plugin\DashboardCard\QuickDraft
 */
class HtmxdemoDashboardQuickDraftForm extends FormBase {

  use HtmxRequestInfoTrait;

  /**
   * The content type the wizard creates.
   */
  public const string BUNDLE = 'article';

  /**
   * The step shown once the draft has been created.
   */
  protected const int DONE = 4;

  /**
   * Form value keys belonging to each step, for limited validation.
   */
  protected const array STEP_FIELDS = [
    1 => ['title'],
    2 => ['body'],
  ];

  /**
   * Constructs a HtmxdemoDashboardQuickDraftForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\filter\FilterFormatRepositoryInterface $filterFormatRepository
   *   The text format repository.
   */
  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FilterFormatRepositoryInterface $filterFormatRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    // As in HtmxdemoForm, the '_form' ending matters: FormBuilder only keeps
    // the form_build_id in the main swap when the target looks like a form.
    return 'htmxdemo_dashboard_quick_draft_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Url|null $action
   *   (optional) The card body URL the form posts back to.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?Url $action = NULL): array {
    $step = $form_state->get('step') ?? 1;
    $stored = $form_state->get('stashed_values') ?? [];

    $form['#attributes']['class'][] = 'htmxdemo-wizard';
    if ($action) {
      // Without an explicit action the form would post to the URL of the GET
      // request that loaded it, query string and all.
      $form['#action'] = $action->toString();
    }

    if ($this->isHtmxRequest()) {
      // Messages must travel inside the swapped form, as there is no page
      // around a card body response to show them in.
      $form['messages'] = [
        '#type' => 'status_messages',
        '#weight' => -100,
      ];
    }

    $form['progress'] = $this->buildProgress($step);

    match ($step) {
      2 => $this->buildBodyStep($form, $stored),
      3 => $this->buildReviewStep($form, $stored),
      self::DONE => $this->buildDoneStep($form, $form_state),
      default => $this->buildTitleStep($form, $stored),
    };
    $this->buildActions($form, $step);

    // The behaviour lives on the <form> element: any button posts the whole
    // form and the response replaces it. The target is the triggering form,
    // so FormBuilder leaves the new form_build_id in the main swap.
    $id = '#' . str_replace('_', '-', $this->getFormId());
    (new Htmx())
      ->post($action)
      ->target($id)
      ->select($id)
      ->swap('outerHTML')
      ->applyTo($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->get('stashed_values') ?? [];

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => self::BUNDLE,
      'title' => $values['title'],
      'uid' => $this->currentUser()->id(),
      'status' => 0,
      'body' => [
        'value' => $values['body'] ?? '',
        'format' => $this->filterFormatRepository->getDefaultFormat($this->currentUser())->id(),
      ],
    ]);
    $node->save();

    $this->messenger()->addStatus($this->t('Draft %title created.', ['%title' => $node->label()]));
    $form_state->set('nid', $node->id());
    $form_state->set('step', self::DONE);
    $form_state->set('stashed_values', []);
    // Rebuild rather than redirect, so the card shows the result in place.
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the Next buttons: stash values and advance a step.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function nextStep(array &$form, FormStateInterface $form_state): void {
    $stored = $form_state->get('stashed_values') ?? [];
    $form_state->set('stashed_values', array_merge($stored, $form_state->cleanValues()->getValues()));
    $form_state->set('step', ($form_state->get('step') ?? 1) + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the Back buttons: step back without validating.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function previousStep(array &$form, FormStateInterface $form_state): void {
    $form_state->set('step', max(1, ($form_state->get('step') ?? 1) - 1));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit handler for the Start another button: return to the first step.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function restart(array &$form, FormStateInterface $form_state): void {
    $form_state->set('step', 1);
    $form_state->set('nid', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Builds the step indicator.
   *
   * @param int $step
   *   The current step.
   *
   * @return array
   *   A render array.
   */
  protected function buildProgress(int $step): array {
    $steps = [
      1 => $this->t('Title'),
      2 => $this->t('Body'),
      3 => $this->t('Review'),
    ];
    $items = [];
    foreach ($steps as $number => $label) {
      $items[] = [
        '#markup' => $label,
        '#wrapper_attributes' => [
          'class' => [
            'htmxdemo-steps__step',
            $number < $step ? 'is-done' : ($number === $step ? 'is-current' : 'is-todo'),
          ],
          'aria-current' => $number === $step ? 'step' : NULL,
        ],
      ];
    }
    return [
      '#theme' => 'item_list',
      '#list_type' => 'ol',
      '#items' => $items,
      '#attributes' => ['class' => ['htmxdemo-steps']],
      '#weight' => -50,
    ];
  }

  /**
   * Step 1: the title.
   *
   * @param array $form
   *   The form being built.
   * @param array $stored
   *   Values stashed by earlier steps.
   */
  protected function buildTitleStep(array &$form, array $stored): void {
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $stored['title'] ?? '',
      '#attributes' => ['autofocus' => 'autofocus'],
    ];
  }

  /**
   * Step 2: the body text.
   *
   * @param array $form
   *   The form being built.
   * @param array $stored
   *   Values stashed by earlier steps.
   */
  protected function buildBodyStep(array &$form, array $stored): void {
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#rows' => 5,
      '#default_value' => $stored['body'] ?? '',
    ];
  }

  /**
   * Step 3: a read-only review of the draft.
   *
   * @param array $form
   *   The form being built.
   * @param array $stored
   *   Values stashed by earlier steps.
   */
  protected function buildReviewStep(array &$form, array $stored): void {
    $form['review'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['htmxdemo-review']],
      // User input is shown as plain text, exactly as it will be saved.
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h3',
        'text' => ['#plain_text' => $stored['title'] ?? ''],
      ],
      'body' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        'text' => ['#plain_text' => ($stored['body'] ?? '') !== '' ? $stored['body'] : $this->t('(No body text.)')],
      ],
      'note' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('The article will be saved unpublished.'),
        '#attributes' => ['class' => ['htmxdemo-review__note']],
      ],
    ];
  }

  /**
   * The final step: confirm the draft was created.
   *
   * @param array $form
   *   The form being built.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildDoneStep(array &$form, FormStateInterface $form_state): void {
    $node = $this->entityTypeManager->getStorage('node')->load($form_state->get('nid'));
    if ($node) {
      $url = $node->access('update') ? $node->toUrl('edit-form') : $node->toUrl();
      $form['done'] = [
        '#type' => 'link',
        '#title' => $this->t('Open “@title”', ['@title' => $node->label()]),
        '#url' => $url,
        '#attributes' => ['class' => ['htmxdemo-wizard__result']],
      ];
    }

    // Tell the rest of the dashboard that content changed. Cards with
    // refreshOnContentChange listen for this on the body and reload.
    (new Htmx())
      ->triggerHeader([DashboardCardInterface::CONTENT_CHANGED_EVENT => ['target' => 'body']])
      ->applyTo($form);
  }

  /**
   * Adds the buttons appropriate to the current step.
   *
   * @param array $form
   *   The form being built.
   * @param int $step
   *   The current step.
   */
  protected function buildActions(array &$form, int $step): void {
    $form['actions'] = ['#type' => 'actions'];

    if ($step === self::DONE) {
      $form['actions']['restart'] = [
        '#type' => 'submit',
        '#value' => $this->t('Start another'),
        '#submit' => ['::restart'],
        '#limit_validation_errors' => [],
      ];
      return;
    }

    if ($step > 1) {
      $form['actions']['back'] = [
        '#type' => 'submit',
        '#value' => $this->t('Back'),
        '#submit' => ['::previousStep'],
        // Going backwards skips validation and discards current input.
        '#limit_validation_errors' => [],
        '#attributes' => ['class' => ['htmxdemo-button--ghost']],
      ];
    }

    if (isset(self::STEP_FIELDS[$step])) {
      $form['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#submit' => ['::nextStep'],
        '#limit_validation_errors' => array_map(
          static fn (string $field): array => [$field],
          self::STEP_FIELDS[$step],
        ),
      ];
    }
    else {
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create draft'),
        '#button_type' => 'primary',
      ];
    }
  }

}
