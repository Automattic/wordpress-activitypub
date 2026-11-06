# Output and links

Escaping, and remote values that become a link, a redirect or a navigation target.

Applies to: `includes/wp-admin/**`, `includes/transformer/**`, `templates/**`, `src/**`, `includes/class-comment.php`, `includes/class-emoji.php`

## Past issues

- **Stored XSS** (WPScan `58a63507-…` / `c15a6032-…`, fixed 1.0.0/1.0.1): contributors and above could inject scripts.
- **Remote URLs reaching a link sink** (2026): values from remote documents (a WebFinger link `template`, actor fields, any member that later becomes an `href`, a `window.open()` target or a redirect) were escaped but not validated.
- **Remote emoji names matched as given** (2026): emoji `tag` names from remote actors and objects were stored and replaced in content as they came, without a check that they were shortcodes, one tag after another. Now only valid shortcodes are stored and matched, in one pass.

## Escaping

Files: `includes/wp-admin/`, `includes/transformer/`.

- Admin output uses `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`. Remote actor names and bios in admin are escaped.
- JSON-LD output does not reflect unsanitized input.
- Error messages do not leak sensitive data.

## Remote values that become a URL

A value from a remote document is as untrusted as an inbox activity, and escaping it is not validating it.

- **Allow schemes, do not escape.** `esc_url()` is wrong for URI templates (it strips `{uri}` / `{object}`), and `wp_allowed_protocols()` is wider and filterable, so another plugin could widen a gate it does not know about.
- **A scheme check alone is not enough.** Protocol-relative (`//host`), schemeless paths, an empty authority (`https:///path`) and empty or whitespace values give `wp_kses_bad_protocol()` nothing to strip. Also require a host and compare case-folded, because kses lowercases the scheme it returns.
- Server side: `get_url_authority()` + `wp_kses_bad_protocol( $value, array( 'http', 'https' ) )` + a case-folded compare. Client side: `isSafeUrl()` in `src/shared/safe-url.js`. Keep the two in step, and enforce both: where the value is stored or returned, and where it reaches the sink.
- Not `filter_var( …, FILTER_VALIDATE_URL )`, which rejects IDN hosts that `new URL()` accepts, and not `wp_http_validate_url()`, whose fetch rules (ports, private IPs) do not apply to a URL the browser opens.
- Values stored before the gate existed (`localStorage`, options, post meta) are validated again on read.
- Find every remote field that ends in `window.open(`, an `href`, `wp_redirect()` or `data-wp-bind--href`, and confirm a scheme allow list sits on the path. Skip a bad value instead of failing, so the other links keep working.

## Remote values that become a search key

A remote value that the plugin looks for in content (today emoji names; any other remote name the plugin later looks for in content) is validated to its expected shape before it is stored and again before it is matched, because records stored earlier can hold others.

- Emoji go through `Emoji::get_shortcode()`: `:name:` with letters, digits, `_` and `-`, 2 to 100 characters.
- Replace all keys in one pass (`Emoji::replace_names()`), so the output of one replacement is never matched by the next.
- Look up only the keys the text uses, so the work scales with the text and not with what the remote sent.
