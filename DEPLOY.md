# Deploying to timin.space (Strato)

Production gets its own git clone of this repo at `~/repos/timin`.
`scripts/deploy.sh` pulls the latest commit and copies
`web/modules/custom` and `web/themes/custom` into the live site at
`~/timin.space/drupal`, then runs the database updates and clears caches.

Only custom code is deployed. Core, contrib, `vendor/`, `settings.php`,
uploaded files and the database are managed on the server as before.

## Everyday workflow

1. Locally: commit and push to GitHub.
2. On Strato (SSH): `~/repos/timin/scripts/deploy.sh --dry-run` shows what would change.
3. If that looks right: `~/repos/timin/scripts/deploy.sh`.

Each deploy puts the site in maintenance mode for a few seconds and first
makes a database backup with Backup and Migrate (`drush bb`, saved to the
private files destination). If the backup fails, the script stops before
touching any code. Manage old backups in the Backup and Migrate UI.

### Rolling back

Undo the bad commit locally with `git revert <commit>` (this adds a new
commit that reverses it), push, and deploy as usual. Never edit files in
`~/repos/timin` directly; the script refuses to run if the clone has changes.

A code rollback does not undo database changes made by `hook_update_N()`.
For that, restore the backup taken before the deploy in the Backup and
Migrate UI (Configuration → Development → Backup and Migrate → Saved
backups → Restore).

## One-time setup

All commands run on Strato over SSH unless noted.

### 1. Check the tools

```sh
git --version     # 2.55.0 is fine
rsync --version   # needed by the deploy script
```

Backup and Migrate needs a private files path (`$settings['file_path_private']`
in settings.php). Check that `drush bb` works on its own before the first deploy.

### 2. Give the server read access to the GitHub repo (deploy key)

```sh
ssh-keygen -t ed25519 -f ~/.ssh/timin_deploy -N "" -C "strato-timin-deploy"
cat ~/.ssh/timin_deploy.pub
```

On GitHub: repo → Settings → Deploy keys → Add deploy key. Paste the
public key, title "Strato", leave "Allow write access" **off**.

Then add this to `~/.ssh/config` on Strato (create the file if needed):

```
Host github-timin
  HostName github.com
  User git
  IdentityFile ~/.ssh/timin_deploy
  IdentitiesOnly yes
```

Test it with `ssh -T github-timin`; it should greet you with
"You've successfully authenticated". If it times out, Strato blocks port 22
outbound. Change `HostName` to `ssh.github.com` and add `Port 443`.

### 3. Clone the repo

```sh
mkdir -p ~/repos
git clone github-timin:marcushenningsen/timin.git ~/repos/timin
```

### 4. Back up the current custom code, then do a dry run

The first deploy replaces whatever was uploaded by SFTP, and `--delete`
removes files on the server that are not in git. Keep a copy first:

```sh
mkdir -p ~/backups
cd ~/timin.space/drupal/web
tar czf ~/backups/custom-before-git-$(date +%Y%m%d).tar.gz modules/custom themes/custom
~/repos/timin/scripts/deploy.sh --dry-run
```

Read the list. Lines starting with `*deleting` are files that exist only on
the server. If any of them are needed, add them to the repo first.
Then run the real deploy.

From then on, stop uploading custom code by SFTP; changes go through git.
