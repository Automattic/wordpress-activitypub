# Multisite

Data stored in tables that every site of a network shares.

Applies to: any new or changed `*_user_meta()`, `*_user_option()`, `*_site_option()` or `*_network_option()` call, `$wpdb->usermeta` queries, and `includes/class-migration.php`

- Users and user meta are shared by all sites of a network; posts, comments and options are per site. Data that belongs to one site is stored per site: a user option (key with `$wpdb->get_blog_prefix()`), an option, or post meta. Plain user meta is only for data that is the same for the user on every site.
- Lookups by key and reads use the prefixed key. `get_user_option()` falls back to the key without the prefix.
- `CUSTOM_USER_TABLE` and `CUSTOM_USER_META_TABLE` share user data between sites that are not multisite. Treat them like multisite.
- A migration runs once per site. A migration of shared data is safe on the second site, and per-site cleanup does not depend on finding the shared rows.
- The change has a test that also runs with `WP_MULTISITE=1`.
