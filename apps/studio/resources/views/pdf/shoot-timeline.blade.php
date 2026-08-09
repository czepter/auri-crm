<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Ablauf · {{ $shoot->label ?? $shoot->kind->label() }}</title>
<style>
    body { font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; margin: 40px; }
    h1 { font-size: 18px; margin: 0 0 4px; letter-spacing: -0.01em; }
    .meta { color: #666; font-size: 12px; margin-bottom: 28px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 10px 0; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
    .time { width: 56px; font-weight: 600; font-size: 13px; font-variant-numeric: tabular-nums; }
    .title { font-weight: 600; font-size: 13px; }
    .where, .note { color: #666; font-size: 11.5px; margin-top: 2px; }
    tr.transit .title { font-weight: 400; color: #888; }
</style>
</head>
<body>
    <h1>{{ $shoot->label ?? $shoot->kind->label() }}</h1>
    <div class="meta">{{ $shoot->project->name }} &middot; {{ $shoot->date->format('d.m.Y') }}</div>

    <table>
        @foreach ($shoot->timeline as $entry)
            <tr class="{{ $entry->is_transit ? 'transit' : '' }}">
                <td class="time">{{ substr($entry->time, 0, 5) }}</td>
                <td>
                    <div class="title">{{ $entry->title }}</div>
                    @if ($entry->location)
                        <div class="where">{{ $entry->location }}</div>
                    @endif
                    @if ($entry->note)
                        <div class="note">{{ $entry->note }}</div>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</body>
</html>
