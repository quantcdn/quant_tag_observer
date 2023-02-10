<?php

namespace Drupal\quant_tag_observer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for the Quant queuer.
 */
class ConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['quant_tag_observer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quant_tag_observer.configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('quant_tag_observer.settings');
    $settings = ['path_blocklist', 'tag_blocklist'];

    $form['track_admin_routes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Track admin routes'),
      '#description' => $this->t('Determine if you need to track administrative routes in the registry'),
      '#default_value' => $config->get('track_admin_routes'),
    ];

    foreach ($settings as $key) {
      $form["{$key}_fieldset"] = [
        '#type' => 'fieldset',
        '#title' => ucfirst(str_replace('_', ' ', $key)),
      ];

      $items = $config->get($key);
      if (!is_array($items)) {
        $items = explode(PHP_EOL, $items);
      }

      if (is_null($form_state->get("{$key}_count"))) {
        $form_state->set("{$key}_count", count($items) ?: 1);
      }

      $max = $form_state->get("{$key}_count");

      $form["{$key}_fieldset"][$key] = [
        '#prefix' => '<div id="' . str_replace('_', '-', $key) . '-wrapper">',
        '#suffix' => '</div>',
        "#tree" => TRUE,
      ];

      for ($delta = 0; $delta < $max; $delta++) {
        if (empty($form["{$key}_fieldset"][$key][$delta])) {
          $form["{$key}_fieldset"][$key][$delta] = [
            '#type' => 'textfield',
            '#default_value' => isset($items[$delta]) ? $items[$delta] : '',
          ];
        }
      }

      $form["{$key}_fieldset"]['add'] = [
        '#type' => 'submit',
        '#name' => "{$key}_add",
        '#value' => $this->t('Add %key', [
          '%key' => str_replace('_', ' ', $key),
        ]),
        '#submit' => [[$this, 'addMoreSubmit']],
        '#ajax' => [
          'callback' => [$this, 'addMoreCallback'],
          'wrapper' => str_replace('_', '-', $key) . '-wrapper',
          'effect' => 'fade',
        ],
      ];
    }

    $form['path_blocklist_fieldset']['#description'] = $this->t('Provide list of paths and query strings that will be excluded from observation.');

    $form['tag_blocklist_fieldset']['#description'] = $this->t('Manage a list of tags that should be excluded when evaluating down-stream operations.');

    $form['actions']['clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear the registry'),
      '#weight' => 10,
      '#button_type' => 'danger',
      '#ajax' => [
        'callback' => '::submitFormClear',
      ],
    ];

    $registry = \Drupal::service('quant_tag_observer.registry');
    $node = \Drupal\node\Entity\Node::load(1);
    $registry->getRelatedPaths($node);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Let the form rebuild the blacklist textfields.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function addMoreSubmit(array &$form, FormStateInterface $form_state) {
    $key = str_replace('_add', '', $form_state->getTriggeringElement()['#name']);
    $count = $form_state->get("{$key}_count");
    $count++;
    $form_state->set("{$key}_count", $count);
    $form_state->setRebuild();
  }

  /**
   * Adds more textfields to the blacklist fieldset.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state) {
    $key = str_replace('_add', '', $form_state->getTriggeringElement()['#name']);
    return $form["{$key}_fieldset"][$key];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('quant_tag_observer.settings')
      ->set('track_admin_routes', $form_state->getValue('track_admin_routes'))
      ->set('tag_blocklist', $form_state->getValue('tag_blocklist'))
      ->set('path_blocklist', $form_state->getValue('path_blocklist'))
      ->save();

    return parent::submitForm($form, $form_state);
  }

  /**
   * Clear the registry.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitFormClear(array &$form, FormStateInterface $form_state) {
    \Drupal::service('quant_tag_observer.registry')->clear();
    return $form;
  }
}
