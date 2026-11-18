# Deploying to Strato (staging and production)

Both sites live on the same Strato server, under the same SSH login:

| Target       | URL             | Drupal project folder |
|--------------|-----------------|-----------------------|
| `staging`    | dev.timin.space | `~/dev.timin.space`   |
| `production` | timin.space     | `~/timin.space`       |

One git clone of this repo at `~/repos/timin` serves both. `scripts/deploy.sh`
copies `web/modules/custom` and `web/themes/custom` from the clone into the
chosen site, then runs the database updates and clears caches.

Only custom code is deployed. Core, contrib, `vendor/`, `settings.php`,
uploaded files and the database are managed on each site as before.

## Everyday workflow

1. Locally: commit and push to GitHub.
2. On Strato (SSH), deploy to staging:
   - `~/repos/timin/scripts/deploy.sh staging --dry-run` fetches the latest
     commits and shows which files would change.
   - `~/repos/timin/scripts/deploy.sh staging` deploys them.
3. Test on dev.timin.space.
4. Deploy the same code to production:
   - `~/repos/timin/scripts/deploy.sh production --dry-run`
   - `~/repos/timin/scripts/deploy.sh production`

Production never fetches anything itself. It always gets exactly the commit
that is on staging. If you pushed something new and ran a staging dry run
(which fetches it), production refuses until that commit has been deployed
to staging too. That's intended: nothing reaches production untested.

Each deploy puts the site in maintenance mode for a few seconds and first
makes a database backup with Backup and Migrate (`drush bb`, saved to that
site's private files destination). If the backup fails, the script stops
before touching any code. Manage old backups in the Backup and Migrate UI.

If any step fails, the script says so and the site stays in maintenance
mode; it prints the command to switch maintenance off.

### Rolling back

Undo the bad commit locally with `git revert <commit>` (this adds a new
commit that reverses it), push, and deploy as usual (staging, then
production). Never edit files in `~/repos/timin` directly; the script
refuses to run if the clone has changes.

A code rollback does not undo database changes made by `hook_update_N()`.
For that, restore the backup taken before the deploy in the Backup and
Migrate UI (Configuration → Development → Backup and Migrate → Saved
backups → Restore).

## One-time setup

All commands run on Strato over SSH.

### 1. Check the tools and both sites

```sh
git --version     # 2.55.0 is fine
rsync --version   # needed by the deploy script
```

For each site (`~/dev.timin.space` and `~/timin.space`):

- Drush works and talks to the right site:
  `cd ~/dev.timin.space && /opt/RZphp84/bin/php-cli vendor/drush/drush/drush.php status`
  should show the dev.timin.space URI and the staging database. If the URI is
  wrong, set it in that site's `drush/drush.yml`.
- `drush bb` works on its own. Backup and Migrate needs a private files path
  (`$settings['file_path_private']` in that site's settings.php).

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

### 4. First deploy: back up, dry run, deploy (staging first)

The first deploy to each site replaces whatever was uploaded by SFTP, and
`--delete` removes files on the server that are not in git. Keep a copy of
both sites' custom code first:

```sh
mkdir -p ~/backups
for site in dev.timin.space timin.space; do
  (cd ~/$site/web && tar czf ~/backups/custom-$site-before-git-$(date +%Y%m%d).tar.gz modules/custom themes/custom)
done
~/repos/timin/scripts/deploy.sh staging --dry-run
```

Read the list. Lines starting with `*deleting` are files that exist only on
the server. If any of them are needed, add them to the repo first.
Then deploy to staging, test, and repeat with `production`.

From then on, stop uploading custom code by SFTP; changes go through git.
