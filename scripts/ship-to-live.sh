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

if [[ -n "$(git status --porcelain)" ]]; then
  git add -A
  git commit -m "$MSG"
fi

# GitHub often protects main (no direct push). Use a PR branch automatically.
if [[ "$BRANCH" == "main" ]]; then
  if git push origin main 2>/dev/null; then
    echo "Done: pushed main. GitHub Actions will deploy to SiteGround."
    exit 0
  fi
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

gh pr merge "$PR" --squash --delete-branch=false

git fetch origin main
git checkout main
git pull origin main --ff-only

echo "Done: PR #$PR merged to main. GitHub Actions will deploy to SiteGround."
