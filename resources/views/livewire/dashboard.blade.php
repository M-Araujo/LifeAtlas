

<div>
<header class="mb-6">
    <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
        Life check-ins
    </h1>

    <p class="mt-2 text-sm text-slate-600">
        Track an area, search your history, and see the balance.
    </p>
</header>

<div class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] xl:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">
{{-- Add Entry --}}
<section class="min-w-0 self-start rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <h2 class="font-semibold text-slate-900">
            Add entry
        </h2>

        <p class="text-sm text-slate-500">
            Pick a category and jot a quick note.
        </p>
    </div>

    <form wire:submit="saveRecord">
        {{-- Category + Type --}}
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Category
                </label>

                <select
                    wire:model="life_area_id"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                >
                    <option value="">Select a category</option>
                    @foreach ($lifeAreas as $lifeArea)
                        <option value="{{ $lifeArea->id }}">
                            {{ $lifeArea->name }}
                        </option>
                    @endforeach
                </select>
                @error('life_area_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Type
                </label>

                <select
                    wire:model="type"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
                >
                    <option value="neutral">Neutral</option>
                    <option value="positive">Positive</option>
                    <option value="negative">Negative</option>
                </select>
                @error('type')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Note --}}
        <div class="mt-4">
            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-500">
                Note
            </label>

            <textarea
                wire:model="description"
                rows="6"
                placeholder="What shaped this moment?"
                class="w-full resize-y rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
            ></textarea>
            @error('description')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Save --}}
        <div class="mt-4 flex justify-end">
            <button
                type="submit"
                class="rounded-lg bg-emerald-800 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-900"
            >
                + Save
            </button>
        </div>
    </form>
</section>

{{-- This Week --}}
<section aria-labelledby="weekly-summary-heading" class="min-w-0 self-start rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5">
        <h2 id="weekly-summary-heading" class="font-semibold text-slate-900">This week</h2>
        <p class="text-sm text-slate-500">Your check-ins at a glance.</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
    <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <p class="text-sm text-slate-500">Positive entries</p>
            <svg class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5V4.5m0 0l-6 6m6-6l6 6" />
            </svg>
        </div>
        <p class="mt-2 text-3xl font-semibold text-emerald-700">{{ $weeklyStats['positive'] }}</p>
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <p class="text-sm text-slate-500">Neutral entries</p>
            <svg class="h-5 w-5 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15.75h7.5M8.25 9.75h7.5M3.75 6.75A2.25 2.25 0 016 4.5h12a2.25 2.25 0 012.25 2.25v10.5A2.25 2.25 0 0118 19.5H6a2.25 2.25 0 01-2.25-2.25V6.75z" />
            </svg>
        </div>
        <p class="mt-2 text-3xl font-semibold text-slate-700">{{ $weeklyStats['neutral'] }}</p>
    </div>

    <div class="rounded-xl border border-red-100 bg-red-50/50 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <p class="text-sm text-slate-500">Negative entries</p>
            <svg class="h-5 w-5 text-red-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0l6-6m-6 6l-6-6" />
            </svg>
        </div>
        <p class="mt-2 text-3xl font-semibold text-red-700">{{ $weeklyStats['negative'] }}</p>
    </div>

    <div class="rounded-xl border border-stone-200 bg-stone-50 p-5 shadow-sm">
        <div class="flex items-start justify-between">
            <p class="text-sm text-slate-500">Total entries</p>
            <svg class="h-5 w-5 text-slate-900" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m4-2a8 8 0 11-16 0 8 8 0 0116 0z" />
            </svg>
        </div>
        <p class="mt-2 text-3xl font-semibold text-slate-900">{{ $weeklyStats['total'] }}</p>
    </div>
    </div>
</section>
</div>

</div>
