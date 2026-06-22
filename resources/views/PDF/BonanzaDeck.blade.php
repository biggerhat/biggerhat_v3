@php
    $suitColors = [
        'crow'  => ['border' => '#16a34a', 'bg' => '#dcfce7', 'light' => '#f0fdf4'],
        'mask'  => ['border' => '#9333ea', 'bg' => '#f3e8ff', 'light' => '#faf5ff'],
        'ram'   => ['border' => '#dc2626', 'bg' => '#fee2e2', 'light' => '#fef2f2'],
        'tome'  => ['border' => '#2563eb', 'bg' => '#dbeafe', 'light' => '#eff6ff'],
        'joker' => ['border' => '#d97706', 'bg' => '#fef3c7', 'light' => '#fffbeb'],
    ];

    $suitSymbols = [
        'crow' => "\u{2666}", 'mask' => "\u{2663}",
        'ram'  => "\u{2665}", 'tome' => "\u{2660}", 'joker' => "\u{2605}",
    ];

    $cleanText = function (?string $text): string {
        if (! $text) return '';
        return trim(preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => '(' . ucfirst(strtolower($m[1])) . ')', $text));
    };

    $statLine = function ($action): string {
        $parts = [];
        if (! is_null($action->range)) $parts[] = 'Rng ' . $action->range . ($action->range_type ? ' ' . $action->range_type : '"');
        if (! is_null($action->stat)) $parts[] = 'Stat ' . $action->stat . ($action->stat_suits ?? '');
        if (! empty($action->resisted_by)) $parts[] = 'vs ' . $action->resisted_by;
        if (! is_null($action->target_number)) $parts[] = 'TN ' . $action->target_number . ($action->target_suits ?? '');
        if (! is_null($action->damage) && $action->damage !== '') $parts[] = 'Dmg ' . $action->damage;
        return implode(' · ', $parts);
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bonanza Loot Deck — Print</title>
    <style>
        @page { margin: 0.25in; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 5pt; line-height: 1.2; color: #111; }

        .page { page-break-after: always; text-align: center; }
        .page:last-child { page-break-after: auto; }

        .card {
            display: inline-block;
            vertical-align: top;
            width: 2.75in;
            height: 4.75in;
            border: 1.5pt solid #999;
            border-radius: 4pt;
            overflow: hidden;
            text-align: left;
            margin: 0.03in;
        }

        .card-header {
            padding: 1.5pt 4pt;
            font-size: 6pt;
            font-weight: bold;
            border-bottom: 0.75pt solid #ccc;
            white-space: nowrap;
            overflow: hidden;
        }
        .suit-sym { font-size: 7pt; margin-right: 1pt; }

        .side {
            padding: 2pt 4pt 1pt;
        }
        .side-lbl {
            display: inline-block;
            font-size: 5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            padding: 0 2pt;
            border-radius: 1pt;
            margin-right: 2pt;
        }
        .side-title { font-weight: bold; font-size: 5.5pt; }

        .effect { margin-top: 1pt; white-space: pre-line; }

        .divider {
            text-align: center;
            padding: 1pt 0;
            font-size: 5.5pt;
            font-weight: bold;
            border-top: 0.75pt dashed #ccc;
            border-bottom: 0.75pt dashed #ccc;
        }

        .entity {
            margin: 1pt 0;
            padding-left: 3pt;
            border-left: 1pt solid #ccc;
        }
        .en-name { font-weight: bold; }
        .en-stat { font-family: DejaVu Sans Mono, monospace; font-size: 4.5pt; color: #444; }
        .trig-line { padding-left: 5pt; font-size: 4.5pt; }
    </style>
</head>
<body>
@foreach($cards->chunk(4) as $pageCards)
    <div class="page">
        @foreach($pageCards as $card)
            @php
                $suit = strtolower($card->suit);
                $colors = $suitColors[$suit] ?? $suitColors['joker'];
                $symbol = $suitSymbols[$suit] ?? '?';
                $sides = [
                    ['letter' => 'A', 'title' => $card->title_a, 'effect' => $card->effect_a,
                     'abilities' => $card->sideAAbilities, 'actions' => $card->sideAActions, 'triggers' => $card->sideATriggers],
                    ['letter' => 'B', 'title' => $card->title_b, 'effect' => $card->effect_b,
                     'abilities' => $card->sideBAbilities, 'actions' => $card->sideBActions, 'triggers' => $card->sideBTriggers],
                ];
            @endphp
            <div class="card" style="border-color: {{ $colors['border'] }}">
                <div class="card-header" style="background: {{ $colors['bg'] }}; border-color: {{ $colors['border'] }}40">
                    <span class="suit-sym" style="color: {{ $colors['border'] }}">{{ $symbol }}</span>
                    {{ $card->value_label }}
                    @if($card->name) — {{ $card->name }}@endif
                </div>

                @foreach($sides as $idx => $side)
                    @if($idx === 1)
                        <div class="divider" style="background: {{ $colors['light'] }}; color: {{ $colors['border'] }}">
                            {{ $symbol }} {{ $card->value_label }} · {{ ucfirst($suit) }}
                        </div>
                    @endif

                    <div class="side">
                        <span class="side-lbl" style="background: {{ $colors['bg'] }}; color: {{ $colors['border'] }}">{{ $side['letter'] }}</span>
                        @if($side['title'])<span class="side-title">{{ $side['title'] }}</span>@endif

                        @if($side['effect'])
                            <div class="effect">{{ $cleanText($side['effect']) }}</div>
                        @endif

                        @foreach($side['abilities'] as $ability)
                            <div class="entity">
                                <span class="en-name">{{ $ability->name }}@if($ability->costs_stone) (Stone)@endif.</span>
                                @if($ability->description) {{ $cleanText($ability->description) }}@endif
                            </div>
                        @endforeach

                        @foreach($side['actions'] as $action)
                            <div class="entity">
                                <span class="en-name">{{ $action->name }}@if($action->stone_cost) [{{ $action->stone_cost }}ss]@endif</span>
                                @if($statLine($action))<span class="en-stat"> — {{ $statLine($action) }}</span>@endif
                                @if($action->description)<div>{{ $cleanText($action->description) }}</div>@endif
                                @foreach($action->triggers as $trigger)
                                    <div class="trig-line"><span class="en-name">{{ $trigger->name }}</span>@if($trigger->suits) ({{ $trigger->suits }})@endif: {{ $cleanText($trigger->description) }}</div>
                                @endforeach
                            </div>
                        @endforeach

                        @foreach($side['triggers'] as $trigger)
                            <div class="entity">
                                <span class="en-name">{{ $trigger->name }}</span>@if($trigger->suits) ({{ $trigger->suits }})@endif: {{ $cleanText($trigger->description) }}
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
