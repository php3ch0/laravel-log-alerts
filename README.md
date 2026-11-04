# Laravel Log Alerts

Composer package that checks Laravel's standard log files once per day
and emails a concise summary of recent `EMERGENCY`, `ALERT`, `CRITICAL`, and
`ERROR` entries.

## Requirements

- PHP 7.3 or later
- Laravel 8 through 13
- An application mail transport configured in `.env`

## Install

Install the package from [Packagist](https://packagist.org/packages/seadragon/laravel-log-alerts):

```sh
composer require seadragon/laravel-log-alerts
```

Laravel package auto-discovery registers the service provider. Set the
notification recipient and mail transport in the application's `.env`:

```dotenv
DEVELOPER_EMAIL=your@email.com
MAIL_MAILER=smtp
# Configure the remaining MAIL_* values for your mail provider.
```

The package reads `DEVELOPER_EMAIL` as the email destination. If configuration
is cached, rebuild the cache after changing `.env` with
`php artisan config:cache`.

Email alerts are sent only when `APP_ENV=production`. In other environments the
command reports that it skipped sending; `--dry-run` still displays the summary
without sending an email.

## How it works

The package registers `log-alerts:email` and schedules it daily at 08:00 UTC. It
scans:

- `storage/logs/laravel.log`
- `storage/logs/laravel-YYYY-MM-DD.log` files spanning the last 24-hour window

Only entries in the last 24 hours are included. Repeated messages are grouped;
the email contains counts, short first-line summaries, and the exception source
file and line number when available. Other structured log context and stack
traces are omitted. No email is sent when there are no matching entries.

Preview the report without sending an email:

```sh
php artisan log-alerts:email --dry-run
```

The Laravel scheduler must be invoked by the host once per minute. Add the
following cron entry for each application using the package:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

## Distribution

The package is published on [Packagist](https://packagist.org/packages/seadragon/laravel-log-alerts).
Its source code is hosted at
[github.com/php3ch0/laravel-log-alerts](https://github.com/php3ch0/laravel-log-alerts).

## Development

```sh
composer install
composer test
```
