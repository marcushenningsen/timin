<?php

namespace Drupal\filter_newline\Plugin\Filter;

use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter to convert line breaks to HTML with nl2br.
 *
 * @Filter(
 *   id = "filter_newline",
 *   title = @Translation("Convert line breaks into HTML with nl2br"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE,
 * )
 */
class FilterNewline extends FilterBase {

/*
  public function process($text, $langcode): FilterProcessResult {
    return new FilterProcessResult($text);
  }
*/

  public function process($text, $langcode) {
    return new FilterProcessResult(nl2br($text));
    //return new FilterProcessResult($text);
  }

}
