<?php

namespace Seadragon\LaravelLogAlerts;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class LogEntryScanner
{
    /**
     * @var string
     */
    protected $logDirectory;

    /**
     * @var DateTimeZone
     */
    protected $timezone;

    public function __construct($logDirectory, DateTimeZone $timezone)
    {
        $this->logDirectory = $logDirectory;
        $this->timezone = $timezone;
    }

    /**
     * Return selected Laravel log entries in the requested time window.
     *
     * @return array<int, array{level: string, message: string, location: string|null}>
     */
    public function findBetween(DateTimeImmutable $start, DateTimeImmutable $end)
    {
        $files = $this->logFilesForWindow($start, $end);
        $entries = array();

        foreach ($files as $file) {
            $handle = @fopen($file, 'r');
            if ($handle === false) {
                continue;
            }

            while (($line = fgets($handle)) !== false) {
                if (!preg_match('/^\[([^\]]+)\]\s+.+\.(EMERGENCY|ALERT|CRITICAL|ERROR):\s?(.*)$/i', rtrim($line, "\r\n"), $matches)) {
                    continue;
                }

                try {
                    $loggedAt = new DateTimeImmutable($matches[1], $this->timezone);
                } catch (Throwable $exception) {
                    continue;
                }

                if ($loggedAt < $start || $loggedAt > $end) {
                    continue;
                }

                $location = $this->extractLocation($matches[3]);

                // Monolog appends structured context as JSON. Keep the
                // human-readable message and source location, not other
                // potentially sensitive context.
                $message = preg_replace('/\s+\{.*$/', '', $matches[3]);
                $message = trim($message);
                if ($message === '') {
                    $message = '(no message)';
                }

                $entries[] = array(
                    'level' => strtoupper($matches[2]),
                    'message' => substr(preg_replace('/\s+/', ' ', $message), 0, 300),
                    'location' => $location,
                );
            }

            fclose($handle);
        }

        return $entries;
    }

    /**
     * Extract the exception source location from Laravel's serialized exception context.
     *
     * @return string|null
     */
    protected function extractLocation($text)
    {
        if (preg_match('/\b(?:at|in)\s+([^\s()]+\.php):(\d+)\b/i', $text, $matches)) {
            return $matches[1].':'.$matches[2];
        }

        return null;
    }

    /**
     * Find laravel.log and dated files spanning the requested time window.
     *
     * @return array<int, string>
     */
    protected function logFilesForWindow(DateTimeImmutable $start, DateTimeImmutable $end)
    {
        $files = array();
        $singleFile = $this->logDirectory.DIRECTORY_SEPARATOR.'laravel.log';

        if (is_file($singleFile)) {
            $files[] = $singleFile;
        }

        // The extra day of slack accounts for midnight and timezone boundaries.
        $date = $start->setTime(0, 0, 0)->modify('-1 day');
        $lastDate = $end->setTime(0, 0, 0)->modify('+1 day');

        while ($date <= $lastDate) {
            $file = $this->logDirectory.DIRECTORY_SEPARATOR.'laravel-'.$date->format('Y-m-d').'.log';
            if (is_file($file)) {
                $files[] = $file;
            }

            $date = $date->modify('+1 day');
        }

        return array_values(array_unique($files));
    }
}
