<?php

namespace Drupal\clubhouse_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a filter form for the clubhouse booking admin list.
 */
class BookingFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'clubhouse_booking_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => TRUE,
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => [
        '' => $this->t('- All -'),
        'free' => $this->t('Free'),
        'requested' => $this->t('Requested'),
        'reserved' => $this->t('Reserved'),
        'booked' => $this->t('Booked'),
      ],
      '#default_value' => \Drupal::request()->query->get('status', ''),
    ];

    $form['filters']['actions'] = [
      '#type' => 'actions',
    ];

    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $form['filters']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $status = $form_state->getValue('status');
    $query = [];
    if ($status !== '') {
      $query['status'] = $status;
    }
    $form_state->setRedirect('clubhouse_booking.admin_slots', [], ['query' => $query]);
  }

  /**
   * Resets the filter form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('clubhouse_booking.admin_slots');
  }

}
