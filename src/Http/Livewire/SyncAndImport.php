<?php

namespace Dcplibrary\Notices\Http\Livewire;

use Livewire\Component;
use Symfony\Component\Process\Process;

/**
 * SyncAndImport Livewire Component
 * 
 * Provides UI for importing FTP files with real-time progress feedback.
 * Includes toggle for patron delivery preference import.
 */
class SyncAndImport extends Component
{
    public $dateRange = 'today'; // 'today', 'yesterday', 'last7days', 'custom'
    public $startDate;
    public $endDate;
    public $importPatrons = false; // Toggle for patron import
    public $isImporting = false;
    public $progress = [];
    public $currentFile = null;
    public $importStats = [];

    protected $rules = [
        'dateRange' => 'required|in:today,yesterday,last7days,custom',
        'startDate' => 'required_if:dateRange,custom|date',
        'endDate' => 'required_if:dateRange,custom|date|after_or_equal:startDate',
        'importPatrons' => 'boolean',
    ];

    public function mount()
    {
        $this->startDate = now()->format('Y-m-d');
        $this->endDate = now()->format('Y-m-d');
    }

    /**
     * Start the import process
     */
    public function startImport()
    {
        $this->validate();

        $this->isImporting = true;
        $this->progress = [];
        $this->currentFile = null;
        $this->importStats = [];

        // Build command
        $command = ['php', base_path('artisan'), 'notices:import-ftp-files'];

        // Add date parameters
        switch ($this->dateRange) {
            case 'today':
                // Default, no parameters needed
                break;
            case 'yesterday':
                $command[] = '--days=1';
                break;
            case 'last7days':
                $command[] = '--days=7';
                break;
            case 'custom':
                $command[] = '--start-date=' . $this->startDate;
                $command[] = '--end-date=' . $this->endDate;
                break;
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
     * Handle progress updates from the command
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
     * Cancel the import
     */
    public function cancelImport()
    {
        $this->dispatch('cancelImport');
        $this->isImporting = false;

        $this->dispatch('show-toast', [
            'type' => 'warning',
            'message' => 'Import cancelled',
        ]);
    }

    public function render()
    {
        // Use the package namespaced settings view for the Sync & Import UI
        return view('notices::settings.sync-and-import');
    }
}
