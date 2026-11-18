#!/usr/bin/env bash
#
# Deploys the timin custom code from this git clone to a Drupal site on Strato.
#
# Usage (on the Strato server):
#   ~/repos/timin/scripts/deploy.sh staging [--dry-run]
#   ~/repos/timin/scripts/deploy.sh production [--dry-run]
#
# staging:    fetches the latest commits (git pull) and deploys them.
# production: fetches nothing. Deploys exactly the commit that is on staging
#             and refuses if the clone holds anything else.
# --dry-run:  shows which files would change; changes nothing on the site.
#
# Steps: maintenance mode on -> database backup (Backup and Migrate,
# "drush bb") -> copy code -> drush updb -> drush cr -> maintenance mode off.
#
# Everything lives in main(), so bash has read the whole script before
# "git pull" can replace this file with a newer version.

set -euo pipefail

# Drupal project folders (the ones containing web/ and vendor/).
STAGING_ROOT="$HOME/dev.timin.space"
PRODUCTION_ROOT="$HOME/timin.space"
PHP="${TIMIN_PHP:-/opt/RZphp84/bin/php-cli}"
# Folders copied from the repo into the site, relative to both roots.
SYNC_DIRS=("web/modules/custom" "web/themes/custom")

usage() {
  echo "Usage: $0 staging|production [--dry-run]" >&2
  exit 1
}

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

IN_MAINTENANCE=0

# Runs whenever the script ends. If it ends while the site is (possibly)
# in maintenance mode, a step failed, so say so loudly.
on_exit() {
  (( IN_MAINTENANCE )) || return 0
  echo >&2
  echo "!! Deploy to $TARGET FAILED. The site is probably still in maintenance mode." >&2
  echo "!! After fixing the problem, rerun this script, or switch maintenance off with:" >&2
  echo "!!   cd $DRUPAL_ROOT && $PHP vendor/drush/drush/drush.php maint:set 0" >&2
}

# Where the script remembers which commit each site has. Inside .git, so it
# is never committed and doesn't count as a local change.
state_file() {
  echo "$REPO/.git/timin-deployed-$1"
}

main() {
  TARGET="${1:-}"
  local dry_run=0
  [[ "${2:-}" == "--dry-run" ]] && dry_run=1
  case "$TARGET" in
    staging) DRUPAL_ROOT="$STAGING_ROOT" ;;
    production) DRUPAL_ROOT="$PRODUCTION_ROOT" ;;
    *) usage ;;
  esac
  REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

  if [[ ! -f "$DRUPAL_ROOT/vendor/drush/drush/drush.php" || ! -d "$DRUPAL_ROOT/web" ]]; then
    echo "No Drupal project at $DRUPAL_ROOT (expected web/ and vendor/drush there)." >&2
    exit 1
  fi

  cd "$REPO"
  if [[ -n "$(git status --porcelain)" ]]; then
    echo "The clone in $REPO has local changes; refusing to deploy:" >&2
    git status --short >&2
    exit 1
  fi

  if [[ "$TARGET" == staging ]]; then
    git pull --ff-only
  else
    local staged head
    staged="$(cat "$(state_file staging)" 2>/dev/null || true)"
    head="$(git rev-parse HEAD)"
    if [[ -z "$staged" ]]; then
      echo "Nothing has been deployed to staging yet. Deploy to staging first." >&2
      exit 1
    fi
    if [[ "$staged" != "$head" ]]; then
      echo "Staging has $(git log -1 --format='%h %s' "$staged")," >&2
      echo "but the clone is at $(git log -1 --format='%h %s' "$head")." >&2
      echo "Deploy to staging (and test) first, then to production." >&2
      exit 1
    fi
  fi

  echo "Target: $TARGET ($DRUPAL_ROOT)"
  echo "Commit: $(git log -1 --format='%h %s (%cd)' --date=short)"
  echo

  if (( dry_run )); then
    sync_code --dry-run
    echo
    echo "Dry run: nothing was changed on the site."
    return
  fi

  trap on_exit EXIT

  IN_MAINTENANCE=1
  drush maint:set 1
  backup_database
  sync_code
  drush updatedb
  drush cache:rebuild
  drush maint:set 0
  IN_MAINTENANCE=0

  git rev-parse HEAD > "$(state_file "$TARGET")"
  echo
  echo "Deployed $(git log -1 --format='%h %s') to $TARGET."
}

main "$@"
