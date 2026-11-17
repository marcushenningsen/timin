/**
 * @file
 * DataTables for the timesheet overview and the project timesheet table.
 *
 * Column indexes must match TimesheetTableBuilder::header().
 */
(function (Drupal, drupalSettings, once) {

  'use strict';

  /**
   * Strips tags for the SearchPanes ("sp") orthogonal data.
   */
  function stripTagsForPanes(data, type) {
    return type === 'sp' ? String(data).replace(/<[^>]*>?/gm, '') : data;
  }

  /**
   * Builds SearchPanes options matching list cells that contain a value.
   */
  function paneOptions(values, column) {
    return (values || []).map(function (value) {
      return {
        label: value,
        value: function (rowData) {
          return rowData[column].includes(value);
        },
      };
    });
  }

  function languageUrl() {
    var lang = drupalSettings.path.currentLanguage === 'de' ? 'de-DE' : 'en-GB';
    return '/libraries/datatables_i18n/' + lang + '.json';
  }

  var hidePane = { show: false };

  function overviewOptions() {
    return {
      language: { url: languageUrl() },
      fixedHeader: true,
      order: [[4, 'desc']],
      scrollX: true,
      orderClasses: false,
      layout: {
        top: 'searchPanes',
        topStart: 'pageLength',
        topEnd: 'search',
      },
      searchPanes: {
        threshold: 0.9,
        initCollapsed: true,
        cascadePanes: true,
      },
      select: {
        style: 'multi',
        selector: 'td:first-child',
        headerCheckbox: 'select-page',
      },
      columnDefs: [
        // select
        { targets: 0, searchable: false, orderable: false, searchPanes: hidePane, render: DataTable.render.select() },
        // flagging
        { targets: 1, searchable: false, orderable: false, searchPanes: hidePane },
        // flagged
        { targets: 2, visible: false },
        // name
        { targets: 3, searchable: false },
        // date
        { targets: 4, searchPanes: hidePane },
        // week
        { targets: 5, visible: false },
        // month-int
        { targets: 6, visible: false, searchPanes: hidePane },
        // month
        { targets: 7, visible: false, searchPanes: { dtOpts: { order: [3, 'asc'] } } },
        // year
        { targets: 8, visible: false },
        // project
        { targets: 9, render: stripTagsForPanes, searchPanes: { orthogonal: 'sp' } },
        // service types / extra works
        { targets: 10, searchPanes: { options: paneOptions(drupalSettings.extra_works, 10) } },
        // personnel
        { targets: 11, visible: false, searchPanes: { options: paneOptions(drupalSettings.personnel, 11) } },
        // author
        { targets: 12, render: stripTagsForPanes, searchPanes: { orthogonal: 'sp' } },
        // created date
        { targets: 13, searchPanes: hidePane },
        // event, internal note, extra works text
        { targets: [14, 15, 16], visible: false, searchPanes: hidePane },
      ],
    };
  }

  function projectOptions() {
    return {
      language: { url: languageUrl() },
      fixedHeader: true,
      order: [[4, 'desc']],
      scrollX: true,
      orderClasses: false,
      columnDefs: [
        // flagging
        { targets: 0, searchable: false, orderable: false, searchPanes: hidePane },
        // flagged
        { targets: 1, visible: false },
        // name
        { targets: 2, searchable: false },
        // date
        { targets: 3, searchPanes: hidePane },
        // week
        { targets: 4, visible: false },
        // month-int
        { targets: 5, visible: false, searchPanes: hidePane },
        // month
        { targets: 6, visible: false, searchPanes: { dtOpts: { order: [3, 'asc'] } } },
        // year
        { targets: 7, visible: false },
        // service types / extra works
        { targets: 8, searchPanes: { options: paneOptions(drupalSettings.extra_works, 8) } },
        // personnel
        { targets: 9, visible: false, searchPanes: { options: paneOptions(drupalSettings.personnel, 9) } },
        // author
        { targets: 10, render: stripTagsForPanes, searchPanes: { orthogonal: 'sp' } },
        // created date
        { targets: 11, searchPanes: hidePane },
      ],
    };
  }

  Drupal.behaviors.timinDatatables = {
    attach: function (context) {

      once('timin-timesheets', 'table.js-timin-timesheets', context).forEach(function (table) {
        var dataTable = new DataTable(table, overviewOptions());

        once('timin-print-selected', '#print-selected-btn', context).forEach(function (button) {
          button.addEventListener('click', function () {
            var ids = dataTable.rows({ selected: true }).nodes().toArray().map(function (row) {
              return row.dataset.timesheetId;
            });
            if (ids.length) {
              window.open(drupalSettings.base_path + '/batch/' + ids.join(','), '_blank');
            }
          });
        });
      });

      once('timin-project-timesheets', 'table.js-timin-project-timesheets', context).forEach(function (table) {
        new DataTable(table, projectOptions());
      });
    },
  };

})(Drupal, drupalSettings, once);
