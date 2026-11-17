<?php

namespace Drupal\timin_timetracking\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for deleting Employee entities.
 *
 * @ingroup timin_timetracking
 */
class EmployeeEntityDeleteForm extends ContentEntityDeleteForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $connection = \Drupal::service('database');
		$timesheet_data_results = array();
		$timesheet_data_query =  $connection->select('timesheet_data', 'td');
		$timesheet_data_query
			->condition('td.employee_id', $entity->id())
			->fields('td',['timesheet_id'])
			->fields('td',['employee_id'])
			->groupBy('timesheet_id','employee_id');
		$timesheet_data_results = $timesheet_data_query->execute()->fetchAll();

    if ( !empty($timesheet_data_results) ) {

			\Drupal::messenger()->addError(t('You can not delete this employee as it is being referenced by %count daily reports.',[
				'%count' => count($timesheet_data_results),
			]));

      unset($form['actions']['submit']);
      unset($form['description']);

		}

		return $form;
  }





  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $entity->delete();
    $form_state->setRedirect('timin_timetracking.employees');
    \Drupal::messenger()->addMessage($this->getDeletionMessage());
  }

  public function getQuestion() {
    $entity = $this->getEntity();

    return $this->t('Delete %label?', [
			'%label' => $entity->label(),
    ]);
  }

  protected function getDeletionMessage() {
    $entity = $this->getEntity();

    return $this->t('The @entity-type %label has been deleted.', [
			'@entity-type' => $entity
        ->getEntityType()
        ->getSingularLabel(),
      '%label' => $entity->label(),
   	]);
  }

}