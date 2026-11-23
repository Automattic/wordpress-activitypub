# Allow More Notification Emails

Raises the number of notification emails a user can get from the ActivityPub plugin per hour.

The plugin sends at most 30 emails about new followers, direct messages and mentions to the same recipient per hour. When that allowance is used up, one more email says that further ones will not be sent until the next hour. The followers, messages and mentions themselves are stored as usual and show up in the dashboard.

On a busy site 30 can be too few. This snippet raises the allowance to 100.

## Installation

Copy this folder to `wp-content/plugins/` and activate **Allow More Notification Emails** from the WordPress admin, or copy `more-notification-emails.php` to `wp-content/mu-plugins/` for automatic activation.

## Requirements

- WordPress 5.9+
- PHP 7.4+
- [ActivityPub](https://wordpress.org/plugins/activitypub/) plugin

## Another number, or no limit

Change the `100` in `more_notification_emails()` to the number you want. Returning `PHP_INT_MAX` switches the limit off; then a remote server can cause as many emails as it sends follows, messages and mentions.

The check for the `mail` bucket has to stay. The `activitypub_rate_limit` filter also sets how many requests per minute the REST endpoints answer, and without the check the snippet would change those too.
