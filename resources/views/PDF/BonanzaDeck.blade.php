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

    $renderAction = function ($action) use ($cleanText): string {
        $type = ucfirst($action->type ?? 'melee');
        $rg = $action->range !== null ? $action->range . '"' : '-';
        $stat = $action->stat !== null ? $action->stat . ($action->stat_suits ?? '') : '-';
        $rst = $action->resisted_by ?: '-';
        $tn = $action->target_number !== null ? $action->target_number . ($action->target_suits ?? '') : '-';
        $dmg = ($action->damage !== null && $action->damage !== '') ? $action->damage : '-';
        $stone = $action->stone_cost ? str_repeat('◆', $action->stone_cost) . ' ' : '';

        $h = '<table style="width:100%;border-collapse:collapse;margin:1pt 0;">';
        // Header row
        $h .= '<tr style="background:#f3f4f6;font-size:4pt;font-weight:bold;text-align:center;">';
        $h .= '<td style="text-align:left;padding:0 2pt;width:45%;">' . e($type) . ' Action</td>';
        $h .= '<td style="width:11%;padding:0 1pt;">Rg</td>';
        $h .= '<td style="width:11%;padding:0 1pt;">Stat</td>';
        $h .= '<td style="width:11%;padding:0 1pt;">Rst</td>';
        $h .= '<td style="width:11%;padding:0 1pt;">TN</td>';
        $h .= '<td style="width:11%;padding:0 1pt;">Dmg</td>';
        $h .= '</tr>';
        // Values row
        $h .= '<tr style="font-size:4.5pt;text-align:center;border-bottom:0.5pt solid #ddd;">';
        $h .= '<td style="text-align:left;padding:1pt 2pt;font-weight:bold;">' . e($stone . $action->name) . '</td>';
        $h .= '<td style="padding:1pt;">' . e($rg) . '</td>';
        $h .= '<td style="padding:1pt;">' . e($stat) . '</td>';
        $h .= '<td style="padding:1pt;">' . e($rst) . '</td>';
        $h .= '<td style="padding:1pt;">' . e($tn) . '</td>';
        $h .= '<td style="padding:1pt;">' . e($dmg) . '</td>';
        $h .= '</tr>';
        $h .= '</table>';

        // Description
        if ($action->description) {
            $h .= '<div style="padding:0 2pt;font-size:4.5pt;">' . e($cleanText($action->description)) . '</div>';
        }
        // Triggers
        foreach ($action->triggers as $trigger) {
            $suits = $trigger->suits ? ' (' . e($trigger->suits) . ')' : '';
            $h .= '<div style="padding:0 2pt 0 6pt;font-size:4pt;"><b>' . e($trigger->name) . '</b>' . $suits . ': ' . e($cleanText($trigger->description)) . '</div>';
        }

        return $h;
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

        .card-hdr {
            padding: 1.5pt 4pt;
            font-size: 5.5pt;
            font-weight: bold;
            border-bottom: 0.75pt solid #ccc;
            white-space: nowrap;
            overflow: hidden;
        }
        .suit-sym { font-size: 7pt; margin-right: 1pt; }

        .side { padding: 2pt 3pt 1pt; }
        .side-lbl {
            display: inline-block;
            font-size: 4.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            padding: 0 2pt;
            border-radius: 1pt;
            margin-right: 2pt;
        }
        .side-title { font-weight: bold; font-size: 5pt; }
        .effect { margin-top: 1pt; white-space: pre-line; }

        .divider {
            text-align: center;
            padding: 1pt 0;
            font-size: 5pt;
            font-weight: bold;
            border-top: 0.75pt dashed #ccc;
            border-bottom: 0.75pt dashed #ccc;
        }

        .entity {
            margin: 1pt 0;
            padding-left: 3pt;
            border-left: 1pt solid #ccc;
            font-size: 4.5pt;
        }
        .en-name { font-weight: bold; }

        /* Side B: reversed text (physical card orientation) */
        .side-b { direction: ltr; }
        .side-b-rotate {
            transform: rotate(180deg);
            transform-origin: center center;
        }
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
            @endphp
            <div class="card" style="border-color: {{ $colors['border'] }}">
                {{-- ═══ Header ═══ --}}
                <div class="card-hdr" style="background: {{ $colors['bg'] }}; border-color: {{ $colors['border'] }}40">
                    <span class="suit-sym" style="color: {{ $colors['border'] }}">{{ $symbol }}</span>
                    {{ $card->value_label }}
                    @if($card->name) — {{ $card->name }}@endif
                </div>

                {{-- ═══ Side A (right-side-up) ═══ --}}
                <div class="side">
                    <span class="side-lbl" style="background: {{ $colors['bg'] }}; color: {{ $colors['border'] }}">A</span>
                    @if($card->title_a)<span class="side-title">{{ $card->title_a }}</span>@endif

                    @if($card->effect_a)
                        <div class="effect">{{ $cleanText($card->effect_a) }}</div>
                    @endif

                    @foreach($card->sideAAbilities as $ability)
                        <div class="entity">
                            <span class="en-name">{{ $ability->name }}@if($ability->costs_stone) (Stone)@endif.</span>
                            @if($ability->description) {{ $cleanText($ability->description) }}@endif
                        </div>
                    @endforeach

                    @foreach($card->sideAActions as $action)
                        {!! $renderAction($action) !!}
                    @endforeach

                    @foreach($card->sideATriggers as $trigger)
                        <div class="entity">
                            <span class="en-name">{{ $trigger->name }}</span>@if($trigger->suits) ({{ $trigger->suits }})@endif: {{ $cleanText($trigger->description) }}
                        </div>
                    @endforeach
                </div>

                {{-- ═══ Divider ═══ --}}
                <div class="divider" style="background: {{ $colors['light'] }}; color: {{ $colors['border'] }}">
                    {{ $symbol }} {{ $card->value_label }} · {{ ucfirst($suit) }}
                </div>

                {{-- ═══ Side B (rotated 180° — physical card orientation) ═══ --}}
                <div class="side side-b-rotate">
                    <span class="side-lbl" style="background: {{ $colors['bg'] }}; color: {{ $colors['border'] }}">B</span>
                    @if($card->title_b)<span class="side-title">{{ $card->title_b }}</span>@endif

                    @if($card->effect_b)
                        <div class="effect">{{ $cleanText($card->effect_b) }}</div>
                    @endif

                    @foreach($card->sideBAbilities as $ability)
                        <div class="entity">
                            <span class="en-name">{{ $ability->name }}@if($ability->costs_stone) (Stone)@endif.</span>
                            @if($ability->description) {{ $cleanText($ability->description) }}@endif
                        </div>
                    @endforeach

                    @foreach($card->sideBActions as $action)
                        {!! $renderAction($action) !!}
                    @endforeach

                    @foreach($card->sideBTriggers as $trigger)
                        <div class="entity">
                            <span class="en-name">{{ $trigger->name }}</span>@if($trigger->suits) ({{ $trigger->suits }})@endif: {{ $cleanText($trigger->description) }}
                        </div>
                    @endforeach
                </div>

                {{-- ═══ Footer (rotated with Side B) ═══ --}}
                <div class="card-hdr side-b-rotate" style="background: {{ $colors['bg'] }}; border-bottom:none; border-top: 0.75pt solid {{ $colors['border'] }}40">
                    <span class="suit-sym" style="color: {{ $colors['border'] }}">{{ $symbol }}</span>
                    {{ $card->value_label }}
                    @if($card->name) — {{ $card->name }}@endif
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
