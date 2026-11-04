# Seadragon Laravel Log Alerts

Private Composer package that checks Laravel's standard log files once per day
and emails a concise summary of recent `EMERGENCY`, `ALERT`, `CRITICAL`, and
`ERROR` entries.

## Requirements

- PHP 7.3 or later
- Laravel 8 through 13
- An application mail transport configured in `.env`

## Install

Add the package's private Git repository to the consuming application's
`composer.json` (update the URL for the repository where you publish it):

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:seadragon/laravel-log-alerts.git"
        }
    ]
}
```

Then install it:

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

## How it works

The package registers `log-alerts:email` and schedules it daily. It scans:

- `storage/logs/laravel.log`
- `storage/logs/laravel-YYYY-MM-DD.log` files spanning the last 24-hour window

Only entries in the last 24 hours are included. Repeated messages are grouped;
the email contains counts and short first-line summaries, not stack traces or
structured log context. No email is sent when there are no matching entries.

Preview the report without sending an email:

```sh
php artisan log-alerts:email --dry-run
```

The Laravel scheduler must be invoked by the host once per minute. Add the
following cron entry for each application using the package:

```cron
* * * * * cd /path/to/application && php artisan schedule:run >> /dev/null 2>&1
```

## Private distribution

For a private Git repository, Composer can install it directly from the VCS
repository as shown above, subject to the consuming environment having Git
credentials. Packagist.org is for public packages; for private distribution,
use Private Packagist or keep the VCS repository entry in each consuming
application.

## Development

```sh
composer install
composer test
```

The package is proprietary and is intended for Seadragon Digital projects.