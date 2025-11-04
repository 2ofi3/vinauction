<?php
namespace Drupal\module_auction_view\Plugin\WebformHandler;

use Drupal\webform\Plugin\WebformHandler\EmailWebformHandler;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\node\Entity\Node;

/**
 * Emails a webform submission.
 *
 * @WebformHandler(
 *   id = "bidding_webform_handler",
 *   label = @Translation("Bidding webform handler"),
 *   category = @Translation("Bidding"),
 *   description = @Translation("Sends bidding webform in different language"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class BiddingWebformhandler extends EmailWebformHandler {

  public function sendMessage(WebformSubmissionInterface $webform_submission, array $message) {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $lot_title = $webform_submission->getElementData('lot_title');
    $nid = $webform_submission->getElementData('lot_select');
    $node = Node::load($nid);

    // Content lot rendering.
    $view_field = 'field_p_lot_content';
    $lot_content = $node->$view_field->view('teaser');
    $lot_content = \Drupal::service('renderer')->renderRoot($lot_content);

    // Offer.
    $offer = $webform_submission->getElementData('bod');
    
    switch($language) {

      case 'nl':
        $subject = "Uw bod op {$lot_title}";
        $body = "Geachte,<br/><br/>
          Dank voor uw bod! U bent momenteel de hoogst biedende ({$lot_title}).<br/>
          De koperscommissie bedraagt 18% zonder bijkomende kosten.<br/><br/>
          {$lot_content}
          <br/><br/>
          <strong>Uw bod: </strong>&euro; {$offer}<br/><br/>
          Voor bijkomende gewenste info, steeds welkom.<br/><br/>
          Vriendelijke groeten,<br/><br/>
          Vin.auction";
      break;

      case 'fr':
        $subject = "Votre offre sur {$lot_title}";
        $body = "Bonjour,<br/><br/>
          Merci pour votre offre sur {$lot_title}. Pour le moment votre offre est le plus haut.<br/>
          La commission d’achat est 18% sans frais supplémentaires<br/><br/>
          {$lot_content}
          <br/><br/>
          <strong>Votre offre: </strong>&euro; {$offer}<br/><br/>          
          Pour des informations supplémentaire, vous êtes le bienvenue<br/><br/>
          Cordialement<br/><br/>
          Vin.auction";
      break;

      case 'en':
      default:
        $subject = "Your offer for {$lot_title}";
        $body = "Hello,<br/><br/>
          Many thanks for your offer! At this moment you’re the highest bidder ({$lot_title})<br/>
          The buyers commission is 18% without any additional costs<br/><br/>
          {$lot_content}
          <br/><br/>
          <strong>Your bid: </strong>&euro; {$offer}<br/><br/>          
          For further questions, you’re welcome…<br/><br/>
          Kind Regards<br/><br/>
          Vin.auction";
      break;
    }

    $message['body'] = $body;
    $message['subject'] = $subject;

    parent::sendMessage($webform_submission, $message);
  }
}