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

    if (!$slot || $slot->status !== 'free') {
      throw new NotFoundHttpException();
    }

    $form['slot_id'] = [
      '#type' => 'value',
      '#value' => $slot_id,
    ];

    $date_string = $slot->booking_date;
    if ($slot->to_date && $slot->to_date != $slot->booking_date) {
      $date_string .= ' - ' . $slot->to_date;
    }

    $form['slot_info'] = [
      '#markup' => '<p>' . $this->t('Booking for: @date', [
        '@date' => $date_string,
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

    $this->database->update('clubhouse_slots')
      ->fields([
        'user_name' => $name,
        'user_email' => $email,
        'status' => 'booked',
        'created' => time(),
      ])
      ->condition('id', $slot_id)
      ->execute();

    $slot = $this->database->select('clubhouse_slots', 's')
      ->fields('s')
      ->condition('id', $slot_id)
      ->execute()
      ->fetchObject();

    // Send email notification.
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();
    $date_string = $slot->booking_date;
    if ($slot->to_date && $slot->to_date != $slot->booking_date) {
      $date_string .= ' - ' . $slot->to_date;
    }

    $params = [
      'user_name' => $name,
      'date' => $date_string,
      'cancel_url' => Url::fromRoute('clubhouse_booking.cancel', ['slot_id' => $slot_id], ['absolute' => TRUE])->toString(),
    ];
    $this->mailManager->mail('clubhouse_booking', 'booking_confirmation', $email, $langcode, $params);

    $this->messenger()->addStatus($this->t('Your booking has been confirmed!'));
    $form_state->setRedirect('clubhouse_booking.calendar');
  }

}
