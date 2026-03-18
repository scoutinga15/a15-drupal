<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;

/**
 * Provides a booking form.
 */
class BookingForm extends FormBase {

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
   * Constructs a new BookingForm.
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
    return 'clubhouse_booking_booking_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $slot_id = NULL) {
    $slot = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->condition('id', $slot_id)
      ->execute()
      ->fetchObject();

    if (!$slot || $slot->is_booked) {
      throw new NotFoundHttpException();
    }

    $form['slot_id'] = [
      '#type' => 'value',
      '#value' => $slot_id,
    ];

    $form['slot_info'] = [
      '#markup' => '<p>' . $this->t('Booking for: @start to @end', [
        '@start' => date('Y-m-d H:i', $slot->start_time),
        '@end' => date('Y-m-d H:i', $slot->end_time),
      ]) . '</p>',
    ];

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your Email'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Confirm Booking'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $slot_id = $form_state->getValue('slot_id');
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');

    $booking_id = $this->database->insert('clubhouse_bookings')
      ->fields([
        'slot_id' => $slot_id,
        'user_name' => $name,
        'user_email' => $email,
        'booking_time' => time(),
      ])
      ->execute();

    $this->database->update('clubhouse_slots')
      ->fields(['is_booked' => 1])
      ->condition('id', $slot_id)
      ->execute();

    $slot = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->condition('id', $slot_id)
      ->execute()
      ->fetchObject();

    // Send email notification.
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();
    $params = [
      'user_name' => $name,
      'start' => date('Y-m-d H:i', $slot->start_time),
      'end' => date('Y-m-d H:i', $slot->end_time),
      'cancel_url' => Url::fromRoute('clubhouse_booking.cancel', ['booking_id' => $booking_id], ['absolute' => TRUE])->toString(),
    ];
    $this->mailManager->mail('clubhouse_booking', 'booking_confirmation', $email, $langcode, $params);

    $this->messenger()->addStatus($this->t('Your booking has been confirmed!'));
    $form_state->setRedirect('clubhouse_booking.calendar');
  }

}
