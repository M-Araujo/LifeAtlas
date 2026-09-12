<div>
    <header class="mb-6">
        <p class="text-sm font-medium text-emerald-700">
            Personal dashboard
        </p>

        <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
            History
        </h1>

        <p class="mt-1 text-sm text-slate-500">
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
                    {{ $events->count() }} entries
                </span>
            </div>

            {{-- Search --}}
            <div class="mt-4 grid gap-3 md:grid-cols-[1fr_200px]">
                <input
                    type="search"
                    wire:model.live="search"
                    placeholder="Search notes..."
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm"
                >

                <select
                    wire:model.live="searchLifeArea"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm"
                >
                    <option value="">All categories</option>

                    @foreach ($lifeAreas as $lifeArea)
                        <option value="{{ $lifeArea->id }}">
                            {{ $lifeArea->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
            @forelse ($events as $event)
                <div class="grid gap-2 px-5 py-4 md:grid-cols-[120px_160px_1fr]">
                    <span class="text-sm text-slate-400">
                        {{ $event->created_at->format('d M Y') }}
                    </span>

                    <span class="text-sm font-medium text-emerald-800">
                        {{ $event->lifeArea->name }}
                    </span>

                    <span class="text-sm text-slate-600">
                        {{ $event->description }}
                    </span>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-400">
                    No entries found.
                </div>
            @endforelse
        </div>
    </section>
</div>
