# HTTP signatures

Both signature verifiers, the keyId binding, and how hosts are compared.

Applies to: `includes/class-signature.php`, `includes/signature/**`, `includes/rest/trait-verification.php`, `includes/functions-url.php`, `includes/class-webfinger.php`

## Past issues

- **keyId parser divergence** (2026): `verify_key_id()`, the only binding of key host to actor host, read keyId with a regex that required quotes and returned `true` when it found none. The RFC 9421 verifier accepted an unquoted `keyid`, so the signature verified and the binding was skipped.
- **Same-host key accepted for another actor** (2026): `verify_key_id()` bound the key to the actor by host only. Where users hold their own keys (Friendica lets every user export theirs), user A signed an Update claiming to be user B on the same host, and `Update::update_actor()` stored A's key and inbox in B's `ap_actor` record. From then on A's signatures verified as B, and B's own failed.
- **One rule, two verifiers** (2026): the requester picks the verifier (`Signature-Input` selects RFC 9421, otherwise draft-cavage) and what gets signed. A signature over `(request-target) date` or `("@method")` verifies on its own terms.

## Verification

Files: `includes/class-signature.php`, `includes/signature/class-http-signature-draft.php`, `includes/signature/class-http-message-signature.php`.

- Every rule holds for both verifiers. A binding is only in place when both enforce it.
- The key's owner is the activity actor; sharing its host is not enough (Mastodon's `different_actor?`). Take the owner from where the key was resolved: the stored actor the keyId points to, else the key as its host serves it (`Proxy::get()`). Compare the owner, not the keyId string, so key aliases on the same host keep working.
- Each verifier requires a signed component that names this site (draft: `host`; RFC 9421: `@target-uri`, `@authority` or `host`; each checked by `is_signature_host()`) and, with a body, a signed `digest` / `content-digest` whose header is present. A signed but absent header enters the signed data empty and vouches for any body.
- POST requests without `date` or the digest are rejected. The clock skew window is minutes, not hours.
- Weak algorithms are not accepted.
- Fetching the key (the actor's `publicKey`) does not allow SSRF (see `remote-fetch.md`).
- The signature deferral in `includes/handler/class-delete.php` is intentional: the key may be gone before the Delete arrives, and a Tombstone check covers it. Not a finding.

## Canonicalization

When two identifiers are compared, or one is decoded before a lookup, the canonical form is the gate.

- **The folded host must be the host that is contacted.** `fold_host()`/`fold_authority()` run IDNA UTS #46, which maps code points: soft hyphen and zero-width characters vanish, fullwidth letters become ASCII (`exam<U+00AD>ple.com` folds to `example.com`). WordPress' HTTP layer punycodes the unmapped string instead (`WpOrg\Requests\IdnaEncoder`). Today `wp_http_validate_url()` refuses non-ASCII hosts (`gethostbyname()` fails), so these URLs are never fetched; a path that A-labels first or fetches outside the WP HTTP API changes that. Fold both sides of a gate, never one, and flag a neighbouring gate that compares raw hosts.
- **IDNA output can contain characters the input did not.** Fullwidth `／`, `＠`, `：`, `？`, `＃` become their ASCII forms, so check that a folded host is a bare host before putting it into a URL.
- **A validating regex is only as strict as its modifiers.** `\p{L}`/`\p{M}`/`\p{N}` without `u` match single bytes. With `u`, `preg_*` returns `false`/`NULL` on invalid UTF-8, so callers need a fail-closed branch and never append a `NULL` result to content. `ACTIVITYPUB_USERNAME_REGEXP` states its modifiers, and every caller uses them.
- **A second decode widens a lookup.** REST decodes query parameters once; after another `rawurldecode()`, values like `*`, `_` and the empty string, and encoded spellings of stored identifiers, are reachable. Decode the identifier, never a host a gate already compared, and reject an empty host explicitly so it never matches an unset stored host.
