<!DOCTYPE html>
<html>
<head>
    <title>{{ $company->name }} — TOS Company</title>
    <style>
        @page { margin: 12px; }
        html, body { margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; }
        body { font-size: 10px; line-height: 1.35; }
        h1 { margin: 0; padding: 0; font-size: 22px; }
        h2 { margin: 16px 0 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #555; border-bottom: 1px solid #d0d0d0; padding-bottom: 3px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px; display: flex; }
        .header .meta { font-size: 11px; color: #555; margin-top: 2px; }
        .stats { margin: 6px 0 0; font-size: 11px; }
        .stat { display: inline-block; margin-right: 12px; }
        .stat strong { color: #1a1a1a; }
        .unit { border: 1px solid #d0d0d0; border-radius: 4px; padding: 7px 10px; margin: 5px 0; page-break-inside: avoid; }
        .unit.commander { border-left: 4px solid #d97706; background: #fffaf0; }
        .unit.combined-arms-child { border-left: 4px solid #d97706; background: #fdf4dd; margin-left: 24px; }
        .unit-head { display: block; }
        .unit-name { font-weight: bold; font-size: 13px; }
        .unit-title { font-style: italic; font-size: 10px; color: #555; margin-left: 4px; }
        .unit-cost { float: right; font-weight: bold; }
        .unit-cost.commander { color: #047857; }
        .unit-meta { font-size: 9px; color: #666; margin-top: 3px; }
        .badge { display: inline-block; border: 1px solid #999; border-radius: 3px; padding: 1px 5px; font-size: 8px; margin-right: 3px; vertical-align: middle; }
        .badge.commander { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
        .badge.neutral { background: #f3f4f6; color: #374151; }
        .assets { font-size: 9px; color: #555; margin-top: 4px; padding-top: 4px; border-top: 1px dashed #d0d0d0; }
        .asset { display: inline-block; padding: 1px 5px; margin-right: 4px; background: #ecfeff; border: 1px solid #06b6d4; border-radius: 3px; font-size: 8px; }
        .footer { margin-top: 18px; padding-top: 6px; border-top: 1px solid #d0d0d0; font-size: 8px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div style="flex: 1;">
            <h1>{{ $company->name }}</h1>
            <div class="meta">
                {{ $company->allegiance->name }} · <span style="text-transform: capitalize;">{{ $company->allegiance->type->value ?? $company->allegiance->type }}</span>
            </div>
            <div class="stats">
                <span class="stat"><strong>{{ $scrip_spent }}</strong> / <strong>{{ $scrip_budget }}</strong> Scrip</span>
                <span class="stat"><strong>{{ $scrip_remaining }}</strong> remaining</span>
                <span class="stat"><strong>{{ $renderable_units->count() }}</strong> {{ $renderable_units->count() === 1 ? 'unit' : 'units' }}</span>
            </div>
        </div>
    </div>

    <h2>Roster</h2>
    @foreach($renderable_units as $cu)
        <div class="unit {{ $cu->is_commander ? 'commander' : '' }}">
            <div class="unit-head">
                <span class="unit-cost {{ $cu->is_commander ? 'commander' : '' }}">
                    {{ $cu->is_commander ? '+' : '' }}{{ $cu->unit->scrip }}s
                </span>
                <span class="unit-name">{{ $cu->unit->name }}</span>
                @if($cu->unit->title)
                    <span class="unit-title">— {{ $cu->unit->title }}</span>
                @endif
            </div>
            <div class="unit-meta">
                @if($cu->is_commander)
                    <span class="badge commander">Commander</span>
                @endif
                @if($cu->unit->restriction)
                    <span class="badge neutral">Neutral · {{ $cu->unit->restriction }}</span>
                @endif
                @foreach($cu->unit->specialUnitRules as $rule)
                    @if($rule->slug !== 'commander')
                        <span class="badge">{{ $rule->name }}</span>
                    @endif
                @endforeach
            </div>
            @if($cu->assets->count())
                <div class="assets">
                    <strong>Assets:</strong>
                    @foreach($cu->assets as $asset)
                        <span class="asset">{{ $asset->name }} ({{ $asset->scrip_cost }}s)</span>
                    @endforeach
                </div>
            @endif
            @php
                $child = $children_by_parent->get($cu->unit->id);
            @endphp
            @if($child)
                <div class="unit combined-arms-child" style="margin-top: 5px; margin-bottom: 0;">
                    <span class="unit-name" style="font-size: 11px;">{{ $child->unit->name }}</span>
                    <span class="badge commander" style="margin-left: 4px;">Combined Arms</span>
                    <span style="font-size: 8px; color: #999; font-style: italic;">auto-attached</span>
                </div>
            @endif
        </div>
    @endforeach

    @if($company->notes)
        <h2>Notes</h2>
        <div style="font-size: 10px; line-height: 1.5; white-space: pre-wrap;">{{ $company->notes }}</div>
    @endif

    <div class="footer">
        biggerhat.net · TOS Company export · {{ now()->format('Y-m-d') }}
    </div>
</body>
</html>
