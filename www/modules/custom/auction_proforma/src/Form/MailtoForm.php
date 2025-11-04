<?php

namespace Drupal\auction_proforma\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\node\Entity\Node;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * Class GotoForm.
 */
class MailtoForm extends FormBase {

    public $auction_id;
    public $auction_date;
    public $proforma_id;
    public $user_reference_mail;
    public $user_lang;
    public $user_reference_lastname;
    public $user_reference_firstname;

    //$year = date("Y");
    //$auction_reference_id = $node->get('field_veiling')->first()->getValue()['target_id'];
    //$user_reference_id = $node->get('field_bieder')->first()->getValue()['target_id'];
    //$invoice_number = $node->field_invoice_number->value;

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
      return 'mailto_form_' . $this->proforma_id;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Mail PDF'),
        ];

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
      $mailer = \Drupal::service('plugin.manager.mail');
      $module = 'auction_proforma';
      $key = 'mail_proforma';
      $email = $this->user_reference_mail;
      $params['proforma_id'] = $this->proforma_id;
      $params['auction_date'] = $this->auction_date;
      $params['user_lastname'] = $this->user_reference_lastname;
      $params['user_firstname'] = $this->user_reference_firstname;
      $params['user_mail'] = $this->user_reference_mail;
      $lang = $this->user_lang;
      $send = TRUE;

      $result = $mailer->mail($module, $key, $email, $lang, $params, NULL, $send);

    }
}


