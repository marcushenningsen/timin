/**
 * @file
 * Generic DataTables init for the project and employee lists.
 *
 * Table options (order, page length, ...) come from data-* attributes set
 * in ListController::buildTable().
 */
(function (Drupal, drupalSettings, once) {

  'use strict';

  Drupal.behaviors.timinListTables = {
    attach: function (context) {
      once('timin-list-table', 'table.js-timin-list-table', context).forEach(function (table) {
        var lang = drupalSettings.path.currentLanguage === 'de' ? 'de-DE' : 'en-GB';
        new DataTable(table, {
          language: { url: '/libraries/datatables_i18n/' + lang + '.json' },
          orderClasses: false,
          autoWidth: false,
        });
      });
    },
  };

})(Drupal, drupalSettings, once);
