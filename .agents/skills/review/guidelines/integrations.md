# Third-party integrations

What each integration hands to another plugin, and on whose terms.

Applies to: `integration/**`

Files: `integration/`, `integration/load.php`, and the other plugin's source when it is installed.

An integration hands our routes, content or data to code we do not control, with its own trust model, matching rules and load order. A guarantee that holds inside the plugin does not survive the handoff on its own, so audit the handoff, not just our side.

- **What goes over, and on whose terms.** For each `add_filter` / `add_action` into another plugin's API, say what is handed over and what that plugin does with it. Read its implementation of the hook, not its name or docblock. When it is not installed, check the `class_exists` / `function_exists` guard in `integration/load.php` and that both states are safe.
- **Only hand something to a shared system when every caller gets the same result.** A cache, search index, feed, export or CDN keyed on a URL or post ID cannot tell callers apart, so nothing that depends on identity, capability, credential or signature may go there. Check the other system's real matching rule: a prefix usually covers everything below it.
- **The drift is on our side.** A list of routes, fields or post types in an integration stays as it is while the surface grows; a new route under a listed prefix joins the handoff with no edit to review. Derive the list from the current source.
- **Pin those lists with a test** that walks the live source and fails when something caller-dependent falls under a listed entry. A "keep in sync" comment is not a control.
- **Check that our hook runs.** A filter applied from a must-use loader or at plugin load can run before our plugin is loaded. Verify it, do not assume.
- **Data that leaves follows the recipient's rules.** Whatever goes into a third-party cache, index, feed or export is readable under that system's rules. Trace how long it lives and who can read it.
- The fetch and escaping rules (`remote-fetch.md`, `output.md`) apply to `integration/` as they do to `includes/`.
