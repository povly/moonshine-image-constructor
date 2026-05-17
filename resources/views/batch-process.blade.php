<div x-data="imageEditorBatch()" class="ie-batch">
    <h2>{{ __('image-editor::image-editor.batch_title') }}</h2>

    <div class="ie-batch-box">
        <div style="margin-bottom:16px;">
            <label class="ie-batch-label">{{ __('image-editor::image-editor.filter_label') }}</label>
            <select x-model="filter" class="ie-batch-select">
                <option value="all">{{ __('image-editor::image-editor.filter_all') }}</option>
                <option value="only_without_conversions">{{ __('image-editor::image-editor.filter_without_conversions') }}</option>
            </select>
        </div>
        <div class="ie-batch-row">
            <button @click="scanFiles()" :disabled="scanning" class="ie-batch-btn ie-batch-btn--scan">
                <svg x-show="scanning" class="ie-batch-spin" viewBox="0 0 24 24" fill="none">
                    <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                {{ __('image-editor::image-editor.scan_files') }}
            </button>
            <span x-show="fileCount !== null" class="ie-batch-count"
                  x-text="'{{ __('image-editor::image-editor.found_files') }}'.replace(':count', fileCount)"></span>
        </div>
    </div>

    <div x-show="files.length > 0" class="ie-batch-box">
        <button @click="startBatch()" :disabled="processing" class="ie-batch-btn ie-batch-btn--start">
            <svg x-show="processing" class="ie-batch-spin" viewBox="0 0 24 24" fill="none">
                <circle style="opacity:0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path style="opacity:0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ __('image-editor::image-editor.start_processing') }}
        </button>
    </div>

    <div x-show="batchId" x-cloak class="ie-batch-box">
        <div class="ie-batch-progress-head">
            <span class="ie-batch-progress-label"
                  x-text="`{{ __('image-editor::image-editor.progress_label') }}`.replace(':processed', progress.processed).replace(':total', progress.total)"></span>
            <span class="ie-batch-progress-pct" x-text="progress.progress + '%'"></span>
        </div>
        <div class="ie-batch-track">
            <div class="ie-batch-fill"
                 :class="progress.finished ? 'ie-batch-fill--done' : 'ie-batch-fill--active'"
                 :style="`width:${Math.max(progress.progress, 2)}%`"></div>
        </div>
        <div class="ie-batch-status">
            <span x-show="progress.finished" class="ie-batch-complete">
                {{ __('image-editor::image-editor.batch_complete') }}
            </span>
            <span x-show="progress.failed > 0" class="ie-batch-failed"
                  x-text="`{{ __('image-editor::image-editor.failed_count') }}`.replace(':count', progress.failed)"></span>
        </div>
    </div>

    <div x-show="batchId" x-cloak class="ie-batch-box">
        <div class="ie-batch-log-head">
            <span class="ie-batch-log-title">{{ __('image-editor::image-editor.log_title') }}</span>
            <button @click="clearLog()" class="ie-batch-log-clear">
                {{ __('image-editor::image-editor.clear_log') }}
            </button>
        </div>
        <div x-ref="logContainer" class="ie-batch-log">
            <template x-for="(entry, index) in progress.logs" :key="index">
                <div class="ie-batch-entry"
                     :class="`ie-batch-entry--${entry.type}`">
                    <span class="ie-batch-time" x-text="entry.time"></span>
                    <span class="ie-batch-badge"
                          :class="`ie-batch-badge--${entry.type}`"
                          x-text="entry.type.toUpperCase()"></span>
                    <span x-text="entry.message"></span>
                </div>
            </template>
            <div x-show="progress.logs.length === 0" class="ie-batch-empty">
                {{ __('image-editor::image-editor.no_logs') }}
            </div>
        </div>
    </div>
</div>

<script>
function imageEditorBatch() {
    return {
        files: [],
        fileCount: null,
        scanning: false,
        processing: false,
        batchId: null,
        filter: 'all',
        progress: {
            total: 0,
            processed: 0,
            progress: 0,
            finished: false,
            failed: 0,
            logs: [],
        },
        pollInterval: null,

        scanFiles() {
            this.scanning = true;
            this.fileCount = null;

            fetch(`{{ $scanUrl }}?filter=${this.filter}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            })
            .then(r => r.json())
            .then(data => {
                this.files = data.files || [];
                this.fileCount = data.count || 0;
            })
            .finally(() => { this.scanning = false; });
        },

        startBatch() {
            this.processing = true;

            fetch('{{ $startUrl }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ files: this.files }),
            })
            .then(r => r.json())
            .then(data => {
                if (data.status) {
                    this.batchId = data.batch_id;
                    this.progress.total = data.total;
                    this.startPolling();
                }
            })
            .finally(() => { this.processing = false; });
        },

        startPolling() {
            if (this.pollInterval) clearInterval(this.pollInterval);

            this.pollInterval = setInterval(() => {
                this.fetchProgress();

                if (this.progress.finished) {
                    clearInterval(this.pollInterval);
                    this.pollInterval = null;
                }
            }, 1500);
        },

        fetchProgress() {
            if (!this.batchId) return;

            fetch(`{{ $progressUrl }}?batch_id=${this.batchId}`, {
                headers: { 'Accept': 'application/json' },
            })
            .then(r => r.json())
            .then(data => {
                this.progress = data;
                this.$nextTick(() => {
                    const container = this.$refs.logContainer;
                    if (container) container.scrollTop = 0;
                });
            });
        },

        clearLog() {
            if (!this.batchId) return;

            fetch('{{ $clearLogUrl }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                body: JSON.stringify({ batch_id: this.batchId }),
            })
            .then(() => {
                this.progress.logs = [];
            });
        },
    };
}
</script>
