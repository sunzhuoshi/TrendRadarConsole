# GitHub Copilot Commit Message Instructions

Use [Conventional Commits 1.0.0](https://www.conventionalcommits.org/en/v1.0.0/) for **all** commit messages that Copilot suggests.

## Format
- `<type>(optional-scope): <short description>`
- Use lowercase (except proper nouns and acronyms), imperative tone, no trailing period; keep the summary under 72 characters.
- Add a body when helpful (what/why), wrapping lines at ~72 characters.
- For breaking changes, either mark the type/scope as breaking with `!` (e.g. `feat(api)!: <short description>`) or add a footer `BREAKING CHANGE: <details>`. Prefer the `BREAKING CHANGE:` footer for breaking changes unless the surrounding context clearly uses `!`.
- Reference issues in footers, e.g. `Refs #123` or `Fixes #123`.

## Common commit types (recommended)
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

Copilot should prefer these types, but may use other valid Conventional Commit types when clearly appropriate; this list is not exhaustive.

## Examples
- `feat(api): add keyword throttling`
- `fix(auth): handle expired sessions`
- `docs: add setup guide for Docker workers`
- `chore: update deployment script paths`
