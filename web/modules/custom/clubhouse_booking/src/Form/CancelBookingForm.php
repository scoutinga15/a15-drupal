<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

use Drupal\Core\Mail\MailManagerInterface;

/**
 * Provides a confirmation form for cancelling a booking.
 */
class CancelBookingForm extends ConfirmFormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The slot ID to cancel.
   *
   * @var int
   */
  protected $slotId;

  /**
   * Constructs a new CancelBookingForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(Connection $database, MailManagerInterface $mail_manager) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'clubhouse_booking_cancel_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to cancel this booking?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('clubhouse_booking.calendar');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $slot_id = NULL) {
    $this->slotId = $slot_id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $slot = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->condition('id', $this->slotId)
      ->execute()
      ->fetchObject();

    if ($slot && in_array($slot->status, ['requested', 'reserved', 'booked'])) {
      $this->database->delete('clubhouse_slots')
        ->condition('id', $this->slotId)
        ->execute();

      // Send cancellation email.
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $date_string = $slot->booking_date;
      if ($slot->to_date && $slot->to_date != $slot->booking_date) {
        $date_string .= ' - ' . $slot->to_date;
      }
      $params = [
        'user_name' => $slot->user_name,
        'date' => $date_string,
      ];
      $this->mailManager->mail('clubhouse_booking', 'booking_cancellation', $slot->user_email, $langcode, $params);

      $this->messenger()->addStatus($this->t('Your booking has been cancelled.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
