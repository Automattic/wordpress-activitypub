# Accessibility

User interface changes: blocks, the admin app, settings screens, templates.

Applies to: `src/**`, `templates/**`, `includes/wp-admin/**`, `assets/js/**`, `assets/css/**`

- Semantic HTML: a `<button>` for actions, not a `<div>` with a click handler; headings in order.
- ARIA only where HTML has no element for it.
- Every control is reachable and usable with the keyboard, in a sensible order, with no trap.
- Focus moves to a modal or new view when it opens, and back when it closes.
- Form fields have a `<label>` or an `aria-label`.
- Text has enough contrast, and nothing relies on color alone.
- Visual-only context (an icon button, a count) has screen reader text.
- Prefer `@wordpress/components`, which handle most of this already.
