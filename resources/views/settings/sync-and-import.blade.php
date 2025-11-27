<div class="max-w-4xl mx-auto p-6">
    <div class="bg-white rounded-lg shadow-lg p-6 relative">
        <!-- Loading Overlay -->
        <div wire:loading class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-lg">
            <div class="text-center">
                <svg class="animate-spin h-10 w-10 text-blue-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-600">Processing...</p>
            </div>
        </div>

        <h2 class="text-2xl font-bold mb-6">Sync & Import FTP Files</h2>

        <!-- Date Range Selection -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
            <div class="flex gap-4">
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="dateRange" name="dateRange" value="today" class="form-radio" wire:loading.attr="disabled">
                    <span class="ml-2">Today</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="dateRange" name="dateRange" value="yesterday" class="form-radio" wire:loading.attr="disabled">
                    <span class="ml-2">Yesterday</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="dateRange" name="dateRange" value="last7days" class="form-radio" wire:loading.attr="disabled">
                    <span class="ml-2">Last 7 Days</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="radio" wire:model.live="dateRange" name="dateRange" value="custom" class="form-radio" wire:loading.attr="disabled">
                    <span class="ml-2">Custom Range</span>
                </label>
            </div>
        </div>

        <!-- Custom Date Range -->
        @if($dateRange === 'custom')
        <div class="mb-6 grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                <input type="date" wire:model.live="startDate" class="form-input w-full rounded-md border-gray-300" wire:loading.attr="disabled">
                @error('startDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                <input type="date" wire:model.live="endDate" class="form-input w-full rounded-md border-gray-300" wire:loading.attr="disabled">
                @error('endDate') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
            </div>

            @if(!$rangeAll)
                @if($rangeMode === 'days')
                    <div class="flex items-end gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Days</label>
                            <input type="number" min="1" wire:model.defer="rangeDays"
                                   class="form-input w-24 rounded-md border-gray-300">
                            @error('rangeDays')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @else
                            <p class="mt-1 text-xs text-gray-500">
                                Examples: <code>--days={{ $rangeDays ?? 1 }}</code> for both <code>notices:import-polaris</code> and <code>notices:import-ftp-files</code>.
                            </p>
                            @enderror
                        </div>
                    </div>
                @elseif($rangeMode === 'date')
                    <div class="flex items-end gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" wire:model="rangeDate" class="form-input rounded-md border-gray-300">
                            @error('rangeDate')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @elseif($rangeMode === 'range')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start date</label>
                            <input type="date" wire:model="rangeStart" class="form-input w-full rounded-md border-gray-300">
                            @error('rangeStart')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">End date</label>
                            <input type="date" wire:model="rangeEnd" class="form-input w-full rounded-md border-gray-300">
                            @error('rangeEnd')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif
            @else
                <p class="mt-2 text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded px-3 py-2">
                    Warning: importing all history can take a long time on large datasets.
                </p>
            @endif
        </div>
        @endif

        <!-- Import Options -->
        <div class="mb-6 p-4 bg-gray-50 rounded-md">
            <h3 class="text-lg font-semibold mb-3">FTP Import Options</h3>
            
            <!-- Patron Import Toggle -->
            <div class="flex items-center justify-between mb-2">
                <div>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="importPatrons" class="form-checkbox h-5 w-5 text-blue-600" wire:loading.attr="disabled">
                        <span class="ml-3 text-sm font-medium text-gray-700">Import Patron Delivery Preferences</span>
                    </label>
                    <p class="ml-8 text-xs text-gray-500 mt-1">
                        Import voice_patrons and text_patrons files to track patron delivery method changes
                    </p>
                </div>
                @if($importPatrons)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Enabled
                </span>
                @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    Disabled
                </span>
                @endif
            </div>

            @if($importPatrons)
            <div class="ml-8 mt-2 p-3 bg-blue-50 rounded-md">
                <p class="text-xs text-blue-700">
                    <strong>Note:</strong> Previously processed patron files will be automatically skipped to prevent duplicate imports.
                </p>
            </div>
            @endif
        </div>

        <!-- Import Button -->
        <div class="mb-4 flex flex-col gap-3">
            @if($isImporting)
            <button wire:click="cancelImport"
                    wire:loading.attr="disabled"
                    class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition duration-150">
                <span wire:loading.remove>Cancel Import</span>
                <span wire:loading>Cancelling...</span>
            </button>
            @else
            <button wire:click="startImport"
                    wire:loading.attr="disabled"
                    class="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold py-3 px-4 rounded-lg transition duration-150">
                <span wire:loading.remove>Start Import</span>
                <span wire:loading>Starting...</span>
            </button>
            @endif
        </div>

        <!-- Log Viewer Modal -->
        @if($showLogModal && ($isImporting || !empty($progress)))
            <div class="fixed inset-0 z-40 flex items-center justify-center bg-black bg-opacity-40">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[80vh] flex flex-col">
                    <div class="flex items-center justify-between px-4 py-3 border-b">
                        <div>
                            <h3 class="text-lg font-semibold">Import Progress</h3>
                            @if($currentFile)
                                <p class="mt-1 text-xs text-gray-600">
                                    Currently importing: <span class="font-mono text-blue-700">{{ $currentFile }}</span>
                                </p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if($isImporting)
                                <button type="button" wire:click="cancelImport" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-red-500 text-red-600 hover:bg-red-50">
                                    Cancel
                                </button>
                            @endif
                            <button type="button" wire:click="closeLogModal" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded border border-gray-300 text-gray-700 hover:bg-gray-50">
                                Minimize
                            </button>
                        </div>
                    </div>

                    <div class="px-4 py-3 flex-1 overflow-hidden">
                        <div class="max-h-[55vh] overflow-y-auto bg-gray-900 text-green-400 p-4 rounded font-mono text-xs" id="progress-log">
                            @forelse($progress as $line)
                                <div>{{ $line }}</div>
                            @empty
                                <div class="text-gray-500">Waiting for output...</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Import Stats -->
        @if(!empty($importStats))
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($importStats as $label => $value)
            <div class="p-4 bg-white border border-gray-200 rounded-lg">
                <div class="text-2xl font-bold text-blue-600">{{ $value }}</div>
                <div class="text-xs text-gray-600 mt-1">{{ $label }}</div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50"></div>
</div>

@push('scripts')
<script>
    // Auto-scroll progress log
    document.addEventListener('livewire:load', function() {
        Livewire.on('updateProgress', () => {
            const log = document.getElementById('progress-log');
            if (log) {
                log.scrollTop = log.scrollHeight;
            }
        });
    });

    // Toast notification system (Livewire v3 events)
    document.addEventListener('livewire:load', () => {
        Livewire.on('show-toast', ({ type, message, duration = 3000 }) => {
        const toast = document.createElement('div');
        toast.className = `mb-4 p-4 rounded-lg shadow-lg transform transition-all duration-300 ${
            type === 'success' ? 'bg-green-500' :
            type === 'error' ? 'bg-red-500' :
            type === 'warning' ? 'bg-yellow-500' :
            'bg-blue-500'
        } text-white`;
        toast.textContent = message;
        
        document.getElementById('toast-container').appendChild(toast);

        setTimeout(() => toast.classList.add('opacity-0'), duration);
        setTimeout(() => toast.remove(), duration + 300);
        });
    });

    // Polaris import (JSON-only)
    Livewire.on('startPolarisImport', (options) => {
        const payload = options || {};

        fetch('/notices/sync/polaris', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
            .then(response => response.json())
            .then(data => {
                @this.call('updateProgress', {
                    progress: data.message || `Polaris import completed (${data.records ?? 0} records)`,
                    stats: data.records != null ? { records: data.records } : {},
                    completed: true,
                    success: data.status === 'success',
                    message: data.message || 'Polaris import finished',
                });
            })
            .catch(error => {
                console.error('Polaris import error:', error);
                @this.call('updateProgress', {
                    completed: true,
                    success: false,
                    message: 'Polaris import failed: ' + error.message,
                });
            });
    });

    // Command output streaming
    let importProcess = null;

    Livewire.on('startImportStream', (data) => {
        const command = data.command;

        // Call backend to start streaming process
        // Use relative URL to avoid SSL/port issues with proxies
        fetch('/notices/sync/ftp-files/stream', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ command })
        })
        .then(response => {
            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            function readStream() {
                reader.read().then(({ done, value }) => {
                    if (done) {
                        @this.call('updateProgress', {
                            completed: true,
                            success: true,
                            message: 'Import completed successfully!'
                        })
                        return;
                    }

                    const chunk = decoder.decode(value, { stream: true });
                    const lines = chunk.split('\n').filter(line => line.trim());

                    lines.forEach(line => {
                        try {
                            const data = JSON.parse(line);
                            @this.call('updateProgress', data)
                        } catch (e) {
                            // Plain text output
                            @this.call('updateProgress', { progress: line })
                        }
                    });

                    readStream();
                });
            }

            readStream();
        })
        .catch(error => {
            console.error('Import stream error:', error);
            @this.call('updateProgress', {
                completed: true,
                success: false,
                message: 'Import failed: ' + error.message
            })
        });
    });

    Livewire.on('cancelImport', () => {
        // Call backend to cancel process (best-effort)
        // Use relative URL to avoid SSL/port issues with proxies
        fetch('/notices/sync/ftp-files/cancel', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
    });
</script>
@endpush
