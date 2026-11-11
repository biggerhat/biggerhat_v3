<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $tournament->name }} — Results</title>
    <style>
        @page { margin: 28px 30px; }
        html, body { margin: 0; padding: 0; font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; }
        body { font-size: 11px; line-height: 1.35; }
        h1 { margin: 0; font-size: 26px; }
        h2 { margin: 18px 0 6px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #555; border-bottom: 1px solid #d0d0d0; padding-bottom: 3px; }
        .header { border-bottom: 3px solid #333; padding-bottom: 10px; }
        .meta { font-size: 11px; color: #555; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; }
        .podium td { width: 33%; vertical-align: bottom; padding: 0 4px; }
        .place { border: 1px solid #d0d0d0; border-radius: 6px; padding: 10px 8px; text-align: center; }
        .place .rank { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #777; }
        .place .name { font-size: 16px; font-weight: bold; margin: 4px 0 2px; }
        .place .sub { font-size: 10px; color: #666; }
        .place .score { font-size: 11px; margin-top: 4px; }
        .gold { border-color: #d4a017; background: #fffbea; }
        .silver { border-color: #9ca3af; background: #f5f6f8; }
        .bronze { border-color: #b4773a; background: #fdf4ec; }
        .first { padding-top: 22px; padding-bottom: 22px; }
        table.standings th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: #555; border-bottom: 2px solid #333; padding: 4px 5px; }
        table.standings td { padding: 5px; border-bottom: 1px solid #e5e5e5; }
        table.standings th.num, table.standings td.num { text-align: center; }
        .muted { color: #777; }
        .tag { font-size: 8px; border: 1px solid #aaa; border-radius: 3px; padding: 0 3px; color: #555; }
        .footer { margin-top: 20px; padding-top: 6px; border-top: 1px solid #d0d0d0; font-size: 9px; color: #888; text-align: center; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    @php
        $labels = ['sos' => 'SoS', 'diff' => 'DIFF', 'vp' => 'VP'];
        $unit = $isTeamEvent ? 'Team' : 'Player';
        $ordinal = fn (int $n) => $n === 1 ? '1st' : ($n === 2 ? '2nd' : ($n === 3 ? '3rd' : $n.'th'));
    @endphp

    <div class="header">
        <h1>{{ $tournament->name }}</h1>
        <div class="meta">
            {{ $tournament->event_date->format('F j, Y') }}
            @if ($tournament->location) · {{ $tournament->location }} @endif
            · {{ $rulesetLabel }}
            · {{ $standings->where('is_ringer', false)->count() }} {{ strtolower($unit) }}s
            · {{ $tournament->rounds->count() }} rounds
        </div>
    </div>

    @if ($podium->isNotEmpty())
        <h2>Podium</h2>
        <table class="podium">
            <tr>
                @foreach ($podium as $entry)
                    <td>
                        <div class="place {{ $entry['rank'] === 1 ? 'gold first' : ($entry['rank'] === 2 ? 'silver' : 'bronze') }}">
                            <div class="rank">{{ $ordinal($entry['rank']) }}</div>
                            <div class="name">{{ $entry['display_name'] }}</div>
                            <div class="sub">
                                @if (! empty($entry['members'])) {{ implode(' · ', $entry['members']) }}
                                @elseif ($entry['faction_label']) {{ $entry['faction_label'] }}
                                @elseif (! empty($entry['allegiance'])) {{ $entry['allegiance']['name'] }} @endif
                            </div>
                            <div class="score">{{ $entry['total_tp'] }} TP · {{ $entry['wins'] ?? 0 }}-{{ $entry['losses'] ?? 0 }}-{{ $entry['ties'] ?? 0 }}</div>
                        </div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <h2>Final standings</h2>
    <table class="standings">
        <thead>
            <tr>
                <th style="width: 6%">#</th>
                <th>{{ $unit }}</th>
                <th class="num">TP</th>
                <th class="num">W-L-T</th>
                @foreach ($tiebreakers as $key)
                    <th class="num">{{ $labels[$key] ?? strtoupper($key) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($standings as $entry)
                <tr>
                    <td>{{ $entry['is_ringer'] ? '—' : $entry['rank'] }}</td>
                    <td>
                        <strong>{{ $entry['display_name'] }}</strong>
                        @if ($entry['is_ringer']) <span class="tag">ringer</span> @endif
                        @if ($entry['is_dropped']) <span class="tag">dropped</span> @endif
                        <span class="muted">
                            @if (! empty($entry['members'])) {{ implode(' · ', $entry['members']) }}
                            @elseif ($entry['faction_label']) {{ $entry['faction_label'] }}
                            @elseif (! empty($entry['allegiance'])) {{ $entry['allegiance']['name'] }} @endif
                        </span>
                    </td>
                    <td class="num"><strong>{{ $entry['total_tp'] }}</strong></td>
                    <td class="num">{{ $entry['wins'] ?? 0 }}-{{ $entry['losses'] ?? 0 }}-{{ $entry['ties'] ?? 0 }}</td>
                    @foreach ($tiebreakers as $key)
                        <td class="num">{{ $key === 'diff' && $entry['total_diff'] > 0 ? '+' : '' }}{{ $entry['total_'.$key] }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        @if ($organizer) Organized by {{ $organizer }} · @endif {{ $url }}
    </div>
</body>
</html>
