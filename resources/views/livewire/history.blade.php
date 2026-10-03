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

                <span class="text-xs text-slate-400">
                    {{ $events->total() }} entries
                </span>
            </div>

            {{-- Search --}}
            <div class="mt-4 grid gap-3 md:grid-cols-[1fr_180px_180px]">
                <input
                    type="search" aria-label="Search notes"
                    wire:model.live="search"
                    placeholder="Search notes..."
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm"
                >

                <select
                    wire:model.live="searchLifeArea" aria-label="Life area"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm"
                >
                    <option value="">All categories</option>

                    @foreach ($lifeAreas as $lifeArea)
                        <option value="{{ $lifeArea->id }}">
                            {{ $lifeArea->name }}
                        </option>
                    @endforeach
                </select>
                <select wire:model.live="dateRange" aria-label="Date range" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                    <option value="30_days">Last 30 days</option>
                    <option value="6_months">Last 6 months</option>
                    <option value="1_year">Last year</option>
                    <option value="all">All time</option>
                </select>
            </div>
        </div>

        @if ($message)
            <p role="status" class="px-5 py-3 text-sm text-emerald-800">{{ $message }}</p>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] table-fixed text-left text-sm">
                <caption class="sr-only">Life check-ins matching your filters</caption>
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th scope="col" class="w-28 px-3 py-2">Date</th>
                        <th scope="col" class="w-32 px-3 py-2">Life Area</th>
                        <th scope="col" class="w-24 px-3 py-2">Type</th>
                        <th scope="col" class="px-3 py-2">Description</th>
                        <th scope="col" class="w-28 px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($events as $event)
                        <tr wire:key="event-{{ $event->id }}">
                            <td class="whitespace-nowrap px-3 py-2 align-top text-slate-500">{{ $event->created_at->format('d M Y') }}</td>
                            <td class="px-3 py-2 align-top font-medium text-emerald-800">{{ $event->lifeArea->name }}</td>
                            <td class="px-3 py-2 align-top text-slate-600">{{ ucfirst($event->type) }}</td>
                            <td class="whitespace-pre-wrap [overflow-wrap:anywhere] px-3 py-2 align-top text-slate-600">{{ $event->description }}</td>
                            <td class="whitespace-nowrap px-3 py-2 align-top">
                                <button type="button" wire:click="editEvent({{ $event->id }})" wire:loading.attr="disabled" class="text-emerald-700 hover:underline disabled:opacity-50">Edit</button>
                                <button type="button" wire:click="confirmDelete({{ $event->id }})" wire:loading.attr="disabled" class="ml-3 text-red-700 hover:underline disabled:opacity-50">Delete</button>
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
                    <label for="edit-life-area" class="block text-sm font-medium text-slate-700">Life area</label>
                    <select id="edit-life-area" wire:model="life_area_id" class="mt-1 w-full rounded-md border-slate-300">
                        <option value="">Select a life area</option>
                        @foreach ($lifeAreas as $lifeArea)
                            <option value="{{ $lifeArea->id }}">{{ $lifeArea->name }}</option>
                        @endforeach
                    </select>
                    @error('life_area_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="edit-type" class="block text-sm font-medium text-slate-700">Type</label>
                    <select id="edit-type" wire:model="type" class="mt-1 w-full rounded-md border-slate-300">
                        <option value="positive">Positive</option>
                        <option value="neutral">Neutral</option>
                        <option value="negative">Negative</option>
                    </select>
                    @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="edit-description" class="block text-sm font-medium text-slate-700">Description</label>
                    <textarea id="edit-description" wire:model="description" rows="4" maxlength="10000" class="mt-1 w-full rounded-md border-slate-300"></textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" x-on:click="show = false" class="rounded-md px-4 py-2 text-slate-600">Cancel</button>
                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-emerald-700 px-4 py-2 text-white disabled:opacity-50">Save changes</button>
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
                <button type="button" x-on:click="show = false" class="rounded-md px-4 py-2 text-slate-600">Cancel</button>
                <button type="button" wire:click="deleteEvent" wire:loading.attr="disabled" class="rounded-md bg-red-700 px-4 py-2 text-white disabled:opacity-50">Delete entry</button>
            </div>
        </div>
    </x-modal>
</div>
