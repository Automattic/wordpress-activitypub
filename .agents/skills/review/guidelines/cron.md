# Scheduled callbacks

Cron callbacks with side effects someone can see.

Applies to: `includes/scheduler/**`, `includes/class-scheduler.php`, and any new `wp_schedule_event` or `wp_schedule_single_event`

- A callback that sends email, push notifications, outbound requests or federated activities guards against running twice. WP-Cron can fire the same callback more than once for one period: concurrent loopback workers, `register_schedules()` on reactivation, a manual `wp cron event run`, traffic spikes.
- The guard is an atomic claim before the side effect, usually `add_option( $key, $value, '', false )` keyed on the period, recipient or activity, with an early return when the claim fails. Check-then-send, time windows and transient locks are not race-safe.
- With a `$force` parameter, the claim is still written on forced runs, or the next scheduled run sends the same message again.
