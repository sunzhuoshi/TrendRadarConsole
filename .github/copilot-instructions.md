# GitHub Copilot Commit Message Instructions

Use [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/) for **all** commit messages that Copilot suggests.

## Format
- `<type>(optional-scope): <short description>`
- Use lowercase, imperative tone, no trailing period; keep the summary under 72 characters.
- Add a body when helpful (what/why), wrapping lines at ~72 characters.
- For breaking changes, add a footer `BREAKING CHANGE: <details>`.
- Reference issues in footers, e.g. `Refs #123` or `Fixes #123`.

## Allowed types (common)
- `feat` — new feature
- `fix` — bug fix
- `docs` — documentation only
- `style` — formatting changes (no logic)
- `refactor` — code refactoring without behavior change
- `perf` — performance improvement
- `test` — add or update tests
- `build` — build system or dependencies
- `ci` — CI configuration or scripts
- `chore` — maintenance tasks
- `revert` — reverts a previous commit

## Examples
- `feat(api): add keyword throttling`
- `fix(auth): handle expired sessions`
- `docs: add setup guide for docker workers`
- `chore: update deployment script paths`
