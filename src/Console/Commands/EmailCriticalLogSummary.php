<?php

namespace Seadragon\LaravelLogAlerts\Console\Commands;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Seadragon\LaravelLogAlerts\LogEntryScanner;
use Throwable;

class EmailCriticalLogSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log-alerts:email {--dry-run : Display the summary without sending an email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Email a summary of recent emergency, alert, critical, and error log entries';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $timezone = new DateTimeZone(config('app.timezone', date_default_timezone_get()));
        $end = new DateTimeImmutable('now', $timezone);
        $start = $end->modify('-24 hours');
        $scanner = new LogEntryScanner(storage_path('logs'), $timezone);
        $entries = $scanner->findBetween($start, $end);

        if (count($entries) === 0) {
            $this->info('No emergency, alert, critical, or error entries found in the last 24 hours.');

            return 0;
        }

        $siteName = config('app.name') ?: basename(base_path());
        $summary = $this->buildSummary($siteName, $start, $end, $entries);

        if ($this->option('dry-run')) {
            $this->line($summary);

            return 0;
        }

        $recipient = config('log-alerts.recipient');
        if (!is_string($recipient) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Set a valid DEVELOPER_EMAIL in the application .env file.');

            return 1;
        }

        try {
            Mail::raw($summary, function ($message) use ($recipient, $siteName) {
                $message->to($recipient)
                    ->subject('['.$siteName.'] Laravel log alert');
            });
        } catch (Throwable $exception) {
            $this->error('Could not send the log alert email: '.$exception->getMessage());

            return 1;
        }

        $this->info('Sent a summary of '.count($entries).' log entries for '.$siteName.'.');

        return 0;
    }

    /**
     * Build a concise report, grouping repeated messages and omitting stack traces.
     *
     * @param array<int, array{level: string, message: string}> $entries
     */
    protected function buildSummary($siteName, DateTimeImmutable $start, DateTimeImmutable $end, array $entries)
    {
        $levelCounts = array();
        $messageCounts = array();

        foreach ($entries as $entry) {
            if (!isset($levelCounts[$entry['level']])) {
                $levelCounts[$entry['level']] = 0;
            }
            $levelCounts[$entry['level']]++;

            $key = $entry['level'].'|'.strtolower($entry['message']);
            if (!isset($messageCounts[$key])) {
                $messageCounts[$key] = array(
                    'level' => $entry['level'],
                    'message' => $entry['message'],
                    'count' => 0,
                );
            }
            $messageCounts[$key]['count']++;
        }

        ksort($levelCounts);
        $messageCounts = array_values($messageCounts);
        usort($messageCounts, function ($first, $second) {
            return $second['count'] - $first['count'];
        });

        $lines = array(
            'Laravel log summary for '.$siteName,
            'Window: '.$start->format('Y-m-d H:i:s T').' to '.$end->format('Y-m-d H:i:s T'),
            'Matching entries: '.count($entries),
            '',
            'Counts by level:',
        );

        foreach ($levelCounts as $level => $count) {
            $lines[] = '- '.$level.': '.$count;
        }

        $lines[] = '';
        $lines[] = 'Most frequent error summaries (up to 30):';
        foreach (array_slice($messageCounts, 0, 30) as $item) {
            $lines[] = '- ['.$item['level'].'] '.$item['count'].' occurrence(s): '.$item['message'];
        }

        return implode("\n", $lines);
    }
}
