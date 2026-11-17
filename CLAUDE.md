# Timin

## Layout
- This repo holds only custom code, mounted into the container:
  - web/modules/custom → /opt/timin/web/modules/custom
  - web/themes/custom/claro_timin
- Core, contrib and vendor live in a Docker volume. Never change them.

## Commands (PHP, Composer and Drush are NOT installed on the host)
- Drush: `docker compose exec -u www-data timin vendor/bin/drush <cmd>`
- Composer: `docker compose exec timin composer <cmd>`
- After changes to routing, services, plugins, hooks or Twig: `drush cr`
- Schema changes go in hook_update_N(); run `drush updb -y`

## Conventions
- Drupal coding standards; dependency injection, no \Drupal:: calls in classes.
- CoreUI, not vanilla Bootstrap: use the `coreui.*` JS namespace and `data-coreui-*` attributes, never `bootstrap.*` / `data-bs-*`.
- Prefer the #states API over the conditional_fields module.