<?php

namespace Drupal\module_auction_view\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class GotoForm.
 */
class GotoForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'goto_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['auction_id'] = [
      '#type' => 'hidden',
      '#required' => TRUE,
    ];

    $form['porforma_id'] = [
      '#type' => 'hidden',
      '#required' => TRUE,
    ];

    $form['goto'] = [
      '#title' => t('Ga naar lot'),
      '#type' => 'number',
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Ga naar lot'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (!empty($values['goto']) && !empty($values['auction_id'])) {
      $query = \Drupal::entityQuery('node')
        ->condition('status', 1)
        ->condition('type', 'lot')
        ->condition('field_lot_nr.value', $values['goto'])
        ->condition('field_auction_reference.target_id', $values['auction_id']);
      $nids = $query->execute();
      if (!empty($nids)) {
        $nid = array_values($nids);
        $url = \Drupal\Core\Url::fromRoute('entity.node.canonical', ['node' => $nid[0]]);
        //$url = $url->toString();
        //return new RedirectResponse($url->toString());
        $form_state->setRedirectUrl($url);
      }
    }
  }

}
