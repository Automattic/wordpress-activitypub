# Supply chain

Dependencies and install-time code.

Applies to: `package.json`, `package-lock.json`, `composer.json`, `composer.lock`

- Run `npm audit` and `composer audit` (if available); flag high and critical.
- Check `package-lock.json` for known compromised packages (for example axios 1.14.1/0.30.4, event-stream, ua-parser-js).
- No dependency fetches remote code at install time, for example in `postinstall` (`grep -r postinstall node_modules/*/package.json`).
- `composer.lock` pins exact versions.
- Dev dependencies do not end up in the build (check the `wp-scripts build` output).
