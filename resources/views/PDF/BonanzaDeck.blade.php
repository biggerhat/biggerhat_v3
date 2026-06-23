@php
    // Embed the M4E symbol font so Chrome renders the exact suit/range/stone
    // glyphs the on-screen card uses.
    $fontPath = public_path('font/M4E-Symbols.otf');
    $fontB64 = is_file($fontPath) ? base64_encode(file_get_contents($fontPath)) : '';

    // Token/type → M4E glyph. Merges GameIcon's type keys (used by ability
    // suits / defensive_ability_type / action stat suits) with GameText's
    // {{token}} aliases (fortitude → physical_defense glyph, etc.) so card
    // text renders the same icons the web does.
    $glyphs = [
        // Suits
        'crow' => 'c', 'crows' => 'c', 'mask' => 'm', 'masks' => 'm',
        'ram' => 'r', 'rams' => 'r', 'tome' => 't', 'tomes' => 't',
        // Range types
        'melee' => 'y', 'missile' => 'z', 'magic' => 'q', 'pulse' => 'p',
        // Modifiers
        'positive' => '+', 'negative' => '-', '+' => '+', '-' => '-',
        // Soulstone
        'soulstone' => 's', 'soulstones' => 's', 'stone' => 's',
        // Signature action
        'signature_action' => 'f', 'signatureaction' => 'f', 'signature' => 'f', 'saction' => 'f',
        // Defensive icons — GameIcon type keys + their GameText text aliases.
        'physical_defense' => 'u', 'fortitude' => 'u',
        'magical_defense' => 'x', 'warding' => 'x',
        'unusual_defense' => 'v', 'unusual' => 'v', 'unusualdefense' => 'v',
    ];

    // Render a space-separated key string ("crow", "physical_defense", …) into
    // M4E glyph spans, skipping anything unmapped.
    $renderGlyphList = function (?string $value) use (&$glyphs): string {
        if (! $value) return '';
        $out = '';
        foreach (explode(' ', $value) as $key) {
            $key = strtolower(trim($key));
            if (isset($glyphs[$key])) $out .= '<span class="gi">' . $glyphs[$key] . '</span>';
        }
        return $out;
    };

    $suitThemes = [
        'crow'  => ['border' => '#16a34a', 'header' => '#dcfce7', 'divider' => '#f0fdf4', 'glyph' => '#15803d'],
        'mask'  => ['border' => '#9333ea', 'header' => '#f3e8ff', 'divider' => '#faf5ff', 'glyph' => '#7e22ce'],
        'ram'   => ['border' => '#dc2626', 'header' => '#fee2e2', 'divider' => '#fef2f2', 'glyph' => '#b91c1c'],
        'tome'  => ['border' => '#2563eb', 'header' => '#dbeafe', 'divider' => '#eff6ff', 'glyph' => '#1d4ed8'],
        'joker' => ['border' => '#d97706', 'header' => '#fef3c7', 'divider' => '#fffbeb', 'glyph' => '#b45309'],
    ];

    // Render game-text tokens ({{crow}}, {{soulstone}} …) as font glyphs;
    // leave plain text otherwise.
    $renderTokens = function (?string $text) use ($glyphs): string {
        if (! $text) return '';
        $escaped = e($text);
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($glyphs) {
            $key = strtolower($m[1]);
            if (isset($glyphs[$key])) {
                return '<span class="gi">' . $glyphs[$key] . '</span>';
            }
            return '(' . ucfirst($key) . ')';
        }, $escaped);
    };

    $suitGlyph = fn (string $suit) => $glyphs[strtolower($suit)] ?? '';

    $renderAction = function ($action) use ($renderTokens, $glyphs): string {
        $rangeGlyph = ($action->range_type && isset($glyphs[strtolower($action->range_type)]))
            ? '<span class="gi">' . $glyphs[strtolower($action->range_type)] . '</span> ' : '';
        $rg = $action->range !== null ? $rangeGlyph . $action->range . '"' : '-';
        $statSuits = '';
        if ($action->stat_suits) {
            foreach (explode(' ', $action->stat_suits) as $s) {
                if (isset($glyphs[strtolower($s)])) $statSuits .= '<span class="gi">' . $glyphs[strtolower($s)] . '</span>';
            }
        }
        $stat = $action->stat !== null ? $action->stat . $statSuits : '-';
        $rst = $action->resisted_by ?: '-';
        $tn = $action->target_number !== null ? $action->target_number : '-';
        $dmg = ($action->damage !== null && $action->damage !== '') ? $action->damage : '-';
        $stone = $action->stone_cost ? str_repeat('<span class="gi">s</span>', $action->stone_cost) . ' ' : '';
        $sig = $action->is_signature ? '<span class="gi">f</span> ' : '';

        $h = '<div class="act">';
        $h .= '<table class="act-tbl"><tr class="act-hdr"><td class="act-name">' . ucfirst($action->type ?? 'Melee') . ' Action</td>'
            . '<td>Rg</td><td>Stat</td><td>Rst</td><td>TN</td><td>Dmg</td></tr>'
            . '<tr class="act-val"><td class="act-name">' . $sig . $stone . e($action->name) . '</td>'
            . '<td>' . $rg . '</td><td>' . $stat . '</td><td>' . e($rst) . '</td><td>' . e($tn) . '</td><td>' . e($dmg) . '</td></tr></table>';
        if ($action->description) {
            $h .= '<div class="act-desc">' . $renderTokens($action->description) . '</div>';
        }
        foreach ($action->triggers as $t) {
            $tsuit = '';
            if ($t->suits) foreach (explode(' ', $t->suits) as $s) if (isset($glyphs[strtolower($s)])) $tsuit .= '<span class="gi">' . $glyphs[strtolower($s)] . '</span>';
            $h .= '<div class="trig">' . $tsuit . ' <b>' . e($t->name) . ':</b> ' . $renderTokens($t->description) . '</div>';
        }
        return $h . '</div>';
    };

    $renderSide = function ($title, $effect, $abilities, $actions, $triggers) use ($renderTokens, $renderAction, $renderGlyphList): string {
        $h = '';
        if ($title) $h .= '<div class="side-title">' . e($title) . '</div>';
        if ($effect) $h .= '<div class="effect">' . nl2br($renderTokens($effect)) . '</div>';
        foreach ($abilities as $a) {
            // [soulstone] Name (suit, defensive-icon). Description — mirrors LootAbilityDisplay.
            $stone = $a->costs_stone ? '<span class="gi">s</span> ' : '';
            $suitG = ($a->suits && $a->suits !== 'soulstone') ? $renderGlyphList($a->suits) : '';
            $defG = $renderGlyphList($a->defensive_ability_type ?? null);
            $parens = ($suitG || $defG) ? ' (' . $suitG . ($suitG && $defG ? ' ' : '') . $defG . ')' : '';
            $desc = $a->description ? ' ' . $renderTokens($a->description) : '';
            $h .= '<div class="abil">' . $stone . '<b>' . e($a->name) . '</b>' . $parens . '.' . $desc . '</div>';
        }
        foreach ($actions as $a) $h .= $renderAction($a);
        foreach ($triggers as $t) {
            $tsuit = '';
            $h .= '<div class="abil"><b>' . e($t->name) . ':</b> ' . $renderTokens($t->description) . '</div>';
        }
        return $h;
    };
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @font-face {
            font-family: 'M4E-Symbols';
            src: url(data:font/opentype;base64,{{ $fontB64 }}) format('opentype');
        }
        @page { size: letter portrait; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Helvetica, Arial, sans-serif; color: #111; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .gi { font-family: 'M4E-Symbols'; font-weight: normal; }

        /* Each page fills the whole sheet (cards inset by the 0.25in padding) so
           "Fit to page" in a viewer/printer can't enlarge the cards past tarot. */
        .page {
            position: relative;
            width: 8.5in;
            height: 11in;
            padding: 0.25in;
            display: flex;
            flex-wrap: wrap;
            gap: 0.06in;
            align-content: flex-start;
            page-break-after: always;
        }
        .page:last-child { page-break-after: auto; }

        /* Corner crop marks: define the sheet extent (so "fit to page" can't
           enlarge the cards) and give a cut/trim reference. */
        .cmark { position: absolute; width: 0.14in; height: 0.14in; }
        .cmark-tl { top: 0.12in; left: 0.12in; border-top: 0.5pt solid #888; border-left: 0.5pt solid #888; }
        .cmark-tr { top: 0.12in; right: 0.12in; border-top: 0.5pt solid #888; border-right: 0.5pt solid #888; }
        .cmark-bl { bottom: 0.12in; left: 0.12in; border-bottom: 0.5pt solid #888; border-left: 0.5pt solid #888; }
        .cmark-br { bottom: 0.12in; right: 0.12in; border-bottom: 0.5pt solid #888; border-right: 0.5pt solid #888; }

        .card {
            width: 2.75in;
            height: 4.75in;
            border: 1.5px solid #999;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            font-size: 9px;
            line-height: 1.2;
        }

        .hdr, .ftr {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 1px 6px;
            font-size: 10px;
            font-weight: bold;
        }
        .hdr { border-bottom: 1px solid rgba(0,0,0,0.15); }
        .ftr { border-top: 1px solid rgba(0,0,0,0.15); }
        .hdr-val, .ftr-val { font-family: 'Courier New', monospace; white-space: nowrap; }
        .hdr-name, .ftr-name { flex: 1; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .side { flex: 1; overflow: hidden; padding: 2px 6px; min-height: 0; }
        .side-b { transform: rotate(180deg); }
        .badge { display: inline-block; font-weight: bold; padding: 0 3px; border-radius: 2px; margin-right: 3px; }
        .side-title { display: inline; font-weight: bold; }
        .effect { margin-top: 1px; }
        .abil { margin: 1px 0; padding-left: 3px; border-left: 2px solid #ccc; }

        .divider { display: flex; align-items: center; justify-content: center; gap: 3px; padding: 1px 0; font-weight: bold; border-top: 1px dashed #ccc; border-bottom: 1px dashed #ccc; font-family: 'Courier New', monospace; }

        .act { margin: 2px 0; }
        .act-tbl { width: 100%; border-collapse: collapse; }
        .act-hdr td { font-size: 6.5px; font-weight: bold; text-align: center; padding: 0 1px; }
        .act-hdr .act-name, .act-val .act-name { text-align: left; width: 44%; }
        .act-val td { font-size: 8px; text-align: center; padding: 0 1px; border-bottom: 0.5px solid #ddd; }
        .act-val .act-name { font-weight: bold; }
        .act-desc { font-size: 8px; }
        .trig { font-size: 7.5px; padding-left: 5px; }
        .ftr-rot { transform: rotate(180deg); width: 100%; display: flex; align-items: center; gap: 4px; }
    </style>
</head>
<body>
@foreach($cards->chunk(4) as $pageCards)
    <div class="page">
        <span class="cmark cmark-tl"></span><span class="cmark cmark-tr"></span>
        <span class="cmark cmark-bl"></span><span class="cmark cmark-br"></span>
        @foreach($pageCards as $card)
            @php
                $suit = strtolower($card->suit);
                $t = $suitThemes[$suit] ?? $suitThemes['joker'];
                $g = $suitGlyph($suit);
            @endphp
            <div class="card" style="border-color: {{ $t['border'] }}">
                {{-- Header --}}
                <div class="hdr" style="background: {{ $t['header'] }}; border-color: {{ $t['border'] }}66">
                    <span class="hdr-val">{{ $card->value_label }}<span class="gi" style="color: {{ $t['glyph'] }}">{{ $g }}</span></span>
                    <span class="hdr-name">{{ $card->name }}</span>
                </div>

                {{-- Side A --}}
                <div class="side">
                    <span class="badge" style="background: {{ $t['header'] }}; color: {{ $t['border'] }}">A</span>
                    {!! $renderSide($card->title_a, $card->effect_a, $card->sideAAbilities, $card->sideAActions, $card->sideATriggers) !!}
                </div>

                {{-- Divider --}}
                <div class="divider" style="background: {{ $t['divider'] }}; color: {{ $t['border'] }}">
                    <span class="gi">{{ $g }}</span> {{ $card->value_label }}
                </div>

                {{-- Side B (rotated) --}}
                <div class="side side-b">
                    <span class="badge" style="background: {{ $t['header'] }}; color: {{ $t['border'] }}">B</span>
                    {!! $renderSide($card->title_b, $card->effect_b, $card->sideBAbilities, $card->sideBActions, $card->sideBTriggers) !!}
                </div>

                {{-- Footer (rotated to match Side B) --}}
                <div class="ftr" style="background: {{ $t['header'] }}; border-color: {{ $t['border'] }}66">
                    <div class="ftr-rot">
                        <span class="ftr-val">{{ $card->value_label }}<span class="gi" style="color: {{ $t['glyph'] }}">{{ $g }}</span></span>
                        <span class="ftr-name">{{ $card->name }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endforeach
</body>
</html>
