@props(['areas'])

@if (count($areas) === 0)
    <p class="mt-5 text-sm text-slate-600">No Life Areas available.</p>
@else
    @php
        // Stable axis positions even when the count ranking changes.
        $axes = collect($areas)->sortBy('id')->values();
        $radius = max(150, $axes->count() * 18);
        $size = ($radius + 150) * 2;
        $center = $size / 2;
        $maximum = max(1, $axes->max('entries'));
        $step = max(1, (int) ceil($maximum / 4));
        $scale = $step * 4;
        $points = $axes->map(function ($area, $index) use ($axes, $radius, $center, $scale) {
            $angle = -M_PI / 2 + $index * 2 * M_PI / $axes->count();
            $distance = $radius * $area['entries'] / $scale;
            return array_merge($area, [
                'x' => $center + cos($angle) * $distance,
                'y' => $center + sin($angle) * $distance,
                'edgeX' => $center + cos($angle) * $radius,
                'edgeY' => $center + sin($angle) * $radius,
                'labelX' => $center + cos($angle) * ($radius + 28),
                'labelY' => $center + sin($angle) * ($radius + 28),
                'anchor' => abs(cos($angle)) < 0.15 ? 'middle' : (cos($angle) > 0 ? 'start' : 'end'),
                'lines' => explode("\n", wordwrap($area['name'], 14, "\n", true)),
            ]);
        });
    @endphp
    <div class="mt-4" x-data="{ tooltip: '' }" wire:key="radar-{{ md5(json_encode($areas)) }}">
        <div class="overflow-x-auto">
            <svg viewBox="0 0 {{ $size }} {{ $size }}" class="mx-auto block w-full"
                style="max-width: {{ $size }}px; min-width: {{ min($size, max(320, $axes->count() * 42)) }}px"
                role="group" aria-label="Life Area radar chart. Distance from the center represents positive-entry count.">
                @for ($ring = 1; $ring <= 4; $ring++)
                    @if ($axes->count() >= 3)
                        <polygon points="{{ $points->map(fn ($point) => ($center + ($point['edgeX'] - $center) * $ring / 4).','.($center + ($point['edgeY'] - $center) * $ring / 4))->implode(' ') }}"
                            fill="none" stroke="#cbd5e1" />
                    @else
                        <circle cx="{{ $center }}" cy="{{ $center }}" r="{{ $radius * $ring / 4 }}" fill="none" stroke="#cbd5e1" />
                    @endif
                    <text x="{{ $center + 7 }}" y="{{ $center - $radius * $ring / 4 + 5 }}" font-size="12" fill="#475569">{{ $step * $ring }}</text>
                @endfor
                <text x="{{ $center + 7 }}" y="{{ $center + 14 }}" font-size="12" fill="#475569">0</text>
                @foreach ($points as $point)
                    @php($description = $point['name'].': '.$point['entries'].' positive '.($point['entries'] === 1 ? 'entry' : 'entries'))
                    <line x1="{{ $center }}" y1="{{ $center }}" x2="{{ $point['edgeX'] }}" y2="{{ $point['edgeY'] }}" stroke="#cbd5e1" />
                    <text x="{{ $point['labelX'] }}" y="{{ $point['labelY'] }}" text-anchor="{{ $point['anchor'] }}" font-size="14" fill="#334155"
                        tabindex="0" role="button" aria-label="{{ $description }}" class="cursor-pointer"
                        x-on:mouseenter="tooltip = @js($description)" x-on:focus="tooltip = @js($description)"
                        x-on:click="tooltip = @js($description)" x-on:keydown.enter.prevent="tooltip = @js($description)"
                        x-on:keydown.space.prevent="tooltip = @js($description)" x-on:keydown.escape="tooltip = ''">
                        <title>{{ $description }}</title>
                        @foreach ($point['lines'] as $line)
                            <tspan x="{{ $point['labelX'] }}" dy="{{ $loop->first ? -((count($point['lines']) - 1) * 8) : 16 }}">{{ $line }}</tspan>
                        @endforeach
                    </text>
                @endforeach
                @if ($axes->count() >= 3)
                    <polygon points="{{ $points->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ') }}"
                        fill="#10b981" fill-opacity="0.18" stroke="#047857" stroke-width="2" />
                @else
                    <polyline points="{{ $center }},{{ $center }} {{ $points->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ') }}"
                        fill="none" stroke="#047857" stroke-width="2" />
                @endif
                @foreach ($points as $point)
                    @php($description = $point['name'].': '.$point['entries'].' positive '.($point['entries'] === 1 ? 'entry' : 'entries'))
                    <g tabindex="0" role="button" aria-label="{{ $description }}" class="cursor-pointer"
                        x-on:mouseenter="tooltip = @js($description)"
                        x-on:focus="tooltip = @js($description)"
                        x-on:click="tooltip = @js($description)"
                        x-on:keydown.enter.prevent="tooltip = @js($description)"
                        x-on:keydown.space.prevent="tooltip = @js($description)"
                        x-on:keydown.escape="tooltip = ''">
                        <title>{{ $description }}</title>
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="16" fill="transparent" />
                        <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="5" fill="#047857" stroke="white" stroke-width="2" />
                    </g>
                @endforeach
            </svg>
        </div>
        <p role="status" class="min-h-6 text-center text-sm font-medium text-emerald-800"
            x-text="tooltip || 'Counts use the same scale on every axis.'">Counts use the same scale on every axis.</p>
        @if ($axes->sum('entries') === 0)
            <p class="mt-2 text-center text-sm text-slate-600">No positive entries recorded in this period. All Life Areas have value 0.</p>
        @endif
        @if ($axes->count() < 3)
            <p class="mt-2 text-center text-xs text-slate-500">Fewer than three Life Areas are available; points are shown without a filled shape.</p>
        @endif
    </div>
@endif
