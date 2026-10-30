<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Plugin\DashboardCard;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\htmxdemo\Attribute\DashboardCard;
use Drupal\htmxdemo\DashboardCardBase;
use Drupal\htmxdemo\Form\HtmxdemoDashboardQuickDraftForm;

/**
 * A three-step wizard that creates an unpublished article.
 *
 * Only one instance is allowed: a form ID must be unique on a page, and the
 * wizard targets its own form element by ID.
 */
#[DashboardCard(
  id: 'quick_draft',
  label: new TranslatableMarkup('Quick draft'),
  description: new TranslatableMarkup('Create an unpublished article in three steps.'),
  size: 'wide',
  multiple: FALSE,
)]
class QuickDraft extends DashboardCardBase {

  /**
   * Constructs a QuickDraft card.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    return parent::access($account)->andIf(
      $this->entityTypeManager->getAccessControlHandler('node')
        ->createAccess(HtmxdemoDashboardQuickDraftForm::BUNDLE, $account, [], TRUE)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(string $uuid): array {
    // On a POST back to the card body route, getForm() also processes the
    // submission before returning the rebuilt form.
    return $this->formBuilder->getForm(HtmxdemoDashboardQuickDraftForm::class, $this->bodyUrl($uuid));
  }

}
