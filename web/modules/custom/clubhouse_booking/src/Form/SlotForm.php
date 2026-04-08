<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

/**
 * Provides a form for adding or editing slots.
 */
class SlotForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new SlotForm.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'clubhouse_booking_slot_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $slot_id = NULL) {
    $slot = NULL;
    if ($slot_id) {
      $slot = $this->database->select('clubhouse_slots', 's')
        ->fields('s')
        ->condition('id', $slot_id)
        ->execute()
        ->fetchObject();
    }

    $form['slot_id'] = [
      '#type' => 'value',
      '#value' => $slot_id,
    ];

    $form['booking_date'] = [
      '#type' => 'date',
      '#title' => $this->t('From date'),
      '#required' => TRUE,
      '#default_value' => $slot ? $slot->booking_date : '',
    ];

    $form['to_date'] = [
      '#type' => 'date',
      '#title' => $this->t('To date'),
      '#required' => FALSE,
      '#default_value' => $slot ? $slot->to_date : '',
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        'free' => $this->t('Free'),
        'requested' => $this->t('Requested'),
        'reserved' => $this->t('Reserved'),
        'booked' => $this->t('Booked'),
      ],
      '#required' => TRUE,
      '#default_value' => $slot ? $slot->status : 'free',
    ];

    $form['user_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#default_value' => $slot ? $slot->user_name : '',
    ];

    $form['user_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email address'),
      '#default_value' => $slot ? $slot->user_email : '',
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#default_value' => $slot ? $slot->message : '',
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $from = $form_state->getValue('booking_date');
    $to = $form_state->getValue('to_date');
    if ($from && $to && strtotime($to) < strtotime($from)) {
      $form_state->setErrorByName('to_date', $this->t('End date cannot be before start date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $slot_id = $form_state->getValue('slot_id');
    $fields = [
      'booking_date' => $form_state->getValue('booking_date'),
      'to_date' => $form_state->getValue('to_date'),
      'status' => $form_state->getValue('status'),
      'user_name' => $form_state->getValue('user_name'),
      'user_email' => $form_state->getValue('user_email'),
      'message' => $form_state->getValue('message'),
    ];

    if ($slot_id) {
      $this->database->update('clubhouse_slots')
        ->fields($fields)
        ->condition('id', $slot_id)
        ->execute();
      $this->messenger()->addStatus($this->t('Slot updated.'));
    }
    else {
      $fields['created'] = time();
      $this->database->insert('clubhouse_slots')
        ->fields($fields)
        ->execute();
      $this->messenger()->addStatus($this->t('Slot created.'));
    }

    $form_state->setRedirect('clubhouse_booking.admin_slots');
  }

}
