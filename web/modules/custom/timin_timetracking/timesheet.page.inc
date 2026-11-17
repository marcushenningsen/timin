<?php

/**
 * @file
 * Contains timesheet.page.inc.
 *
 * Page callback for Timesheet entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Timesheet templates.
 *
 * Default template: timesheet.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_timesheet(array &$variables) {
  // Fetch TimesheetEntity Entity Object.
  $timesheet = $variables['elements']['#timesheet'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
