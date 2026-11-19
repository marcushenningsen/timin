# Deploying to Strato

Both sites are on the same Strato server, same SSH login:

| Parameter | Site            | Folder              |
|-----------|-----------------|---------------------|
| `staging` | dev.timin.space | `~/dev.timin.space` |
| `prod`    | timin.space     | `~/timin.space`     |

`scripts/deploy.sh` fetches the latest code from GitHub into `~/repos/timin`
and copies `web/modules/custom` and `web/themes/custom` into the chosen site.
Core, contrib, `vendor/`, `settings.php`, files and the database are not touched.

## Deploying

1. Locally: commit and push to GitHub.
2. On Strato:
   ```sh
   ~/repos/timin/scripts/deploy.sh staging --dry-run   # only lists what would change
   ~/repos/timin/scripts/deploy.sh staging
   ```
3. Test on dev.timin.space, then the same with `prod`.

A deploy: maintenance mode on → database backup (`drush bb`) → copy code →
`drush updb` → `drush cr` → maintenance mode off.

If a step fails, the script stops and the site stays in maintenance mode.
Fix the problem, then deploy again or switch it off by running
`drush maint:set 0` in that site's folder.





## One-time setup (on Strato)

1. Check that `rsync --version` works. Each site has its own Drush; the
   `drush` alias in `~/.bashrc` runs the one of the folder you're in:
   ```sh
   alias drush='/opt/RZphp84/bin/php-cli vendor/drush/drush/drush.php'
   ```
   `cd ~/dev.timin.space && drush status` and `cd ~/timin.space && drush status`
   should each show their own site URL and database.
2. Deploy key, so the server can read the GitHub repo:
   ```sh
   ssh-keygen -t ed25519 -f ~/.ssh/timin_deploy -N "" -C "strato-timin-deploy"
   cat ~/.ssh/timin_deploy.pub
   ```
   Add the key on GitHub: repo → Settings → Deploy keys (write access **off**).
   Then put this in `~/.ssh/config`:
   ```
   Host github-timin
     HostName github.com
     User git
     IdentityFile ~/.ssh/timin_deploy
     IdentitiesOnly yes
   ```
   Test with `ssh -T github-timin`. If it times out, use
   `HostName ssh.github.com` plus `Port 443`.
3. Clone:
   ```sh
   git clone github-timin:marcushenningsen/timin.git ~/repos/timin
   ```
4. Before the first deploy, back up the code uploaded by SFTP, because the
   deploy removes files that aren't in git:
   ```sh
   mkdir -p ~/backups
   cd ~/dev.timin.space/web && tar czf ~/backups/custom-dev-before-git.tar.gz modules/custom themes/custom
   cd ~/timin.space/web && tar czf ~/backups/custom-prod-before-git.tar.gz modules/custom themes/custom
   ```
   In the first dry run, check lines starting with `*deleting`.
