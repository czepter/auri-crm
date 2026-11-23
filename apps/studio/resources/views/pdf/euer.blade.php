@php
    $eur = fn (int $cents): string => str_replace('-', '−', number_format($cents / 100, 2, ',', '.').' €');
    $date = fn (?string $day): string => $day === null ? '' : \Illuminate\Support\Carbon::parse($day)->format('d.m.Y');
    $standard = $report->vatMode === 'standard';
@endphp
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>EÜR {{ $report->year }}</title>
<style>
    @page { size: A4; margin: 18mm 16mm; }
    body { font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; font-size: 11px; }
    h1 { font-size: 26px; margin: 0 0 6px; letter-spacing: -0.01em; }
    h2 { font-size: 14px; margin: 0 0 10px; }
    .cover { height: 240mm; display: flex; flex-direction: column; justify-content: center; }
    .meta { color: #555; font-size: 12px; line-height: 1.7; }
    .page { page-break-before: always; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 6px 10px 6px 0; border-bottom: 1px solid #e3e3e3; text-align: left; vertical-align: top; }
    th { font-size: 9.5px; text-transform: uppercase; color: #666; }
    .sub td { color: #555; padding-left: 12px; }
    td:last-child, th:last-child { padding-right: 0; }
    .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .total td { font-weight: 700; border-bottom: 2px solid #1a1a1a; }
    .block { margin-bottom: 22px; }
</style>
</head>
<body>
    <div class="cover">
        <h1>Einnahmenüberschussrechnung {{ $report->year }}</h1>
        <div class="meta">
            {{ $studio->legal_name ?: $studio->display_name }}<br>
            USt-Status: {{ $standard ? 'Regelbesteuerung' : 'Kleinunternehmer' }}<br>
            Erstellt am {{ $createdOn }}
        </div>
    </div>

    <div class="page">
        <div class="block">
            <h2>Betriebseinnahmen</h2>
            <table>
                <tr><td>Einnahmen</td><td class="n">{{ $eur($report->operatingIncome) }}</td></tr>
                @if ($report->creditNotes)
                    <tr class="sub"><td>Gutschriften</td><td class="n">−{{ $eur($report->creditNotes) }}</td></tr>
                @endif
                @if ($standard)
                    <tr><td>Vereinnahmte Umsatzsteuer</td><td class="n">{{ $eur($report->collectedVat) }}</td></tr>
                @endif
                <tr><td>Verkauf von Anlagen</td><td class="n">{{ $eur($report->assetSales) }}</td></tr>
                <tr class="total"><td>Summe</td><td class="n">{{ $eur($report->income) }}</td></tr>
            </table>
        </div>

        <div class="block">
            <h2>Betriebsausgaben</h2>
            <table>
                @foreach ($report->categories as $category)
                    <tr><td>{{ $category['label'] }}</td><td class="n">{{ $eur($category['amount']) }}</td></tr>
                @endforeach
                <tr><td>Abschreibungen</td><td class="n">{{ $eur($report->depreciation) }}</td></tr>
                <tr><td>Restbuchwert Abgänge</td><td class="n">{{ $eur($report->residualBookValue) }}</td></tr>
                @if ($standard)
                    <tr><td>Gezahlte Vorsteuer</td><td class="n">{{ $eur($report->paidInputVat) }}</td></tr>
                @endif
                <tr class="total"><td>Summe</td><td class="n">{{ $eur($report->expenses) }}</td></tr>
            </table>
        </div>

        <table>
            <tr class="total"><td>Gewinn</td><td class="n">{{ $eur($report->profit) }}</td></tr>
        </table>
    </div>

    <div class="page">
        <h2>Ausgaben</h2>
        <table>
            <tr><th>Bezahlt</th><th>Anbieter</th><th>Nr.</th><th>Kategorie</th><th class="n">Anteil</th><th class="n">Betrag</th></tr>
            @foreach ($appendix['expenses'] as $expense)
                <tr>
                    <td>{{ $date($expense['paidAt']) }}</td>
                    <td>{{ $expense['vendor'] }}</td>
                    <td>{{ $expense['invoiceNo'] }}</td>
                    <td>{{ $expense['category'] }}</td>
                    <td class="n">{{ $expense['businessShare'] }} %</td>
                    <td class="n">{{ $expense['grossEur'] === null ? '—' : $eur($expense['grossEur']) }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="page">
        <h2>Anlagenverzeichnis</h2>
        <table>
            <tr><th>Inventarnr.</th><th>Bezeichnung</th><th>Anschaffung</th><th>Methode</th><th class="n">Kosten</th><th class="n">AfA {{ $report->year }}</th><th class="n">Buchwert</th></tr>
            @foreach ($appendix['assets'] as $asset)
                <tr>
                    <td>{{ $asset['inventoryNo'] }}</td>
                    <td>{{ $asset['name'] }}</td>
                    <td>{{ $date($asset['acquiredAt']) }}</td>
                    <td>{{ $asset['method'] }}</td>
                    <td class="n">{{ $eur($asset['cost']) }}</td>
                    <td class="n">{{ $eur($asset['depreciation']) }}</td>
                    <td class="n">{{ $eur($asset['bookValue']) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</body>
</html>
