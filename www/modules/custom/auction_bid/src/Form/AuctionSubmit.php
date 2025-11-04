<?php

namespace Drupal\auction_bid\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;

/**
 * AJAX bid form for auction lots.
 */
class AuctionSubmit extends FormBase {

  /** @var int|string */
  public $lot_id;

  /** @var string */
  public $lot_title;

  /** @var int|string */
  public $auction_id;

  /** @var float|int|string */
  public $starting_bid;

  /** @var float|int|string|null */
  public $highest_bid;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'auction_submit_' . $this->lot_id;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) : array {
    $form['#prefix'] = '<div id="auction-submit-' . $this->lot_id . '">';
    $form['#suffix'] = '</div>';

    $form['message'] = [
      '#type' => 'markup',
      '#markup' => '<div class="result_message" id="result_message_' . $this->lot_id . '"></div>',
    ];

    // Refresh-knop met Material Icon "autorenew".
    $form['actions_gethigestbid'] = [
      '#type' => 'submit',
      '#value' => 'autorenew',
      '#attributes' => [
        'class' => ['button-refresh', 'material-icons'],
        'title' => $this->t('Vernieuwen'),
        'aria-label' => $this->t('Vernieuwen'),
        'data-lotid' => [$this->lot_id],
      ],
      '#ajax' => [
        'wrapper' => 'form-wrap',
        'progress' => ['type' => 'none', 'message' => NULL],
        'callback' => '::getHighestBid',
        'disable-refocus' => TRUE,
      ],
    ];

    $current_highest = $this->getHighestOffer();
    $step = $this->getStep();
    $defaultBid = ($this->highest_bid === '' || $this->highest_bid === NULL)
      ? (float) $this->starting_bid
      : ((float) $this->highest_bid + $step);

    $form['bod'] = [
      '#type' => 'textfield',
      '#placeholder' => $this->t('min. @amount', ['@amount' => $defaultBid]),
      '#size' => 20,
      '#required' => TRUE,
      '#attributes' => [
        'inputmode' => 'decimal',
        'pattern' => '[0-9]*[.,]?[0-9]*',
        'autocomplete' => 'off',
      ],
    ];

    $form['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Bid'),
      '#attributes' => [
        'class' => ['button-bid'],
      ],
      '#ajax' => [
        'wrapper' => 'result_message_' . $this->lot_id,
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Sending your bid ...'),
        ],
        'callback' => '::setBid',
        'disable-refocus' => TRUE,
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback to submit a bid.
   */
  public function setBid(array $form, FormStateInterface $form_state) : AjaxResponse {
    $current_bid_raw = $form_state->getValue('bod');
    $current_bid = is_string($current_bid_raw) ? (float) str_replace(',', '.', $current_bid_raw) : (float) $current_bid_raw;
    $response = new AjaxResponse();

    // Quick validation: numeric?
    if (!is_numeric((string) $current_bid_raw)) {
      $ajax_message = "<div class='bid bid--error'>" . $this->t('Geef een geldig bod in.') . "</div>";
      return $response->addCommand(new HtmlCommand('#auction-submit-'. $this->lot_id .' .result_message', $ajax_message));
    }

    $starting = (float) $this->starting_bid;
    if ($current_bid < $starting) {
      $ajax_message = "<div class='bid bid--error'>" . $this->t('Uw bod is te laag.<br />Openingsbod is minimaal € @starting .', ['@starting' => $starting]) . "</div>";
      return $response->addCommand(new HtmlCommand('#auction-submit-'. $this->lot_id .' .result_message', $ajax_message));
    }

    $highest_bid = (float) $this->getHighestOffer();
    if ($current_bid >= $starting && $current_bid > $highest_bid) {
      $step = $this->getStep();
      if (($current_bid - $highest_bid) < $step) {
        $minimum_bid = $highest_bid + $step;
        $ajax_message = "<div class='bid bid--error'>" . $this->t('U dient hoger te bieden dan € @step.', ['@step' => $minimum_bid]) . "</div>";
        return $response->addCommand(new HtmlCommand('#auction-submit-'. $this->lot_id .' .result_message', $ajax_message));
      }

      // Alles ok → submit webform "offer".
      $webform_id = 'offer';
      $webform = Webform::load($webform_id);
      if ($webform) {
        $values = [
          'webform_id' => $webform->id(),
          'data' => [
            'bod' => $current_bid,
            'lot_select' => $this->lot_id,
            'lot_title' => $this->lot_title,
            'auction_select' => $this->auction_id,
            'message' => 'Added via lot overview',
          ],
        ];
        /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
        $webform_submission = WebformSubmission::create($values);
        $webform_submission->save();
      }

      // Zet hoogste bod en bidder op lot node.
      if ($lot = Node::load($this->lot_id)) {
        $lot->set('field_highest_bid', $current_bid);
        $lot->set('field_highest_bid_user', \Drupal::currentUser()->id());
        $lot->save();
      }

      // Informeer lagere bieders (zou idealiter in Queue).
      $this->noticeLowerBidders($current_bid, $this->lot_title);

      // UI updates.
      $ajax_message = "<div class='bid bid--success'>" . $this->t('U hebt momenteel het hoogste bod met € @highestbid .', ['@highestbid' => $current_bid]) . "</div>";
      $response->addCommand(new HtmlCommand('#c-lot-counter-'. $this->lot_id .' .highest_bid', $current_bid));
      $response->addCommand(new HtmlCommand('#auction-submit-'. $this->lot_id .' .result_message', $ajax_message));
      return $response;
    }

    // Bid is te laag.
    $ajax_message = "<div class='bid bid--error'>" . $this->t('Uw bod is te laag. Biedt hoger dan € @highestbid .', ['@highestbid' => $highest_bid]) . "</div>";
    $response->addCommand(new HtmlCommand('#c-lot-counter-'. $this->lot_id .' .highest_bid', $highest_bid));
    $response->addCommand(new HtmlCommand('#auction-submit-'. $this->lot_id .' .result_message', $ajax_message));
    return $response;
  }

  /**
   * AJAX callback to refresh highest bid display.
   */
  public function getHighestBid(array $form, FormStateInterface $form_state) : AjaxResponse {
    $response = new AjaxResponse();
    $show_bid = $this->getHighestOffer();
    return $response->addCommand(new HtmlCommand('#c-lot-counter-'. $this->lot_id .' .highest_bid', $show_bid));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) : void {
    // No non-AJAX submit used.
  }

  /**
   * Get the highest offer for current lot from Webform submissions.
   */
  public function getHighestOffer() {
    // Zoek alle submissions (sid) voor dit lot.
    $select = \Drupal::database()
      ->select('webform_submission_data', 'wsd')
      ->fields('wsd', ['sid'])
      ->condition('wsd.webform_id', 'offer')
      ->condition('wsd.name', 'lot_select')
      ->condition('wsd.value', $this->lot_id)
      ->execute();

    $sids = $select->fetchAll(\PDO::FETCH_COLUMN);
    if (!$sids) {
      return '0';
    }

    // Haal de bod-waarden op voor gevonden sids.
    $select_max = \Drupal::database()
      ->select('webform_submission_data', 'wsd')
      ->fields('wsd', ['value'])
      ->condition('wsd.sid', $sids, 'IN')
      ->condition('wsd.name', 'bod')
      ->execute();

    $values = $select_max->fetchAll(\PDO::FETCH_COLUMN);
    if (!$values) {
      return '0';
    }

    // Sorteer numeriek aflopend en neem de hoogste.
    rsort($values, SORT_NUMERIC);
    $highest_bid = (float) str_replace(',', '.', (string) $values[0]);

    return $highest_bid;
  }

  /**
   * Notify lower bidders by mail (simple, synchronous).
   * In productie liever via Queue.
   */
  public function noticeLowerBidders($bid, $lot_title) : void {
    // Verzamel alle submissions voor dit lot.
    $select = \Drupal::database()
      ->select('webform_submission_data', 'wsd')
      ->fields('wsd', ['sid'])
      ->orderBy('wsd.sid', 'DESC')
      ->condition('wsd.webform_id', 'offer')
      ->condition('wsd.name', 'lot_select')
      ->condition('wsd.value', $this->lot_id)
      ->execute();

    $sids = $select->fetchAll(\PDO::FETCH_COLUMN);
    if (!$sids) {
      return;
    }

    $lower_bids = [];
    foreach ($sids as $sid) {
      if ($submission = WebformSubmission::load($sid)) {
        $data = $submission->getData();
        if (!empty($data['bod']) && (float) $data['bod'] < (float) $bid) {
          $user_ref = $submission->get('uid')->getValue();
          if (!empty($user_ref[0]['target_id'])) {
            $lower_bids[(int) $user_ref[0]['target_id']] = ['bod' => (float) $data['bod']];
          }
        }
      }
    }

    $current_uid = (int) \Drupal::currentUser()->id();

    // Render een stukje lot-content (teaser) om mee te sturen.
    $node = Node::load($this->lot_id);
    $params = [
      'lot_content' => '',
      'highest_bid' => $bid,
      'bid_title' => $lot_title,
    ];
    if ($node) {
      $view_field = 'field_p_lot_content';
      if (!$node->get($view_field)->isEmpty()) {
        $render = $node->$view_field->view('teaser');
        $params['lot_content'] = \Drupal::service('renderer')->renderRoot($render);
      }
    }

    $mailmanager = \Drupal::service('plugin.manager.mail');

    foreach ($lower_bids as $uid => $_info) {
      if ($uid === $current_uid) {
        continue;
      }
      $user = \Drupal\user\Entity\User::load($uid);
      if (!$user) {
        continue;
      }
      $email = $user->getEmail();
      $lang = $user->getPreferredLangcode() ?: \Drupal::languageManager()->getDefaultLanguage()->getId();
      $mailmanager->mail('auction_bid', 'higher_bid', $email, $lang, $params, NULL, TRUE);
    }
  }

  /**
   * Biedstap logisch bepalen op basis van starting bid (ranges).
   */
  public function getStep() : int {
    $start = (float) $this->starting_bid;

    if ($start >= 4000) {
      return 200;
    }
    if ($start >= 2000) {
      return 100;
    }
    if ($start >= 800) {
      return 50;
    }
    if ($start >= 300) {
      return 20;
    }
    if ($start >= 80) {
      return 10;
    }
    return 5;
  }

}
