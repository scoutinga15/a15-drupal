<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a form for requesting custom slots.
 */
class RequestCustomSlotForm extends FormBase {

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
   * Constructs a new RequestCustomSlotForm.
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
    return 'clubhouse_booking_request_custom_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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

    $form['start_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Requested Start Time'),
      '#required' => TRUE,
    ];

    $form['end_time'] = [
      '#type' => 'datetime',
      '#title' => $this->t('Requested End Time'),
      '#required' => TRUE,
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Additional Information'),
      '#description' => $this->t('Why do you need this custom slot?'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Request Slot'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $start = $form_state->getValue('start_time');
    $end = $form_state->getValue('end_time');

    if ($start instanceof DrupalDateTime && $end instanceof DrupalDateTime) {
      if ($start->getTimestamp() >= $end->getTimestamp()) {
        $form_state->setErrorByName('end_time', $this->t('End time must be after start time.'));
      }
      if ($start->getTimestamp() < time()) {
        $form_state->setErrorByName('start_time', $this->t('Start time cannot be in the past.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $name = $form_state->getValue('name');
    $email = $form_state->getValue('email');
    $start = $form_state->getValue('start_time')->getTimestamp();
    $end = $form_state->getValue('end_time')->getTimestamp();
    $message_text = $form_state->getValue('message');

    // For now, we just notify the admin.
    // In a real scenario, we might save this as a "pending" request.
    $admin_email = \Drupal::config('system.site')->get('mail');
    $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $params = [
      'user_name' => $name,
      'user_email' => $email,
      'start' => date('Y-m-d H:i', $start),
      'end' => date('Y-m-d H:i', $end),
      'message' => $message_text,
    ];

    $this->mailManager->mail('clubhouse_booking', 'custom_slot_request', $admin_email, $langcode, $params);

    $this->messenger()->addStatus($this->t('Your request for a custom slot has been sent to the administrator.'));
    $form_state->setRedirect('clubhouse_booking.calendar');
  }

}
