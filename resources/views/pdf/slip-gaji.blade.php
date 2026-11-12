<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji {{ $mentor['mentor'] }} {{ $periode['label'] }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        .kop { border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; }
        .kop .nama { font-size: 18px; font-weight: bold; }
        .kop .sub { color: #4b5563; }
        h1 { font-size: 15px; margin: 0 0 10px; letter-spacing: 1px; }
        .status { float: right; font-size: 10px; font-weight: bold; padding: 3px 8px; border: 1px solid; }
        .status.final { color: #166534; border-color: #166534; }
        .status.draft { color: #92400e; border-color: #92400e; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.info td { padding: 2px 0; border: none; }
        table.info td.label { width: 140px; color: #4b5563; }
        table.data th, table.data td { border: 1px solid #d1d5db; padding: 6px; text-align: left; }
        table.data th { background: #f3f4f6; }
        .angka { text-align: right !important; white-space: nowrap; }
        tr.total td { font-weight: bold; background: #f9fafb; }
        h2 { font-size: 12px; margin: 0 0 6px; }
        .ringkas td { padding: 4px 6px; border: none; }
        .ringkas tr.diterima td { border-top: 2px solid #111827; font-size: 13px; font-weight: bold; }
        .kaki { margin-top: 24px; color: #6b7280; font-size: 9px; }
        .kosong { color: #6b7280; text-align: center; }
    </style>
</head>
<body>
    <div class="kop">
        <div class="nama">{{ config('app.name') }}</div>
        <div class="sub">Sistem Informasi Absensi dan Penggajian</div>
    </div>

    <span class="status {{ $periode['status'] }}">{{ $periode['status'] === 'final' ? 'FINAL' : 'DRAF' }}</span>
    <h1>SLIP GAJI</h1>

    <table class="info">
        <tr><td class="label">Nama Guru/Mentor</td><td>: {{ $mentor['mentor'] }}</td></tr>
        <tr><td class="label">Periode</td><td>: {{ $periode['label'] }}</td></tr>
        <tr><td class="label">Status</td><td>: {{ $periode['status'] === 'final' ? 'Final, difinalisasi '.$periode['finalized_label'] : 'Draf (periode belum difinalisasi)' }}</td></tr>
    </table>

    <h2>Rincian Kehadiran</h2>
    <table class="data">
        <thead>
            <tr><th>Kelas</th><th class="angka">Tarif</th><th class="angka">Jumlah Kehadiran</th><th class="angka">Subtotal</th></tr>
        </thead>
        <tbody>
            @forelse ($mentor['rincian'] as $kelas)
                <tr>
                    <td>{{ $kelas['kelas'] }}</td>
                    <td class="angka">{{ \App\Support\Laporan::rupiah($kelas['tarif']) }}</td>
                    <td class="angka">{{ $kelas['jumlah_kehadiran'] }}</td>
                    <td class="angka">{{ \App\Support\Laporan::rupiah($kelas['nominal']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="kosong">Tidak ada kehadiran pada periode ini.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="2">Gaji pokok</td>
                <td class="angka">{{ $mentor['total_kehadiran'] }}</td>
                <td class="angka">{{ \App\Support\Laporan::rupiah($mentor['gaji_pokok']) }}</td>
            </tr>
        </tbody>
    </table>

    @if (count($mentor['penyesuaian']))
        <h2>Penyesuaian</h2>
        <table class="data">
            <thead><tr><th>Jenis</th><th>Keterangan</th><th class="angka">Nominal</th></tr></thead>
            <tbody>
                @foreach ($mentor['penyesuaian'] as $p)
                    <tr>
                        <td>{{ $p['jenis'] === 'bonus' ? 'Bonus' : 'Potongan' }}</td>
                        <td>{{ $p['keterangan'] ?: '-' }}</td>
                        <td class="angka">{{ $p['jenis'] === 'bonus' ? '' : '-' }}{{ \App\Support\Laporan::rupiah($p['nominal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="ringkas">
        <tr><td>Gaji pokok</td><td class="angka">{{ \App\Support\Laporan::rupiah($mentor['gaji_pokok']) }}</td></tr>
        <tr><td>Bonus</td><td class="angka">{{ \App\Support\Laporan::rupiah($mentor['bonus']) }}</td></tr>
        <tr><td>Potongan</td><td class="angka">-{{ \App\Support\Laporan::rupiah($mentor['potongan']) }}</td></tr>
        <tr class="diterima"><td>Total diterima</td><td class="angka">{{ \App\Support\Laporan::rupiah($mentor['total']) }}</td></tr>
    </table>

    <div class="kaki">
        Dicetak {{ $dicetak }} melalui {{ config('app.name') }}. Gaji dihitung dari jumlah kehadiran (hadir) dikali tarif kelas, ditambah bonus dan dikurangi potongan.
    </div>
</body>
</html>
