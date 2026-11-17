<?php

namespace Drupal\timin_timetracking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class TimesheetBatchController extends ControllerBase {

  public function downloadMultiple(string $ids): Response {
    $id_list = array_map('intval', explode(',', $ids));
    $entities = $this->entityTypeManager()
      ->getStorage('timesheet')
      ->loadMultiple($id_list);

    $view_builder = $this->entityTypeManager()->getViewBuilder('timesheet');

    $build = [];
    $count = count($entities);
    $i = 0;

		foreach ($entities as $entity) {
      $i++;
      $build[] = [
        '#type' => 'container',
        '#attributes' => [
          // Don't add the break class after the last item.
          'class' => $i < $count ? ['timesheet-print-page-break'] : [],
        ],
        'content' => $view_builder->view($entity, 'full'),
      ];
    }
		
    $manager = \Drupal::service('printable.format_plugin_manager');
    $plugin = $manager->createInstance('print');
		$plugin->setContent($build);

    return $plugin->getResponse();
  }

}