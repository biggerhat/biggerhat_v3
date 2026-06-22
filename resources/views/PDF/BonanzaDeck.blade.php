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

        $h = '<table style="width:100%;border-collapse:collapse;margin:1pt 0;border:0.5pt solid #ddd;">';
        $h .= '<tr style="background:#f3f4f6;font-size:4pt;font-weight:bold;text-align:center;">';
        $h .= '<td style="text-align:left;padding:0 2pt;width:45%;">' . e($type) . ' Action</td>';
        $h .= '<td style="width:11%;">Rg</td><td style="width:11%;">Stat</td><td style="width:11%;">Rst</td><td style="width:11%;">TN</td><td style="width:11%;">Dmg</td>';
        $h .= '</tr><tr style="font-size:4.5pt;text-align:center;">';
        $h .= '<td style="text-align:left;padding:1pt 2pt;font-weight:bold;">' . e($stone . $action->name) . '</td>';
        $h .= '<td>' . e($rg) . '</td><td>' . e($stat) . '</td><td>' . e($rst) . '</td><td>' . e($tn) . '</td><td>' . e($dmg) . '</td>';
        $h .= '</tr></table>';

        if ($action->description) {
            $h .= '<div style="padding:0 2pt;font-size:4.5pt;">' . e($cleanText($action->description)) . '</div>';
        }
        foreach ($action->triggers as $trigger) {
            $suits = $trigger->suits ? ' (' . e($trigger->suits) . ')' : '';
            $h .= '<div style="padding:0 2pt 0 6pt;font-size:4pt;"><b>' . e($trigger->name) . '</b>' . $suits . ': ' . e($cleanText($trigger->description)) . '</div>';
        }
        return $h;
    };

    $renderSide = function (string $letter, ?string $title, ?string $effect, $abilities, $actions, $triggers) use ($cleanText, $renderAction, $suitColors): string {
        $h = '';
        if ($title) {
            $h .= '<span style="font-weight:bold;font-size:5pt;">' . e($title) . '</span>';
        }
        if ($effect) {
            $h .= '<div style="margin-top:1pt;white-space:pre-line;font-size:4.5pt;">' . e($cleanText($effect)) . '</div>';
        }
        foreach ($abilities as $ability) {
            $desc = $ability->description ? ' ' . e($cleanText($ability->description)) : '';
            $stone = $ability->costs_stone ? ' (Stone)' : '';
            $h .= '<div style="margin:1pt 0;padding-left:3pt;border-left:1pt solid #ccc;font-size:4.5pt;"><b>' . e($ability->name) . $stone . '.</b>' . $desc . '</div>';
        }
        foreach ($actions as $action) {
            $h .= $renderAction($action);
        }
        foreach ($triggers as $trigger) {
            $suits = $trigger->suits ? ' (' . e($trigger->suits) . ')' : '';
            $h .= '<div style="margin:1pt 0;padding-left:3pt;border-left:1pt solid #ccc;font-size:4.5pt;"><b>' . e($trigger->name) . '</b>' . $suits . ': ' . e($cleanText($trigger->description)) . '</div>';
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
            overflow: hidden;
            text-align: left;
            margin: 0.03in;
        }
        .card-tbl {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            border: 1.5pt solid #999;
            border-radius: 4pt;
        }

        .hdr-cell {
            padding: 1.5pt 4pt;
            font-size: 5.5pt;
            font-weight: bold;
            white-space: nowrap;
            overflow: hidden;
            height: 14pt;
        }
        .suit-sym { font-size: 7pt; margin-right: 1pt; }

        .side-cell {
            padding: 2pt 3pt;
            vertical-align: top;
            font-size: 5pt;
        }
        .side-cell-b {
            padding: 2pt 3pt;
            vertical-align: bottom;
            font-size: 5pt;
        }
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
        .div-cell {
            text-align: center;
            padding: 1pt 0;
            font-size: 5pt;
            font-weight: bold;
            height: 12pt;
        }

        .side-b-content {
            transform: rotate(180deg);
            transform-origin: center center;
        }
        .footer-content {
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

                $sideAHtml = $renderSide('A', $card->title_a, $card->effect_a, $card->sideAAbilities, $card->sideAActions, $card->sideATriggers);
                $sideBHtml = $renderSide('B', $card->title_b, $card->effect_b, $card->sideBAbilities, $card->sideBActions, $card->sideBTriggers);

                $hdrContent = '<span class="suit-sym" style="color:' . $colors['border'] . '">' . $symbol . '</span> '
                    . e($card->value_label)
                    . ($card->name ? ' — ' . e($card->name) : '');
            @endphp
            <div class="card">
                <table class="card-tbl" style="border-color: {{ $colors['border'] }}">
                    {{-- Header --}}
                    <tr>
                        <td class="hdr-cell" style="background: {{ $colors['bg'] }}; border-bottom: 0.75pt solid {{ $colors['border'] }}40">
                            {!! $hdrContent !!}
                        </td>
                    </tr>
                    {{-- Side A — top aligned --}}
                    <tr>
                        <td class="side-cell">
                            <span class="side-lbl" style="background: {{ $colors['bg'] }}; color: {{ $colors['border'] }}">A</span>
                            {!! $sideAHtml !!}
                        </td>
                    </tr>
                    {{-- Divider --}}
                    <tr>
                        <td class="div-cell" style="background: {{ $colors['light'] }}; color: {{ $colors['border'] }}; border-top: 0.75pt dashed #ccc; border-bottom: 0.75pt dashed #ccc;">
                            {{ $symbol }} {{ $card->value_label }} · {{ ucfirst($suit) }}
                        </td>
                    </tr>
                    {{-- Side B — bottom aligned, rotated --}}
                    <tr>
                        <td class="side-cell-b">
                            <div class="side-b-content">
                                <span class="side-lbl" style="background: {{ $colors['bg'] }}; color: {{ $colors['border'] }}">B</span>
                                {!! $sideBHtml !!}
                            </div>
                        </td>
                    </tr>
                    {{-- Footer (rotated) --}}
                    <tr>
                        <td class="hdr-cell" style="background: {{ $colors['bg'] }}; border-top: 0.75pt solid {{ $colors['border'] }}40">
                            <div class="footer-content">{!! $hdrContent !!}</div>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
