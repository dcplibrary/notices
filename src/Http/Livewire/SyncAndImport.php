<?php

namespace Dcplibrary\Notices\Http\Livewire;

use Livewire\Component;
use Symfony\Component\Process\Process;

/**
 * SyncAndImport Livewire Component.
 *
 * Provides UI for importing FTP files with real-time progress feedback.
 * Includes toggle for patron delivery preference import.
 */
class SyncAndImport extends Component
{
    // Legacy date range selector (kept for backwards-compatible Blade template)
    public $dateRange = 'today'; // today, yesterday, last7days, custom
    public $startDate;
    public $endDate;

    // Shared date / range options for all imports
    public $rangeMode = 'days'; // 'days', 'date', 'range'

    public $rangeDays = 7;

    public $rangeAll = false;

    public $rangeDate;

    public $rangeStart;

    public $rangeEnd;

    // FTP-specific options
    public $importPatrons = false; // Toggle for patron import

    public $isImporting = false;

    public $progress = [];

    public $currentFile = null;

    public $importStats = [];

    // Log viewer modal visibility
    public $showLogModal = false;

    protected $rules = [
        'rangeMode' => 'required|in:days,date,range',
        'rangeDays' => 'nullable|integer|min:1',
        'rangeAll' => 'boolean',
        'rangeDate' => 'required_if:rangeMode,date|date',
        'rangeStart' => 'required_if:rangeMode,range|date',
        'rangeEnd' => 'required_if:rangeMode,range|date|after_or_equal:rangeStart',
        'importPatrons' => 'boolean',
    ];

    public function mount()
    {
        $today = now()->format('Y-m-d');

        // Initialize legacy date range fields used by the Blade view
        $this->startDate = $today;
        $this->endDate = $today;

        // Initialize shared range fields
        $this->rangeDate = $today;
        $this->rangeStart = $today;
        $this->rangeEnd = $today;

        $this->showLogModal = false;
    }

    /**
     * Start the FTP import process.
     */
    public function startImport()
    {
        $this->validate();

        $this->isImporting = true;
        $this->progress = [];
        $this->currentFile = null;
        $this->importStats = [];
        $this->showLogModal = true;

        // Build command
        $command = ['php', base_path('artisan'), 'notices:import-ftp-files'];

        // Add shared range parameters (mapped to CLI flags)
        foreach ($this->buildFtpRangeFlags() as $flag) {
            $command[] = $flag;
        }

        // Add patron import flag if enabled
        if ($this->importPatrons) {
            $command[] = '--import-patrons';
        }

        // Dispatch an event for the front-end listener to start streaming output
        $this->dispatch('startImportStream', command: $command);

        // Notify front-end to show a toast (Livewire v3 event)
        $this->dispatch('show-toast', [
            'type' => 'info',
            'message' => 'Import started...',
        ]);
    }

    /**
     * Start Polaris import (non-streaming; JSON response only).
     */
    public function startPolarisImport(): void
    {
        // Validate shared range options for Polaris
        $this->validate([
            'rangeMode' => 'required|in:days,date,range',
            'rangeAll' => 'boolean',
            'rangeDays' => 'nullable|integer|min:1',
            'rangeDate' => 'required_if:rangeMode,date|date',
            'rangeStart' => 'required_if:rangeMode,range|date',
            'rangeEnd' => 'required_if:rangeMode,range|date|after_or_equal:rangeStart',
        ]);

        [$options, $label] = $this->buildPolarisOptions();

        $this->isImporting = true;
        $this->progress = [];
        $this->currentFile = null;
        $this->importStats = [];
        $this->showLogModal = true;

        $this->dispatch('startPolarisImport', $options);

        $this->dispatch('show-toast', [
            'type' => 'info',
            'message' => "Polaris import started for {$label}...",
        ]);
    }

    /**
     * Build CLI flags for FTP import from the shared range options.
     */
    private function buildFtpRangeFlags(): array
    {
        $flags = [];

        if ($this->rangeAll) {
            // If the FTP command supports --all, enable it; otherwise you can ignore this.
            $flags[] = '--all';

            return $flags;
        }

        if ($this->rangeMode === 'date') {
            $flags[] = '--date=' . $this->rangeDate;
        } elseif ($this->rangeMode === 'range') {
            if ($this->rangeStart) {
                $flags[] = '--start=' . $this->rangeStart;
            }
            if ($this->rangeEnd) {
                $flags[] = '--end=' . $this->rangeEnd;
            }
        } else {
            $days = (int) ($this->rangeDays ?: 1);
            $flags[] = '--days=' . $days;
        }

        return $flags;
    }

    /**
     * Build JSON options array for Polaris import from shared range options.
     *
     * @return array{0: array, 1: string} [options, human label]
     */
    private function buildPolarisOptions(): array
    {
        $options = [];

        if ($this->rangeAll) {
            $options['all'] = true;

            return [$options, 'all history'];
        }

        if ($this->rangeMode === 'date') {
            $options['date'] = $this->rangeDate;

            return [$options, 'date ' . $this->rangeDate];
        }

        if ($this->rangeMode === 'range') {
            $options['start'] = $this->rangeStart;
            $options['end'] = $this->rangeEnd;

            return [$options, "range {$this->rangeStart} → {$this->rangeEnd}"];
        }

        $days = (int) ($this->rangeDays ?: 1);
        $options['days'] = $days;

        return [$options, "last {$days} day(s)"];
    }

    /**
     * Handle progress updates from the command.
     */
    public function updateProgress($data)
    {
        if (isset($data['file'])) {
            $this->currentFile = $data['file'];
        }

        if (isset($data['progress'])) {
            $this->progress[] = $data['progress'];
        }

        if (isset($data['stats'])) {
            $this->importStats = $data['stats'];
        }

        if (isset($data['completed'])) {
            $this->isImporting = false;

            $this->dispatch('show-toast', [
                'type' => $data['success'] ? 'success' : 'error',
                'message' => $data['message'],
                'duration' => 5000,
            ]);
        }

        // Notify front-end listeners (for auto-scroll, etc.) that progress changed
        $this->dispatch('updateProgress');
    }

    /**
     * Cancel the import.
     */
    public function cancelImport()
    {
        $this->dispatch('cancelImport');
        $this->isImporting = false;
        $this->showLogModal = false;

        $this->dispatch('show-toast', [
            'type' => 'warning',
            'message' => 'Import cancelled',
        ]);
    }

    /**
     * Open the log viewer modal.
     */
    public function openLogModal(): void
    {
        $this->showLogModal = true;
    }

    /**
     * Close (minimize) the log viewer modal.
     */
    public function closeLogModal(): void
    {
        $this->showLogModal = false;
    }

    public function render()
    {
        // Use the package namespaced settings view for the Sync & Import UI
        return view('notices::settings.sync-and-import');
    }
}
