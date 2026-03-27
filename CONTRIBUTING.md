# Contributing

This repo is the base theme source of truth. Keep `main` stable and deployable.

## Branch Strategy
- `main`: protected branch, production-ready code only.
- `feature/*`: all normal work (example: `feature/footer-easter-egg`).
- `hotfix/*`: urgent fixes.

## Required Flow
1. Branch from `main`.
2. Commit to your feature branch.
3. Open a PR into `main`.
4. Get 1 approval.
5. Squash merge.
6. GitHub Actions auto-deploys `main` to staging.

## Commands
```bash
git checkout main
git pull
git checkout -b feature/<short-task-name>
git push -u origin feature/<short-task-name>
```

## Pull Request Rules
- Keep PRs focused and small.
- Include test notes in the PR template.
- Do not push directly to `main`.

## Deployment
- Deployment runs only on pushes to `main`.
- Merging a PR into `main` triggers staging deploy automatically.

## Local Testing Minimum
- Run PHP syntax checks for changed PHP files.
- Validate the changed UI path manually.
- For theme-impacting changes, smoke-test homepage + form flows.
