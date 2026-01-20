# Fix Git Merge Conflict

You have local changes that conflict with the remote. Here are your options:

## Option 1: Stash Local Changes (Recommended)

If you want to keep your local changes but apply remote updates first:

```bash
cd /var/www/kawhe

# Stash your local changes
git stash

# Pull the latest changes
git pull

# If you want to reapply your local changes later:
# git stash pop
```

## Option 2: Discard Local Changes

If your local changes aren't important and you want to use the remote version:

```bash
cd /var/www/kawhe

# Discard local changes
git checkout -- app/Services/Wallet/AppleWalletPassService.php

# Pull the latest changes
git pull
```

## Option 3: Commit Local Changes First

If you want to keep your local changes:

```bash
cd /var/www/kawhe

# See what changed
git diff app/Services/Wallet/AppleWalletPassService.php

# If you want to keep the changes, commit them
git add app/Services/Wallet/AppleWalletPassService.php
git commit -m "Local changes to AppleWalletPassService"

# Then pull (may need to merge)
git pull
```

## Recommended: Option 1 (Stash)

This is safest - it saves your changes and lets you pull:

```bash
cd /var/www/kawhe
git stash
git pull
```

Then continue with debugging the 500 error.
