#!/usr/bin/env bash
# Ship this theme to production: GitHub main → SiteGround (via .github/workflows/deploy-staging.yml).
# Usage: ./scripts/ship-to-live.sh "commit message here"
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ "$(pwd)" == *"/.worktrees/"* ]]; then
  echo "Run this from the main theme folder, not .worktrees/: $ROOT" >&2
  exit 1
fi

MSG="${1:-chore: ship theme updates}"
BRANCH="$(git branch --show-current)"

echo "Running PHP syntax validation (php -l)..."
find . -name "*.php" -print0 | xargs -0 -n1 php -l

if [[ -n "$(git status --porcelain)" ]]; then
  git add -A
  git commit -m "$MSG"
fi

# GitHub protects main. Always ship via PR branch (no direct push).
if [[ "$BRANCH" == "main" ]]; then
  AUTO="chore/auto-ship-$(date +%Y%m%d-%H%M%S)"
  git checkout -b "$AUTO"
  BRANCH="$AUTO"
  git push -u origin "$BRANCH"
fi

git push -u origin "$BRANCH"

PR="$(gh pr list --head "$BRANCH" --state open --json number -q '.[0].number' 2>/dev/null || true)"
if [[ -z "$PR" || "$PR" == "null" ]]; then
  gh pr create --base main --head "$BRANCH" --title "$MSG" --body "Ship to production (squash merge → main → deploy)."
  PR="$(gh pr list --head "$BRANCH" --state open --json number -q '.[0].number')"
fi

gh pr merge "$PR" --squash --delete-branch

git fetch origin main
git checkout main
# Squash merge rewrites history; ff-only pull fails if we had the same changes as a local commit on main.
git reset --hard origin/main

echo "Done: PR #$PR merged to main. GitHub Actions will deploy to SiteGround."
