<div>
    <header class="mb-6">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
            History
        </h1>

        <p class="mt-2 text-sm text-slate-600">
            Search and manage your life check-ins.
        </p>
    </header>

    {{-- Results --}}
    <section class="mt-4 rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 p-5">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-slate-900">
                        Results
                    </h2>

                    <p class="text-sm text-slate-500">
                        Recent life check-ins
                    </p>
                </div>

                <span class="text-xs text-slate-500">
                    {{ $events->total() }} entries
                </span>
            </div>

            {{-- Search --}}
            <div class="mt-4 grid gap-3 md:grid-cols-[1fr_180px_180px]">
                <div>
                    <label for="history-search" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Search</label>
                    <input
                        id="history-search"
                        type="search" aria-label="Search notes"
                        wire:model.live="search"
                        placeholder="Search notes..."
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                    >
                </div>
                <div>
                    <label for="history-life-area" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Category</label>
                    <select
                        id="history-life-area"
                        wire:model.live="searchLifeArea" aria-label="Life area"
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                    >
                        <option value="">All categories</option>

                        @foreach ($lifeAreas as $lifeArea)
                            <option value="{{ $lifeArea->id }}">
                                {{ $lifeArea->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="history-date-range" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Date range</label>
                    <select id="history-date-range" wire:model.live="dateRange" aria-label="Date range" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                        <option value="30_days">Last 30 days</option>
                        <option value="6_months">Last 6 months</option>
                        <option value="1_year">Last year</option>
                        <option value="all">All time</option>
                    </select>
                </div>
            </div>
        </div>

        @if ($message)
            <p role="status" class="mx-5 my-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">{{ $message }}</p>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] table-fixed text-left text-sm">
                <caption class="sr-only">Life check-ins matching your filters</caption>
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="w-28 px-3 py-2">Date</th>
                        <th scope="col" class="w-32 px-3 py-2">Life Area</th>
                        <th scope="col" class="w-28 px-3 py-2">Type</th>
                        <th scope="col" class="px-3 py-2">Description</th>
                        <th scope="col" class="w-[108px] px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $event)
                        <tr wire:key="event-{{ $event->id }}">
                            <td class="whitespace-nowrap px-3 py-3 align-top text-slate-500">{{ $event->created_at->format('d M Y') }}</td>
                            <td class="px-3 py-3 align-top font-medium text-emerald-800">{{ $event->lifeArea->name }}</td>
                            <td class="px-3 py-3 align-top">
                                <span @class([
                                    'inline-flex items-center gap-1 rounded-md border px-1.5 py-0.5 text-xs font-medium',
                                    'border-emerald-100 bg-emerald-50/50 text-emerald-700' => $event->type === 'positive',
                                    'border-slate-200 bg-slate-50 text-slate-700' => $event->type === 'neutral',
                                    'border-red-100 bg-red-50/50 text-red-700' => $event->type === 'negative',
                                ])>
                                    <svg class="h-3 w-3 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        @if ($event->type === 'positive')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5V4.5m0 0l-6 6m6-6l6 6" />
                                        @elseif ($event->type === 'neutral')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15.75h7.5M8.25 9.75h7.5M3.75 6.75A2.25 2.25 0 016 4.5h12a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0118 19.5H6a2.25 2.25 0 01-2.25-2.25V6.75z" />
                                        @elseif ($event->type === 'negative')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0l6-6m-6 6l-6-6" />
                                        @endif
                                    </svg>
                                    {{ ucfirst($event->type) }}
                                </span>
                            </td>
                            <td class="whitespace-pre-wrap [overflow-wrap:anywhere] px-3 py-3 align-top leading-6 text-slate-700">{{ $event->description }}</td>
                            <td class="whitespace-nowrap px-3 py-3 align-top">
                                <div class="flex items-center gap-1">
                                    <button type="button" aria-label="Edit entry" title="Edit entry" wire:click="editEvent({{ $event->id }})" wire:loading.attr="disabled" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-emerald-700 hover:bg-emerald-50 hover:text-emerald-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.5 3.75 3.75 3.75M4.5 19.5l4.5-1.125L20.25 7.125a2.652 2.652 0 0 0-3.75-3.75L5.25 14.625 4.5 19.5Z" />
                                        </svg>
                                    </button>
                                    <button type="button" aria-label="Delete entry" title="Delete entry" wire:click="confirmDelete({{ $event->id }})" wire:loading.attr="disabled" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-red-700 hover:bg-red-50 hover:text-red-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M9 6.75v-3h6v3m-9.75 0 .75 13.5h12l.75-13.5M9.75 10.5v6m4.5-6v6" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-400">No entries found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-3 py-3">
            {{ $events->links(data: ['scrollTo' => false]) }}
        </div>
    </section>

    <x-modal name="edit-entry" maxWidth="2xl" focusable>
        <div class="p-6" x-init="$watch('show', value => { if (!value) $wire.cancelEdit() })" role="dialog" aria-modal="true" aria-labelledby="edit-entry-title">
            <h2 id="edit-entry-title" class="text-lg font-semibold text-slate-800">Edit entry</h2>
            <form wire:submit="updateEvent" class="mt-6 space-y-4">
                <div>
                    <label for="edit-life-area" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Life area</label>
                    <select id="edit-life-area" wire:model="life_area_id" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                        <option value="">Select a life area</option>
                        @foreach ($lifeAreas as $lifeArea)
                            <option value="{{ $lifeArea->id }}">{{ $lifeArea->name }}</option>
                        @endforeach
                    </select>
                    @error('life_area_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="edit-type" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Type</label>
                    <select id="edit-type" wire:model="type" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm">
                        <option value="positive">Positive</option>
                        <option value="neutral">Neutral</option>
                        <option value="negative">Negative</option>
                    </select>
                    @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="edit-description" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">Description</label>
                    <textarea id="edit-description" wire:model="description" rows="4" maxlength="10000" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"></textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="show = false" class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-lg bg-emerald-800 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">Save changes</button>
                </div>
            </form>
        </div>
    </x-modal>

    <x-modal name="delete-entry" maxWidth="md" focusable>
        <div class="p-6" x-init="$watch('show', value => { if (!value) $wire.cancelDelete() })" role="dialog" aria-modal="true" aria-labelledby="delete-entry-title">
            <h2 id="delete-entry-title" class="text-lg font-semibold text-slate-800">Delete entry?</h2>
            <p class="mt-2 text-sm text-slate-600">{{ $deletingEventDate }} · {{ $deletingEventLifeArea }}</p>
            <p class="mt-2 whitespace-pre-wrap [overflow-wrap:anywhere] text-sm text-slate-600">{{ $deletingEventDescription }}</p>
            <p class="mt-3 text-sm text-slate-600">This action cannot be undone.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" x-on:click="show = false" class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 focus-visible:ring-offset-2">Cancel</button>
                <button type="button" wire:click="deleteEvent" wire:loading.attr="disabled" class="rounded-lg bg-red-700 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-700 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">Delete entry</button>
            </div>
        </div>
    </x-modal>
</div>
