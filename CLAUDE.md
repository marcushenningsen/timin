# Timin

## Layout
- This repo holds only custom code, mounted into the container:
  - web/modules/custom → /opt/drupal/web/modules/custom
  - web/themes/custom → /opt/drupal/web/themes/custom (theme: claro_timin)
- Core, contrib and vendor live in a Docker volume. Never change them.

## Commands (PHP, Composer and Drush are NOT installed on the host)
- Compose service is `timin_drupal`; start with `docker compose up -d`.
- Drush: `docker compose exec -u www-data timin_drupal vendor/bin/drush <cmd>`
- Composer: `docker compose exec timin_drupal composer <cmd>`
- From Git Bash, prefix with `MSYS_NO_PATHCONV=1` when passing container paths, or they get rewritten to Windows paths.
- After changes to routing, services, plugins, hooks or Twig: `drush cr`
- Schema changes go in hook_update_N(); run `drush updb -y`

## Conventions
- Drupal coding standards; dependency injection, no \Drupal:: calls in classes.
- Short array syntax `[]` only, never `array()`.
- CoreUI, not vanilla Bootstrap: use the `coreui.*` JS namespace and `data-coreui-*` attributes, never `bootstrap.*` / `data-bs-*`.
- Prefer the #states API over the conditional_fields module.

### Don't reintroduce
- Views for timesheets, projects or employees: tables are built by `TimesheetTableBuilder` and `ListController`.
- The contrib `datatables` module (no stable D11 release): DataTables is loaded locally from `/libraries/datatables` (+ `/libraries/datatables_i18n`), never from a CDN.
- DataTables SearchPanes 3.x or other DataTables Plus–licensed extensions: keep to the MIT-licensed versions.
- Server-side AJAX for table filtering: filtering is client-side in DataTables.
- pdf_api/dompdf: PDFs are made by the browser from the `printable` module's print format.

### Tables and JS
- Tables keep the legacy CSS classes (`dataTable`, `dataTable_project`, `stripe`) for styling only. JS hooks onto `js-*` classes only (`js-timin-timesheets`, `js-timin-project-timesheets`, `js-timin-list-table`).
- JS uses `Drupal.behaviors` + `once()`.
- Sortable cells carry `data-order` (raw timestamp or int); boolean cells also carry `data-search`.
- Pass data JS needs (personnel names, extra-works names, list base_path) via `drupalSettings`, not markup.
- Access-check table cells: link the author only with view access; show the edit icon only with update access.