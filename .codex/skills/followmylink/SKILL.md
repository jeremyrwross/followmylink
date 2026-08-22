---
name: followmylink
description: Use when working on the FollowMyLink Laravel application, including redirect testing, URL safety, abuse controls, authentication, Livewire/Flux UI, Laravel backend code, tests, validation, GitHub issue tracking, branching, commits, pushes, and pull requests for this repository.
---

# FollowMyLink

## Overview

FollowMyLink is a Laravel 13 application with Livewire 4, Flux UI, Pest 4, Larastan, Pint, and Laravel Boost. Use this skill for project-specific implementation flow, validation, and GitHub tracking in `/home/jeremy/projects/followmylink`.

Repository: `git@github.com:jeremyrwross/followmylink.git`

## Mandatory Implementation Tracking Gate

Before editing any tracked file for implementation work:

1. Identify every repository that will change.
2. Create a GitHub issue in each affected repository using the connected GitHub MCP app.
3. Cross-link the issues when more than one repository is involved.
4. Create or switch to an issue-specific branch in each repository before making implementation edits.

Do not postpone this gate until after implementation. Read-only investigation and planning may happen before issues exist, but tracked-file implementation may not. If the GitHub connector cannot create the required tracking items, stop before editing and report the blocker.

Skip this gate only for read-only answers, code review with no edits, or when the user explicitly asks for local-only work with no issue/PR.

## Branch, Commit, and PR Flow

- Prefer branch names that include the issue number, such as `codex/issue-123-target-host-throttling`.
- Keep branches focused on one issue or tightly related set of changes.
- Preserve unrelated worktree changes. Do not revert user changes.
- After implementation and validation, commit the completed work, push the branch, and create a pull request.
- Every implementation branch and PR must be tied to a GitHub issue.
- Include a closing or tracking reference in the PR body:
  - Use `Closes #123` only when the PR fully resolves the issue.
  - Use `Advances #123`, `Related to #123`, or `Part of #123` when follow-up work remains.
- Include a `Manual checks` section in every PR body with concrete reviewer steps. If there is no meaningful browser/runtime path, say what code or test review should focus on instead.

Use the connected GitHub MCP app for remote GitHub operations: repositories, issues, pull requests, comments, labels, checks, workflow runs, jobs, and logs. Use local `git` for checkout state, branches, staging, commits, and SSH pushes. If a required remote operation is not exposed by the GitHub connector, report the missing capability before falling back to another path.

## Project Rules and Laravel Boost

Before planning or editing, follow `AGENTS.md` and Laravel Boost rules:

- Read `.ai/rules/index.md` when it exists.
- Read every rule file whose globs cover the path being changed.
- Run `grep -rin 'keyword' .ai/rules` for relevant task keywords.
- Use Laravel Boost `search-docs` before Laravel ecosystem code changes.
- Use Boost `record-rule` for durable project conventions.

## Code Expectations

- Follow existing Laravel, Livewire, Flux, Pest, and Tailwind conventions in nearby files.
- Keep changes scoped to the requested behavior.
- Prefer Laravel APIs, named routes, dependency injection, Eloquent resources for APIs when the project pattern supports them, and framework rate limiting/cache primitives for abuse controls.
- For redirect testing and URL safety, preserve SSRF protections: public IP checks, port allow-listing, timeout/body/hop caps, direct requests, and per-target-host throttling.
- Add or update tests for every behavior change.
- If PHP files change, run `vendor/bin/pint --dirty --format agent` before finishing.

## Validation

Run the narrowest meaningful checks first, then broaden before PR when practical:

```sh
php artisan test --compact path/to/TestFile.php
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse
npm run build
composer test
```

For frontend changes, run `npm run build`. If a dev server URL is needed, resolve it with Laravel Boost `get-absolute-url` before sharing.

Before opening or updating a PR, include the exact commands run and their results in the final response and, when useful, in the PR body.

## PR Check Follow-Up

After pushing and opening the PR, inspect the PR checks through the GitHub connector when available. Report compact status. Fetch logs only for failed checks. Do not mark the work complete while required checks are pending or failing unless the user explicitly asks to stop there.
