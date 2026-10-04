<div>
    <header class="mb-6">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">Statistics</h1>
        <p class="mt-2 text-sm text-slate-600">Explore patterns in your recorded check-ins.</p>
    </header>

    <section aria-label="Date range" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <form wire:submit="applyRange" x-data class="flex flex-wrap items-start gap-4">
            <div class="w-full min-w-0 sm:w-48">
                <label for="stats-from" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">From</label>
                <input id="stats-from" type="date" wire:model="from" required
                    aria-describedby="stats-from-error" @error('from') aria-invalid="true" @enderror
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                <p id="stats-from-error" role="alert" class="mt-1 text-sm text-red-700">@error('from') {{ $message }} @enderror</p>
            </div>
            <div class="w-full min-w-0 sm:w-48">
                <label for="stats-to" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">To</label>
                <input id="stats-to" type="date" wire:model="to" required
                    aria-describedby="stats-to-error" @error('to') aria-invalid="true" @enderror
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                <p id="stats-to-error" role="alert" class="mt-1 text-sm text-red-700">@error('to') {{ $message }} @enderror</p>
            </div>
            <button type="submit" wire:loading.attr="disabled" wire:target="applyRange"
                class="w-full rounded-lg bg-emerald-800 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-900 disabled:opacity-50 sm:mt-6 sm:w-auto">
                Apply
            </button>
            <span x-cloak x-show="$wire.from !== $wire.appliedFrom || $wire.to !== $wire.appliedTo"
                role="status" class="w-full text-sm text-amber-800 sm:mt-9 sm:w-auto">Unapplied changes</span>
        </form>
        <p role="status" class="mt-3 text-sm font-medium text-slate-700">Showing {{ $periodLabel }}, inclusive</p>
        <p class="mt-1 text-xs text-slate-500">Europe/Lisbon · Based on the date each entry was recorded.</p>
        <p wire:loading wire:target="applyRange" role="status" class="mt-2 text-sm text-emerald-700">Updating statistics…</p>
    </section>

    <section aria-labelledby="overview-heading" class="mt-6">
        <h2 id="overview-heading" class="font-semibold text-slate-900">Period overview</h2>
        <div class="mt-3 grid gap-4 md:grid-cols-3">
            @foreach (['entries' => 'Entries', 'check_in_days' => 'Check-in days', 'life_areas' => 'Life Areas represented'] as $key => $label)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm text-slate-600">{{ $label }}</p>
                        <span class="relative h-5 w-5 shrink-0" aria-hidden="true">
                            <span class="absolute -inset-1.5 rounded-lg border border-slate-200 bg-slate-50"></span>
                            <svg class="relative h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                @if ($key === 'entries')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6h11M9 12h11M9 18h11M4 6h1M4 12h1M4 18h1" />
                                @elseif ($key === 'check_in_days')
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 3v4m8-4v4M4 10h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
                                @else
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z" />
                                @endif
                            </svg>
                        </span>
                    </div>
                    <p class="mt-2 text-3xl font-semibold text-slate-900">{{ number_format($overview[$key]) }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section aria-labelledby="type-heading" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="type-heading" class="font-semibold text-slate-900">Entry type distribution</h2>
            <p class="mt-1 text-sm text-slate-500">Count and share of entries in this period.</p>
            @if ($overview['entries'] === 0)
                <p class="mt-4 text-sm text-slate-600">No entries to calculate a distribution.</p>
            @endif
            <ul class="mt-5 space-y-5">
                @foreach ($distribution as $item)
                    <li>
                        <div class="flex flex-wrap justify-between gap-2 text-sm">
                            <span @class([
                                'inline-flex items-center gap-1.5 font-medium',
                                'text-emerald-700' => $item['type'] === 'positive',
                                'text-slate-700' => $item['type'] === 'neutral',
                                'text-red-700' => $item['type'] === 'negative',
                            ])>
                                <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    @if ($item['type'] === 'positive')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5V4.5m0 0l-6 6m6-6l6 6" />
                                    @elseif ($item['type'] === 'neutral')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15.75h7.5M8.25 9.75h7.5M3.75 6.75A2.25 2.25 0 016 4.5h12a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0118 19.5H6a2.25 2.25 0 01-2.25-2.25V6.75z" />
                                    @elseif ($item['type'] === 'negative')
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0l6-6m-6 6l-6-6" />
                                    @endif
                                </svg>
                                {{ ucfirst($item['type']) }}
                            </span>
                            <span class="text-slate-600">{{ number_format($item['entries']) }} {{ $item['entries'] === 1 ? 'entry' : 'entries' }} · {{ number_format($item['share'], 1) }}%</span>
                        </div>
                        <div aria-hidden="true" class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div @class(['h-full rounded-full', 'bg-emerald-600' => $item['type'] === 'positive', 'bg-slate-500' => $item['type'] === 'neutral', 'bg-red-600' => $item['type'] === 'negative']) style="width: {{ $item['share'] }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="areas-heading" class="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 id="areas-heading" class="font-semibold text-slate-900">Entries by Life Area</h2>
            <p class="mt-1 text-sm text-slate-500">Ranked by entry count. Share of all entries in this period.</p>
            @if ($overview['entries'] === 0)
                <p class="mt-4 text-sm text-slate-600">No entries recorded in this period.</p>
            @endif
            <ul class="mt-5 space-y-5">
                @forelse ($lifeAreas as $area)
                    <li>
                        <div class="flex flex-wrap justify-between gap-2 text-sm">
                            <span class="font-medium text-slate-700">{{ $area['name'] }}</span>
                            <span class="text-slate-600">{{ number_format($area['entries']) }} {{ $area['entries'] === 1 ? 'entry' : 'entries' }} · {{ number_format($area['share'], 1) }}%</span>
                        </div>
                        <div aria-hidden="true" class="mt-2 h-3 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-600" style="width: {{ $area['share'] }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="text-sm text-slate-600">No Life Areas available.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <section aria-labelledby="radar-heading" class="mt-6 min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 id="radar-heading" class="font-semibold text-slate-900">Positive entries by Life Area</h2>
        <p class="mt-1 text-sm text-slate-500">Positive-entry counts in this period · Hover, focus, or tap a point or label for its value.</p>
        <x-life-area-radar :areas="$radarAreas" />
    </section>
</div>
