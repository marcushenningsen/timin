#!/usr/bin/env bash
# Deploys the custom code from ~/repos/timin to a site on Strato.
# Usage: deploy.sh staging|prod [--dry-run]
set -euo pipefail          # stop at the first failing command

# The braces make bash read the whole script before running it,
# so "git pull" can safely replace this file while it runs.
{

case "${1:-}" in
  staging) SITE=~/dev.timin.space ;;
  prod)    SITE=~/timin.space ;;
  *) echo "Usage: deploy.sh staging|prod [--dry-run]"; exit 1 ;;
esac
DRUSH="/opt/RZphp84/bin/php-cli $SITE/vendor/drush/drush/drush.php -y"
RSYNC="rsync -rlt --checksum --delete --itemize-changes"

cd ~/repos/timin
git pull --ff-only

if [ "${2:-}" = "--dry-run" ]; then
  $RSYNC --dry-run web/modules/custom/ "$SITE/web/modules/custom/"
  $RSYNC --dry-run web/themes/custom/  "$SITE/web/themes/custom/"
  exit 0
fi

$DRUSH maint:set 1

# Database backup (Backup and Migrate). "drush bb" reports success even
# when it fails, so look for an error in its output.
BACKUP=$($DRUSH bb 2>&1) || true
echo "$BACKUP"
if [[ "$BACKUP" == *"[error]"* ]]; then
  echo "Backup failed. Stopping; the site is still in maintenance mode."
  exit 1
fi

$RSYNC web/modules/custom/ "$SITE/web/modules/custom/"
$RSYNC web/themes/custom/  "$SITE/web/themes/custom/"
$DRUSH updb
$DRUSH cr
$DRUSH maint:set 0
echo "Deployed to $1."
exit

}
