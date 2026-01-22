## WordPress ActivityPub Development

This project uses AI coding assistant skills to provide contextual guidance for development workflows.

### Available Skills

The following skills are available in `.claude/skills/`:

- **dev** — Development workflows, testing, linting, and environment setup
- **code-style** — PHP coding standards and WordPress patterns
- **pr** — Pull request creation and review processes
- **federation** — ActivityPub protocol implementation and federation
- **test** — PHPUnit and E2E testing patterns
- **release** — Version management and release processes
- **integrations** — Third-party plugin integration patterns

### Available Agents

The following agents are available in `.claude/agents/`:

- **summary** — Summarize the session at its end (auto-invoked on goodbye)

**CRITICAL:** After reading a skill, check if a local skill override file exists at `~/.claude/skills/{skill-name}-local/SKILL.md` and apply it too.
For example, after reading `.claude/skills/dev/SKILL.md`, check for `~/.claude/skills/dev-local/SKILL.md`.

**Local override skills take precedence over project-level skills in case of conflict.**

## Notes for Claude

- This doc provides context; skills provide procedures
- When in doubt about HOW to do something, check the skills
- When in doubt about WHAT something is or WHERE it fits, check this doc
- Skills are invoked automatically when relevant to the task
