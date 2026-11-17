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