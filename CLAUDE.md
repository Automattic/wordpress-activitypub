## WordPress ActivityPub Development

This project uses AI coding assistant skills to provide contextual guidance for development workflows.

### Available Skills

The following skills are available in `.claude/skills/`:

- **activitypub-dev-cycle** — Development workflows, testing, linting, and environment setup
- **activitypub-php-conventions** — PHP coding standards and WordPress patterns
- **activitypub-pr-workflow** — Pull request creation and review processes
- **activitypub-federation** — ActivityPub protocol implementation and federation
- **activitypub-testing** — PHPUnit and E2E testing patterns
- **activitypub-release** — Version management and release processes
- **activitypub-integrations** — Third-party plugin integration patterns

**CRITICAL:** After reading a skill, check if a local skill override file exists at `~/.claude/skills/{skill-name}-local/SKILL.md` and apply it too. 
For example, after reading `.claude/skills/activitypub-dev-cycle/SKILL.md`, check for `~/.claude/skills/activitypub-dev-cycle-local/SKILL.md`.

**Local override skills take precedence over project-level skills in case of conflict.**

## Notes for Claude

- This doc provides context; skills provide procedures
- When in doubt about HOW to do something, check the skills
- When in doubt about WHAT something is or WHERE it fits, check this doc
- Skills are invoked automatically when relevant to the task
