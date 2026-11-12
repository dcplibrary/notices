<?php

namespace Dcplibrary\Notices\Support\Progress;

use Illuminate\Console\OutputStyle;

/**
 * Console-rendering implementation of ProgressSink.
 *
 * Writes Unicode box-drawing progress tables to the Artisan console output
 * as the notices import pipeline processes each date. Each import step
 * (FTP, email, reconcile, failures) emits a labelled table or a single-line
 * summary when it completes.
 */
class ConsoleProgressSink implements ProgressSink
{
    private const LABEL_WIDTH                  = 52;
    private const COUNT_WIDTH                  = 6;
    protected ?\DateTimeImmutable $currentDate = null;

    /**
     * Create a new ConsoleProgressSink.
     *
     * @param OutputStyle $output Symfony console output adapter (available via $this->output in commands)
     */
    public function __construct(
        protected OutputStyle $output
    ) {}

    public function startDay(\DateTimeImmutable $date): void
    {
        $this->currentDate = $date;

        $this->output->writeln('');
        $this->output->writeln($date->format('Y-m-d'));
    }

    public function step(string $key, string $status, array $extra = []): void
    {
        // Only emit a line when a step completes; "pending" calls are just
        // internal signals from the pipeline.
        if ('done' !== $status) {
            return;
        }

        if ('ftp' === $key) {
            $this->renderFtpDetails($extra['files'] ?? []);

            return;
        }

        if ('email' === $key) {
            $this->renderEmailDetails($extra['files'] ?? []);

            return;
        }

        if ('reconcile' === $key) {
            $this->output->writeln('Reconciling notifications');

            return;
        }

        if ('failures' === $key) {
            $this->output->writeln('Detecting failures / invalid data');

            if (!empty($extra['stats']) && is_array($extra['stats'])) {
                $stats = $extra['stats'];

                $this->output->writeln('┌────────────┬────────┐');
                $this->output->writeln('│ Status     │ Count  │');
                $this->output->writeln('├────────────┼────────┤');
                foreach (['delivered', 'failed', 'pending', 'total'] as $k) {
                    if (!array_key_exists($k, $stats)) {
                        continue;
                    }
                    $this->output->writeln(sprintf('│ %-10s │ %6d │', ucfirst($k), $stats[$k]));
                }
                $this->output->writeln('└────────────┴────────┘');
                $this->output->writeln('');
            }

            return;
        }
    }

    public function endDay(\DateTimeImmutable $date): void
    {
        $this->output->writeln('✔  Completed notices import for ' . $date->format('Y-m-d') . '.');
    }

    protected function renderFtpDetails(array $files): void
    {
        if (empty($files)) {
            return;
        }

        // Group files by notification type based on filename prefix
        $groups = [
            'Holds notifications'      => [],
            'Renewal notifications'    => [],
            'Overdue notifications'    => [],
            'Notification Log exports' => [],
            'Other FTP data'           => [],
        ];

        foreach ($files as $file) {
            $label = (string) ($file['label'] ?? '');
            $count = (int) ($file['count'] ?? 0);
            $lower = strtolower($label);

            if (str_starts_with($lower, 'holds_')) {
                $groups['Holds notifications'][] = [$label, $count];
            } elseif (str_starts_with($lower, 'renew_')) {
                $groups['Renewal notifications'][] = [$label, $count];
            } elseif (str_starts_with($lower, 'overdue_') || str_starts_with($lower, 'overdue')) {
                $groups['Overdue notifications'][] = [$label, $count];
            } elseif (str_starts_with($lower, 'notification_log_')) {
                $groups['Notification Log exports'][] = [$label, $count];
            } else {
                $groups['Other FTP data'][] = [$label, $count];
            }
        }

        foreach ($groups as $heading => $rows) {
            if (empty($rows)) {
                continue;
            }

            $title = 'Importing ' . $heading;
            $this->output->writeln($title);

            $labelWidth = self::LABEL_WIDTH;
            $countWidth = self::COUNT_WIDTH;

            $top = '┌' . str_repeat('─', $labelWidth + 2) . '┬' . str_repeat('─', $countWidth + 2) . '┐';
            $sep = '├' . str_repeat('─', $labelWidth + 2) . '┼' . str_repeat('─', $countWidth + 2) . '┤';
            $bot = '└' . str_repeat('─', $labelWidth + 2) . '┴' . str_repeat('─', $countWidth + 2) . '┘';

            $this->output->writeln($top);
            $this->output->writeln(sprintf(
                '│ %-' . $labelWidth . 's │ %-' . $countWidth . 's │',
                'File / Source',
                'Count'
            ));
            $this->output->writeln($sep);

            $running = 0;
            foreach ($rows as [$label, $count]) {
                $label = (string) $label;

                if (mb_strlen($label) > $labelWidth) {
                    $label = mb_substr($label, 0, $labelWidth - 3) . '...';
                }

                $running += (int) $count;
                $this->output->writeln(sprintf(
                    '│ %-' . $labelWidth . 's │ %' . $countWidth . 'd │',
                    $label,
                    $running
                ));
            }

            $this->output->writeln($bot);
            $this->output->writeln('');
        }
    }

    protected function renderEmailDetails(array $files): void
    {
        if (empty($files)) {
            return;
        }

        $title = 'Importing email reports';
        $this->output->writeln($title);

        $labelWidth = self::LABEL_WIDTH;
        $countWidth = self::COUNT_WIDTH;

        $top = '┌' . str_repeat('─', $labelWidth + 2) . '┬' . str_repeat('─', $countWidth + 2) . '┐';
        $sep = '├' . str_repeat('─', $labelWidth + 2) . '┼' . str_repeat('─', $countWidth + 2) . '┤';
        $bot = '└' . str_repeat('─', $labelWidth + 2) . '┴' . str_repeat('─', $countWidth + 2) . '┘';

        $this->output->writeln($top);
        $this->output->writeln(sprintf(
            '│ %-' . $labelWidth . 's │ %-' . $countWidth . 's │',
            'Email Subject',
            'Count'
        ));
        $this->output->writeln($sep);

        $running = 0;
        foreach ($files as $file) {
            $label = (string) ($file['label'] ?? '');
            $count = (int) ($file['count'] ?? 0);

            if (mb_strlen($label) > $labelWidth) {
                $label = mb_substr($label, 0, $labelWidth - 3) . '...';
            }

            $running += $count;
            $this->output->writeln(sprintf(
                '│ %-' . $labelWidth . 's │ %' . $countWidth . 'd │',
                $label,
                $running
            ));
        }

        $this->output->writeln($bot);
        $this->output->writeln('');
    }
}
