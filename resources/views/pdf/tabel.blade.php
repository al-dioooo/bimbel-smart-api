<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111827; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .brand { font-size: 11px; color: #4b5563; margin-bottom: 2px; }
        .keterangan { color: #4b5563; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: bold; }
        td.angka, th.angka { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: bold; background: #f9fafb; }
        .kosong { text-align: center; color: #6b7280; padding: 16px; }
    </style>
</head>
<body>
    <div class="brand">{{ config('app.name') }}</div>
    <h1>{{ $judul }}</h1>
    <div class="keterangan">
        @foreach ($keterangan as $line)
            <div>{{ $line }}</div>
        @endforeach
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $i => $heading)
                    <th class="{{ in_array($i, $numeric, true) ? 'angka' : '' }}">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $i => $value)
                        <td class="{{ in_array($i, $numeric, true) ? 'angka' : '' }}">{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="kosong" colspan="{{ count($headings) }}">Tidak ada data pada periode ini.</td></tr>
            @endforelse
            @if ($total && $rows)
                <tr class="total">
                    @foreach ($total as $i => $value)
                        <td class="{{ in_array($i, $numeric, true) ? 'angka' : '' }}">{{ $value }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
</body>
</html>
