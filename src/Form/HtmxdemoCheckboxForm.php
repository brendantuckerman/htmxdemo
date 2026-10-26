<?php

declare(strict_types=1);

namespace Drupal\htmxdemo\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Htmx\HtmxRequestInfoTrait;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\MainContent\MainContentRendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;
use Override;

class HtmxdemoCheckboxForm extends FormBase {

  use HtmxRequestInfoTrait;

  public function __construct(
    protected MainContentRendererInterface $htmxRenderer,
  ) {}


  public static function create(ContainerInterface $container): static {
    return new static($container->get('main_content_renderer.htmx'));
  }
   /**
   * {@inheritdoc}
   */
  #[Override]
  public function getFormId()
  {
    return 'htmxdemo_checkbox_form';
  }

   /**
   * {@inheritdoc}
   */
  #[Override]
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $days = [
      'Monday' => $this->t('Mon'),
      'Tuesday' => $this->t('Tues'),
      'Wednesday' => $this->t('Wed'),
      'Thursday' => $this->t('Thurs'),
      'Friday' => $this->t('Fri'),
      'Saturday' => $this->t('Sat'),
      'Sunday' => $this->t('Sun'),
    ];

    $form['day-set'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select days')
    ];

    $form['day-set']['days'] = [
      '#type' => 'checkboxes',
      '#options' => $days,
    ];

    $formUrl = Url::fromRoute('htmxdemo.form_checkboxes');

    $htmxCheckboxes = new Htmx();
    $htmxCheckboxes->post($formUrl)->onlyMainContent();

    foreach (array_keys($form['day-set']['days']['#options']) as $day) {
      $form['day-set']['days'][$day]  = [
          '#executes_submit_callback' => TRUE,
          '#submit' => [[$this, 'submitForm']]
      ];
    }
    $htmxCheckboxes->applyTo($form['day-set']['days']);

    $form['progress'] = [
      '#type' => 'html_tag',
      '#tag' => 'progress',
      '#value' => '0%',
      '#attributes' => ['id' => 'day-progress', 'max' => 100, 'value' => 0],
      // The label sits outside the swapped element, so it is not replaced.
      '#prefix' => '<label for="day-progress">' . $this->t('Progress') . '</label> ',
    ];
    $form['percent'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '0%',
      '#attributes' => ['id' => 'day-percent'],
    ];

    // One hidden slot per day. Each response replaces every slot, revealing
    // the selected days and hiding the rest.
    $form['list'] = [
      '#prefix' => '<ul id="day-list">',
      '#suffix' => '</ul>',
    ];
    foreach ($days as $day => $label) {
      $form['list'][$day] = [
        '#type' => 'html_tag',
        '#tag' => 'li',
        '#value' => $day,
        '#attributes' => ['id' => "day-item-$day", 'hidden' => TRUE],
      ];
    }

    return $form;
  }

    /**
   * {@inheritdoc}
   */
  #[Override]
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    if ($this->isHtmxRequest()) {
      $options = $form['day-set']['days']['#options'];
      $selected = array_filter($form_state->getValue('days'));
      $percent = (int) min(100, round(count($selected) / count($options) * 100));

      $days = [];
      foreach (array_keys($options) as $day) {
        $days[] = [
          'value' => $day,
          'label' => $day,
          'selected' => isset($selected[$day]),
        ];
      }
      $form_state->setResponse($this->buildHtmxResponse($percent, $days));
    }
  }

  /**
   * Builds an htmx response with partials updating progress and the day list.
   *
   * @param int $percent
   *   The percentage of days selected, from 0 to 100.
   * @param array $days
   *   One entry per day, with 'value', 'label' and 'selected' values.
   */
  protected function buildHtmxResponse(int $percent, array $days): HtmlResponse {
    $content = [
      '#type' => 'inline_template',
      '#template' => <<<'TWIG'
<hx-partial hx-target="#day-progress" hx-swap="outerHTML">
  <progress id="day-progress" max="100" value="{{ percent }}">{{ percent }}%</progress>
</hx-partial>
<hx-partial hx-target="#day-percent" hx-swap="innerHTML">{{ percent }}%</hx-partial>
{% for day in days %}
<hx-partial hx-target="#day-item-{{ day.value }}" hx-swap="outerHTML">
  <li id="day-item-{{ day.value }}"{% if not day.selected %} hidden{% endif %}>{{ day.label }}</li>
</hx-partial>
{% endfor %}
TWIG,
      '#context' => ['percent' => $percent, 'days' => $days],
    ];

    return $this->htmxRenderer->renderResponse($content, $this->getRequest(), $this->getRouteMatch());
  }

}
