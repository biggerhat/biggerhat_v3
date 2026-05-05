<!DOCTYPE html>
<html>
<head>
    <title>{{ $garrison->name }} — TOS Garrison</title>
    <style>
        @page { margin: 12px; }
        html, body { margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; }
        body { font-size: 10px; line-height: 1.35; }
        h1 { margin: 0; padding: 0; font-size: 22px; }
        h2 { margin: 14px 0 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #555; border-bottom: 1px solid #d0d0d0; padding-bottom: 3px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 10px; }
        .header .meta { font-size: 11px; color: #555; margin-top: 2px; }
        .stats { margin: 6px 0 0; font-size: 11px; }
        .stat { display: inline-block; margin-right: 12px; }
        .stat strong { color: #1a1a1a; }
        .stat.over strong { color: #b91c1c; }
        .violations { border: 1px solid #b91c1c; background: #fef2f2; border-radius: 4px; padding: 6px 10px; margin: 8px 0; font-size: 9px; color: #7f1d1d; page-break-inside: avoid; }
        .violations strong { color: #b91c1c; }
        .violations ul { margin: 3px 0 0 14px; padding: 0; }
        .unit { border: 1px solid #d0d0d0; border-radius: 4px; padding: 6px 10px; margin: 4px 0; page-break-inside: avoid; }
        .unit.commander { border-left: 4px solid #d97706; background: #fffaf0; }
        .unit-head { display: block; }
        .unit-name { font-weight: bold; font-size: 12px; }
        .unit-title { font-style: italic; font-size: 10px; color: #555; margin-left: 4px; }
        .unit-cost { float: right; font-weight: bold; }
        .unit-cost.commander { color: #047857; }
        .unit-meta { font-size: 9px; color: #666; margin-top: 3px; }
        .badge { display: inline-block; border: 1px solid #999; border-radius: 3px; padding: 1px 5px; font-size: 8px; margin-right: 3px; vertical-align: middle; }
        .badge.commander { background: #fef3c7; color: #92400e; border-color: #f59e0b; }
        .badge.neutral { background: #f3f4f6; color: #374151; }
        .pool-row { border: 1px solid #d0d0d0; border-radius: 3px; padding: 4px 8px; margin: 3px 0; page-break-inside: avoid; font-size: 10px; }
        .pool-row .row-cost { float: right; font-size: 9px; color: #555; tabular-nums: true; }
        .pool-row .row-name { font-weight: 600; }
        .pool-row .row-meta { font-size: 9px; color: #666; margin-top: 2px; }
        .pool-row .row-effect { font-size: 9px; color: #444; margin-top: 3px; line-height: 1.4; }
        .qty { font-weight: bold; color: #444; }
        .footer { margin-top: 18px; padding-top: 6px; border-top: 1px solid #d0d0d0; font-size: 8px; color: #999; text-align: center; }
        .empty { font-size: 9px; color: #999; font-style: italic; padding: 4px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $garrison->name }}</h1>
        <div class="meta">
            {{ $garrison->allegiance->name }}
            ·
            <span style="text-transform: capitalize;">{{ $garrison->allegiance->type->value ?? $garrison->allegiance->type }}</span>
            ·
            <strong>{{ $format->label() }}</strong>
        </div>
        <div class="stats">
            <span class="stat {{ $scrip_remaining < 0 ? 'over' : '' }}">
                <strong>{{ $scrip_spent }}</strong> / <strong>{{ $scrip_budget }}</strong> Scrip
            </span>
            <span class="stat"><strong>{{ $commanders->count() }}</strong> / <strong>{{ $format->maxCommanders() }}</strong> Commanders</span>
            <span class="stat"><strong>{{ $garrison->stratagems->count() }}</strong> / <strong>{{ $format->stratagemCount() }}</strong> Stratagems</span>
            @if($format->envoyCount() > 0)
                <span class="stat"><strong>{{ $garrison->envoys->count() }}</strong> / <strong>{{ $format->envoyCount() }}</strong> Envoy</span>
            @endif
        </div>
    </div>

    @if(count($violations))
        <div class="violations">
            <strong>{{ count($violations) }} rule {{ count($violations) === 1 ? 'violation' : 'violations' }}:</strong>
            <ul>
                @foreach($violations as $v)
                    <li>{{ $v }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <h2>Commanders ({{ $commanders->count() }} / {{ $format->maxCommanders() }})</h2>
    @if($commanders->count())
        @foreach($commanders as $cu)
            <div class="unit commander">
                <div class="unit-head">
                    <span class="unit-cost commander">+{{ $cu->unit->scrip }}s</span>
                    <span class="unit-name">{{ $cu->unit->name }}</span>
                    @if($cu->unit->title)
                        <span class="unit-title">— {{ $cu->unit->title }}</span>
                    @endif
                </div>
                <div class="unit-meta">
                    <span class="badge commander">Commander</span>
                    @if($cu->unit->restriction)
                        <span class="badge neutral">Neutral · {{ $cu->unit->restriction }}</span>
                    @endif
                    @foreach($cu->unit->specialUnitRules as $rule)
                        @if($rule->slug !== 'commander')
                            <span class="badge">{{ $rule->name }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="empty">No Commanders selected.</div>
    @endif

    <h2>Units ({{ $units->count() }})</h2>
    @if($units->count())
        @foreach($units as $cu)
            <div class="unit">
                <div class="unit-head">
                    <span class="unit-cost">{{ $cu->unit->scrip }}s</span>
                    <span class="unit-name">{{ $cu->unit->name }}</span>
                    @if($cu->unit->title)
                        <span class="unit-title">— {{ $cu->unit->title }}</span>
                    @endif
                </div>
                <div class="unit-meta">
                    @if($cu->unit->restriction)
                        <span class="badge neutral">Neutral · {{ $cu->unit->restriction }}</span>
                    @endif
                    @foreach($cu->unit->specialUnitRules as $rule)
                        <span class="badge">{{ $rule->name }}</span>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="empty">No Units in the pool.</div>
    @endif

    <h2>Assets ({{ $garrison->assets->sum('pivot.quantity') }})</h2>
    @if($garrison->assets->count())
        @foreach($garrison->assets as $asset)
            <div class="pool-row">
                <span class="row-cost">{{ $asset->scrip_cost }}s × {{ $asset->pivot->quantity }} = {{ $asset->scrip_cost * $asset->pivot->quantity }}s</span>
                <span class="qty">×{{ $asset->pivot->quantity }}</span>
                <span class="row-name">{{ $asset->name }}</span>
                @if($asset->limits->count())
                    <div class="row-meta">
                        @foreach($asset->limits as $limit)
                            <span class="badge" style="text-transform: capitalize;">
                                {{ $limit->limit_type }}{{ $limit->parameter_value ? ' (' . $limit->parameter_value . ')' : '' }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    @else
        <div class="empty">No Assets in the pool.</div>
    @endif

    <h2>Stratagems ({{ $garrison->stratagems->count() }} / {{ $format->stratagemCount() }})</h2>
    @if($garrison->stratagems->count())
        @foreach($garrison->stratagems as $stratagem)
            <div class="pool-row">
                <span class="row-cost">{{ $stratagem->tactical_cost }}T</span>
                <span class="row-name">{{ $stratagem->name }}</span>
                <div class="row-meta">
                    @if($stratagem->allegiance)
                        {{ $stratagem->allegiance->name }}
                    @elseif($stratagem->allegiance_type)
                        Any {{ $stratagem->allegiance_type->value ?? $stratagem->allegiance_type }} allegiance
                    @else
                        Universal
                    @endif
                </div>
                @if($stratagem->effect)
                    <div class="row-effect">{{ strip_tags($stratagem->effect) }}</div>
                @endif
            </div>
        @endforeach
    @else
        <div class="empty">No Stratagems picked.</div>
    @endif

    @if($format->envoyCount() > 0)
        <h2>Envoy ({{ $garrison->envoys->count() }} / {{ $format->envoyCount() }})</h2>
        @if($garrison->envoys->count())
            @foreach($garrison->envoys as $envoy)
                <div class="pool-row">
                    <span class="row-name">{{ $envoy->name }}</span>
                    @if($envoy->allegiance)
                        <div class="row-meta">{{ $envoy->allegiance->name }}</div>
                    @endif
                </div>
            @endforeach
        @else
            <div class="empty">No Envoy picked.</div>
        @endif
    @endif

    @if($garrison->notes)
        <h2>Notes</h2>
        <div style="font-size: 10px; line-height: 1.5; white-space: pre-wrap;">{{ $garrison->notes }}</div>
    @endif

    <div class="footer">
        biggerhat.net · TOS Garrison export · {{ now()->format('Y-m-d') }}
    </div>
</body>
</html>
