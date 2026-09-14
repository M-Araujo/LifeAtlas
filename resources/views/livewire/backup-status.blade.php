@if ($backup)
    <div
        @if ($backup->isActive()) wire:poll.2s="$refresh" @endif
        class="mb-6 rounded-xl border p-4 shadow-sm {{ $backup->isActive() ? 'border-emerald-200 bg-emerald-50' : ($backup->status === \App\Models\Backup::STATUS_COMPLETED ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50') }}"
    >
        <div class="flex items-center gap-3">
            @if ($backup->isActive())
                <svg class="h-5 w-5 animate-spin text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" d="M12 3a9 9 0 109 9" />
                </svg>
                <p class="text-sm font-medium text-emerald-800">
                    Backing up… Please don't close LifeAtlas.
                </p>
            @elseif ($backup->status === \App\Models\Backup::STATUS_COMPLETED)
                <svg class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium text-emerald-800">
                    Backup completed.
                </p>
            @else
                <svg class="h-5 w-5 text-red-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3h.008v.008H12v-.008zM10.29 3.86L2.82 16.5a2.25 2.25 0 001.94 3.39h14.48a2.25 2.25 0 001.94-3.39L13.71 3.86a2.25 2.25 0 00-3.42 0z" />
                </svg>
                <div>
                    <p class="text-sm font-medium text-red-800">Backup failed.</p>
                    <p class="text-xs text-red-700">{{ $backup->error_message }}</p>
                </div>
            @endif
        </div>
    </div>
@endif
