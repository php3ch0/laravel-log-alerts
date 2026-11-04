<?php

namespace Seadragon\LaravelLogAlerts\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Seadragon\LaravelLogAlerts\LogEntryScanner;

class LogEntryScannerTest extends TestCase
{
    /**
     * @var string
     */
    protected $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-log-alerts-'.uniqid('', true);
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.DIRECTORY_SEPARATOR.'*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    public function test_it_scans_single_and_dated_logs_for_selected_levels_in_window()
    {
        file_put_contents(
            $this->directory.DIRECTORY_SEPARATOR.'laravel-2026-06-01.log',
            "[2026-06-01 12:00:00] production.ERROR: Example failure {\"userId\":42}\n".
            "[2026-06-01 12:01:00] production.INFO: Routine message\n".
            "[2026-06-01 12:02:00] production.CRITICAL: Critical failure\n".
            "[2026-06-01 08:00:00] production.CRITICAL: Outside window\n"
        );
        file_put_contents(
            $this->directory.DIRECTORY_SEPARATOR.'laravel.log',
            "[2026-06-02 10:00:00] local.ALERT: Alert message\n".
            "[2026-06-02 11:00:00] local.EMERGENCY: Emergency message\n".
            "[2026-06-02 12:00:00] local.CRITICAL: After window\n"
        );

        $timezone = new DateTimeZone('UTC');
        $scanner = new LogEntryScanner($this->directory, $timezone);
        $entries = $scanner->findBetween(
            new DateTimeImmutable('2026-06-01 10:00:00', $timezone),
            new DateTimeImmutable('2026-06-02 11:30:00', $timezone)
        );

        $levels = array_column($entries, 'level');
        sort($levels);

        $this->assertSame(array('ALERT', 'CRITICAL', 'EMERGENCY', 'ERROR'), $levels);
        $error = array_values(array_filter($entries, function ($entry) {
            return $entry['level'] === 'ERROR';
        }));
        $this->assertSame('Example failure', $error[0]['message']);
    }
}
