<?php

namespace Drupal\timin_timetracking\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for deleting Project entities.
 *
 * @ingroup timin_timetracking
 */
class ProjectEntityDeleteForm extends ContentEntityDeleteForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $connection = \Drupal::service('database');
		$timesheet_results = array();
		$timesheet_query =  $connection->select('timesheet', 't');
		$timesheet_query->leftJoin('timesheet__field_project', 'tp', 't.id = tp.entity_id');
		$timesheet_query
			->condition('field_project_target_id', $entity->id())
			->fields('t',['id'])
			->fields('t',['name'])
			->fields('tp',['field_project_target_id']);
		$timesheet_results = $timesheet_query->execute()->fetchAll();

    if ( !empty($timesheet_results) ) {

			\Drupal::messenger()->addError(t('You can not delete this project as it is being referenced by %count daily reports.',[
				'%count' => count($timesheet_results),
			]));

/*
			$form['list'] = [
				'#theme' => 'item_list',
				'#items' => [],
			];
			foreach ($timesheet_results as $delta => $timesheet) {
				$form['list']['#items'][] = $timesheet->name;
			}
*/

      unset($form['actions']['submit']);
      unset($form['description']);

		}

		return $form;
  }



  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->getEntity();

    $entity->delete();
    $form_state->setRedirect('timin_timetracking.projects');
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
