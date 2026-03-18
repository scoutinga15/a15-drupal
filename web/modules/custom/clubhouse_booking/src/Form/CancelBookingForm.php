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
   * The booking ID to cancel.
   *
   * @var int
   */
  protected $bookingId;

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
  public function buildForm(array $form, FormStateInterface $form_state, $booking_id = NULL) {
    $this->bookingId = $booking_id;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $booking = $this->database->select('clubhouse_bookings', 'b')
      ->fields('b')
      ->condition('id', $this->bookingId)
      ->execute()
      ->fetchObject();

    if ($booking) {
      $this->database->delete('clubhouse_bookings')
        ->condition('id', $this->bookingId)
        ->execute();

      $this->database->update('clubhouse_slots')
        ->fields(['is_booked' => 0])
        ->condition('id', $booking->slot_id)
        ->execute();

      // Send cancellation email.
      $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $params = [
        'user_name' => $booking->user_name,
      ];
      $this->mailManager->mail('clubhouse_booking', 'booking_cancellation', $booking->user_email, $langcode, $params);

      $this->messenger()->addStatus($this->t('Your booking has been cancelled.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
