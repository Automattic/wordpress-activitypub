/**
 * Backwards-compat shim for `withSyncEvent` (WordPress 6.8+).
 *
 * `withSyncEvent` wraps an Interactivity action so that the event object
 * remains synchronously available — required since WordPress 6.9 deprecated
 * the implicit-sync `data-wp-on-async--{event}` directive.
 *
 * On WordPress 6.5–6.7 the helper is undefined. The original `data-wp-on`
 * directive already delivers events synchronously on those versions, so the
 * identity fallback below preserves behavior without bumping the plugin's
 * minimum WordPress version.
 */
import * as iAPI from '@wordpress/interactivity';

export const withSyncEvent = iAPI.withSyncEvent ?? ( ( fn ) => fn );
