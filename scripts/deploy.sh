#!/usr/bin/env bash
#
# Deploys the timin custom code from this git clone to the live Drupal site.
#
# Usage (on the Strato server):
#   ~/repos/timin/scripts/deploy.sh --dry-run   Show what would change, change nothing.
#   ~/repos/timin/scripts/deploy.sh             Deploy.
#
# Steps: git pull -> maintenance mode on -> database backup (Backup and
# Migrate, "drush bb") -> copy code ->
# drush updb -> drush cr -> maintenance mode off.
#
# Everything lives in main(), so bash has read the whole script before
# "git pull" can replace this file with a newer version.

set -euo pipefail

DRUPAL_ROOT="${TIMIN_DRUPAL_ROOT:-$HOME/timin.space/drupal}"
PHP="/opt/RZphp84/bin/php-cli"
# Folders copied from the repo into the site, relative to both roots.
SYNC_DIRS=("web/modules/custom" "web/themes/custom")

drush() {
  (cd "$DRUPAL_ROOT" && "$PHP" vendor/drush/drush/drush.php -y "$@")
}

sync_code() {
  local dir
  for dir in "${SYNC_DIRS[@]}"; do
    echo "== $dir"
    # --checksum: compare contents, not timestamps, so only real changes show.
    # --delete: files removed from git are removed from the site too.
    rsync -rlt --checksum --delete --itemize-changes "$@" \
      "$REPO/$dir/" "$DRUPAL_ROOT/$dir/"
  done
}

backup_database() {
  # Backup and Migrate quick backup: default_db -> private_files destination.
  # "drush bb" exits 0 even when the backup fails, so check its log output.
  local output
  output="$(drush bb 2>&1)" || true
  echo "$output"
  if [[ "$output" == *"[error]"* ]]; then
    echo "Database backup failed; stopping before any code is changed." >&2
    return 1
  fi
}

on_error() {
  echo >&2
  echo "!! Deploy FAILED. The site may still be in maintenance mode." >&2
  echo "!! After fixing the problem, rerun this script, or switch maintenance off with:" >&2
  echo "!!   cd $DRUPAL_ROOT && $PHP vendor/drush/drush/drush.php maint:set 0" >&2
}

main() {
  REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  local dry_run=0
  [[ "${1:-}" == "--dry-run" ]] && dry_run=1

  [[ -d "$DRUPAL_ROOT/web" ]] || { echo "No Drupal site at $DRUPAL_ROOT" >&2; exit 1; }

  cd "$REPO"
  if [[ -n "$(git status --porcelain)" ]]; then
    echo "The clone in $REPO has local changes; refusing to deploy:" >&2
    git status --short >&2
    exit 1
  fi
  git pull --ff-only
  echo "Commit: $(git log -1 --format='%h %s (%cd)' --date=short)"
  echo

  if (( dry_run )); then
    sync_code --dry-run
    echo
    echo "Dry run: nothing was changed on the site."
    return
  fi

  trap on_error ERR

  drush maint:set 1

  backup_database

  sync_code
  drush updatedb
  drush cache:rebuild
  drush maint:set 0

  trap - ERR
  echo
  echo "Deployed $(git log -1 --format='%h %s')."
}

main "$@"
