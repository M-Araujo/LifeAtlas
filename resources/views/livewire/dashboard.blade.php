

<div>
<header class="mb-6">
    <p class="text-sm font-medium text-emerald-700">
        Personal dashboard
    </p>

    <h1 class="text-3xl font-semibold tracking-tight text-slate-900">
        Life check-ins
    </h1>

    <p class="mt-1 text-sm text-slate-500">
        Track an area, search your history, and see the balance.
    </p>
</header>

{{-- Add Entry --}}
<section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
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
                rows="3"
                placeholder="What shaped this moment?"
                class="w-full resize-none rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm"
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

<x-modal name="edit-entry" :show="false" maxWidth="2xl">
    <div class="p-6">
        <h2 class="text-lg font-semibold text-slate-800">
            Edit entry
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Update this life check-in.
        </p>

        <form wire:submit="updateEvent" class="mt-6 space-y-4">
            {{-- Life area --}}
            <div>
                <label class="block text-sm font-medium text-slate-700">
                    Life area
                </label>

                <select
                    wire:model="life_area_id"
                    class="mt-1 block w-full rounded-md border-slate-300"
                >
                    <option value="">Select a life area</option>

                    @foreach ($lifeAreas as $lifeArea)
                        <option value="{{ $lifeArea->id }}">
                            {{ $lifeArea->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Type --}}
            <div>
                <label class="block text-sm font-medium text-slate-700">
                    Type
                </label>

                <select
                    wire:model="type"
                    class="mt-1 block w-full rounded-md border-slate-300"
                >
                    <option value="positive">Positive</option>
                    <option value="neutral">Neutral</option>
                    <option value="negative">Negative</option>
                </select>
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-slate-700">
                    Description
                </label>

                <textarea
                    wire:model="description"
                    rows="4"
                    class="mt-1 block w-full rounded-md border-slate-300"
                ></textarea>
            </div>

            {{-- Buttons --}}
            <div class="flex justify-end gap-3 pt-4">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', 'edit-entry')"
                    class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800"
                >
                    Update
                </button>
            </div>
        </form>
    </div>
</x-modal>

<x-modal name="delete-entry" :show="false" maxWidth="md">
    <div class="p-6">
        <h2 class="text-lg font-semibold text-slate-800">
            Delete entry
        </h2>

        <p class="mt-2 text-sm text-slate-500">
            Are you sure you want to delete this entry {{ $this->deletingEventDescription }}? This action cannot be undone.
        </p>

        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                x-on:click="$dispatch('close-modal', 'delete-entry')"
                class="rounded-md px-4 py-2 text-sm text-slate-600 hover:text-slate-900"
            >
                Cancel
            </button>

            <button
                type="button"
                wire:click="deleteEvent"
                class="rounded-md bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800"
            >
                Delete
            </button>
        </div>
    </div>
</x-modal>
</div>
