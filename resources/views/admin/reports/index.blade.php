<x-layouts::app :title="__('Laporan Penyewaan Garasi Multi-Dimensi')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">Reports &amp; Analytics Penyewaan Garasi</flux:heading>
                <flux:text class="mt-1">
                    Rekap transaksi sewa garasi bulanan, harian, per tipe garasi (S, C, E), dan per penyewa dalam Rupiah (IDR).
                </flux:text>
            </div>
        </div>

        <!-- Validation Errors Alert -->
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/50 dark:text-red-200" data-test="report-error-alert">
                <div class="font-semibold">Parameter filter laporan tidak valid:</div>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Multi-Dimensional Filter Form -->
        <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs print:hidden dark:border-zinc-700 dark:bg-zinc-900">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <!-- Mode Laporan -->
                <div>
                    <flux:label for="mode">Dimensi Laporan</flux:label>
                    <select
                        id="mode"
                        name="mode"
                        data-test="filter-mode"
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        <option value="bulanan" @selected($filters['mode'] === 'bulanan')>Laporan Bulanan (Per Tahun)</option>
                        <option value="harian" @selected($filters['mode'] === 'harian')>Laporan Harian (Per Bulan)</option>
                        <option value="tipe_garasi" @selected($filters['mode'] === 'tipe_garasi')>Berdasarkan Tipe Garasi</option>
                        <option value="penyewa" @selected($filters['mode'] === 'penyewa')>Berdasarkan Penyewa</option>
                    </select>
                </div>

                <!-- Tahun -->
                <div>
                    <flux:label for="tahun">Tahun Laporan</flux:label>
                    <input
                        id="tahun"
                        type="number"
                        name="tahun"
                        min="2020"
                        max="2100"
                        value="{{ $filters['tahun'] }}"
                        data-test="filter-tahun"
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    />
                </div>

                <!-- Bulan (Digunakan pada Laporan Harian) -->
                <div>
                    <flux:label for="bulan">Bulan (Laporan Harian)</flux:label>
                    <select
                        id="bulan"
                        name="bulan"
                        data-test="filter-bulan"
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        @foreach ($monthNames as $monthNumber => $monthLabel)
                            <option value="{{ $monthNumber }}" @selected($filters['bulan'] === $monthNumber)>
                                {{ sprintf('%02d - %s', $monthNumber, $monthLabel) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tipe Garasi -->
                <div>
                    <flux:label for="tipe_garasi">Filter Tipe Garasi</flux:label>
                    <select
                        id="tipe_garasi"
                        name="tipe_garasi"
                        data-test="filter-tipe-garasi"
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        <option value="">Semua Tipe Garasi (S, C, E)</option>
                        @foreach ($garageTypes as $type)
                            <option value="{{ $type->value }}" @selected($filters['tipe_garasi'] === $type->value)>
                                {{ $type->label() }} (Prefix {{ $type->prefix() }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Penyewa -->
                <div>
                    <flux:label for="penyewa_id">Filter Penyewa</flux:label>
                    <select
                        id="penyewa_id"
                        name="penyewa_id"
                        data-test="filter-penyewa"
                        class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        <option value="">Semua Penyewa</option>
                        @foreach ($allTenants as $tenantItem)
                            <option value="{{ $tenantItem->id }}" @selected($filters['penyewa_id'] === $tenantItem->id)>
                                {{ $tenantItem->nama_lengkap }} ({{ $tenantItem->nik }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Tombol Filter -->
                <div class="flex items-end gap-2">
                    <flux:button type="submit" variant="primary" class="w-full" data-test="apply-report-filter-btn">
                        Terapkan Filter
                    </flux:button>
                    <flux:button variant="ghost" :href="route('admin.reports.index', ['mode' => $filters['mode']])" wire:navigate>
                        Reset
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Financial KPI Summary Cards (IDR) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Total Transaksi Tersaring</div>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white" data-test="kpi-total-transactions">
                    {{ $summaryMetrics['total_transactions'] }} Transaksi
                </div>
                <div class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                    {{ $summaryMetrics['active_transactions'] }} sewa aktif berjalan
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Pendapatan Sewa Baru (IDR)</div>
                <div class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400" data-test="kpi-sewa-baru">
                    Rp {{ number_format($summaryMetrics['total_sewa_baru'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-zinc-500">
                    Pokok: Rp {{ number_format($summaryMetrics['total_tarif_pokok'], 0, ',', '.') }} | Listrik EV: Rp {{ number_format($summaryMetrics['total_biaya_listrik'], 0, ',', '.') }}
                </div>
            </div>

            <div class="rounded-xl border border-indigo-200 bg-indigo-50/40 p-5 shadow-xs dark:border-indigo-800/60 dark:bg-indigo-950/20">
                <div class="text-xs font-medium uppercase tracking-wider text-indigo-700 dark:text-indigo-300">Pendapatan Perpanjangan (IDR)</div>
                <div class="mt-2 font-mono text-2xl font-bold text-indigo-600 dark:text-indigo-400" data-test="kpi-perpanjangan">
                    Rp {{ number_format($summaryMetrics['total_biaya_perpanjangan'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                    {{ $summaryMetrics['total_extensions'] }}x Transaksi Extend (Lunas)
                </div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-xs dark:border-emerald-800/60 dark:bg-emerald-950/20">
                <div class="text-xs font-medium uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Total Pendapatan Akumulatif (IDR)</div>
                <div class="mt-2 text-2xl font-extrabold text-emerald-700 dark:text-emerald-400" data-test="kpi-grand-total">
                    Rp {{ number_format($summaryMetrics['grand_total_pendapatan'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/80">
                    Sewa Baru + Perpanjangan Sewa
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- DIMENSI 1: LAPORAN BULANAN (Per Bulan dalam Tahun Tertentu)               -->
        <!-- ========================================================================= -->
        @if ($filters['mode'] === 'bulanan')
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900" data-test="section-monthly-report">
                <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">Rekapitulasi Laporan Bulanan &mdash; Tahun {{ $monthlyReport['year'] }}</flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Ringkasan jumlah transaksi sewa garasi, tarif pokok, biaya listrik EV, dan biaya perpanjangan per bulan (Januari s/d Desember {{ $monthlyReport['year'] }}).
                    </flux:text>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Bulan</th>
                                <th class="px-4 py-3 text-center">Jml Transaksi</th>
                                <th class="px-4 py-3 text-right">Tarif Pokok (IDR)</th>
                                <th class="px-4 py-3 text-right">Biaya Listrik EV (IDR)</th>
                                <th class="px-4 py-3 text-right">Total Sewa Baru (IDR)</th>
                                <th class="px-4 py-3 text-right">Biaya Extend (IDR)</th>
                                <th class="px-4 py-3 text-right">Total Pendapatan (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($monthlyReport['months'] as $mRow)
                                <tr class="{{ $mRow['transaction_count'] > 0 ? 'bg-indigo-50/20 font-medium dark:bg-indigo-950/15' : 'text-zinc-400 dark:text-zinc-500' }}" data-test="monthly-row-{{ $mRow['month'] }}">
                                    <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">
                                        {{ sprintf('%02d', $mRow['month']) }} &mdash; {{ $mRow['month_name'] }} {{ $monthlyReport['year'] }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-mono">
                                        {{ $mRow['transaction_count'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">
                                        Rp {{ number_format($mRow['tarif_pokok'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">
                                        Rp {{ number_format($mRow['biaya_listrik'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs font-semibold">
                                        Rp {{ number_format($mRow['total_sewa_baru'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">
                                        Rp {{ number_format($mRow['total_perpanjangan'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                        Rp {{ number_format($mRow['grand_total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-zinc-300 bg-zinc-100 font-bold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <tr>
                                <td class="px-4 py-3.5">TOTAL TAHUN {{ $monthlyReport['year'] }}</td>
                                <td class="px-4 py-3.5 text-center font-mono">{{ $monthlyReport['totals']['transaction_count'] }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($monthlyReport['totals']['tarif_pokok'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($monthlyReport['totals']['biaya_listrik'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($monthlyReport['totals']['total_sewa_baru'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($monthlyReport['totals']['total_perpanjangan'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-sm text-emerald-700 dark:text-emerald-400">
                                    Rp {{ number_format($monthlyReport['totals']['grand_total'], 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- DIMENSI 2: LAPORAN HARIAN (Per Hari dalam Bulan Tertentu)                 -->
        <!-- ========================================================================= -->
        @if ($filters['mode'] === 'harian')
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900" data-test="section-daily-report">
                <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">
                        Rekapitulasi Laporan Harian &mdash; Bulan {{ $dailyReport['month_name'] }} {{ $dailyReport['year'] }} ({{ $dailyReport['days_in_month'] }} Hari Kalender)
                    </flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Rekapitulasi transaksi penyewaan per hari dalam bulan {{ $dailyReport['month_name'] }} {{ $dailyReport['year'] }}.
                    </flux:text>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3 text-center">Jml Transaksi</th>
                                <th class="px-4 py-3 text-right">Tarif Pokok (IDR)</th>
                                <th class="px-4 py-3 text-right">Biaya Listrik EV (IDR)</th>
                                <th class="px-4 py-3 text-right">Total Sewa Baru (IDR)</th>
                                <th class="px-4 py-3 text-right">Biaya Extend (IDR)</th>
                                <th class="px-4 py-3 text-right">Total Pendapatan (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($dailyReport['days'] as $dRow)
                                <tr class="{{ $dRow['transaction_count'] > 0 ? 'bg-emerald-50/30 font-medium dark:bg-emerald-950/15' : 'text-zinc-400 dark:text-zinc-500' }}" data-test="daily-row-{{ $dRow['date'] }}">
                                    <td class="px-4 py-2.5 font-mono text-xs font-semibold text-zinc-900 dark:text-white">
                                        {{ $dRow['day_label'] }} ({{ $dRow['date'] }})
                                    </td>
                                    <td class="px-4 py-2.5 text-center font-mono text-xs">
                                        {{ $dRow['transaction_count'] }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-xs">
                                        Rp {{ number_format($dRow['tarif_pokok'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-xs">
                                        Rp {{ number_format($dRow['biaya_listrik'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-xs font-semibold">
                                        Rp {{ number_format($dRow['total_sewa_baru'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-xs">
                                        Rp {{ number_format($dRow['total_perpanjangan'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                        Rp {{ number_format($dRow['grand_total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 border-zinc-300 bg-zinc-100 font-bold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                            <tr>
                                <td class="px-4 py-3.5">TOTAL {{ strtoupper($dailyReport['month_name']) }} {{ $dailyReport['year'] }}</td>
                                <td class="px-4 py-3.5 text-center font-mono">{{ $dailyReport['totals']['transaction_count'] }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($dailyReport['totals']['tarif_pokok'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($dailyReport['totals']['biaya_listrik'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($dailyReport['totals']['total_sewa_baru'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs">Rp {{ number_format($dailyReport['totals']['total_perpanjangan'], 0, ',', '.') }}</td>
                                <td class="px-4 py-3.5 text-right font-mono text-sm text-emerald-700 dark:text-emerald-400">
                                    Rp {{ number_format($dailyReport['totals']['grand_total'], 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- DIMENSI 3: LAPORAN BERDASARKAN TIPE GARASI (Sepeda, Mobil, Listrik)       -->
        <!-- ========================================================================= -->
        @if ($filters['mode'] === 'tipe_garasi')
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900" data-test="section-garage-type-report">
                <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">Rekapitulasi Laporan Berdasarkan Tipe Garasi (S, C, E)</flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Perbandingan okupansi unit garasi dan kontribusi pendapatan berdasarkan Tipe Garasi Sepeda (S), Mobil Bensin (C), dan Mobil Listrik (E).
                    </flux:text>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3.5">Tipe Garasi</th>
                                <th class="px-4 py-3.5 text-center">Status Unit (Used / Total)</th>
                                <th class="px-4 py-3.5 text-center">Jml Transaksi</th>
                                <th class="px-4 py-3.5 text-right">Tarif Pokok (IDR)</th>
                                <th class="px-4 py-3.5 text-right">Biaya Listrik EV (IDR)</th>
                                <th class="px-4 py-3.5 text-right">Total Sewa Baru (IDR)</th>
                                <th class="px-4 py-3.5 text-right">Biaya Extend (IDR)</th>
                                <th class="px-4 py-3.5 text-right">Total Pendapatan (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($garageTypeReport as $gTypeRow)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="garage-type-row-{{ $gTypeRow['tipe_garasi'] }}">
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $gTypeRow['label'] }}</div>
                                        <div class="font-mono text-xs text-indigo-600 dark:text-indigo-400">Kode Prefix: {{ $gTypeRow['prefix'] }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                            {{ $gTypeRow['used_units'] }} Used / {{ $gTypeRow['total_units'] }} Unit
                                        </span>
                                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400">
                                            {{ $gTypeRow['available_units'] }} Tersedia
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center font-mono font-bold">
                                        {{ $gTypeRow['transaction_count'] }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs">
                                        Rp {{ number_format($gTypeRow['tarif_pokok'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs">
                                        Rp {{ number_format($gTypeRow['biaya_listrik'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs font-semibold">
                                        Rp {{ number_format($gTypeRow['total_sewa_baru'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs">
                                        Rp {{ number_format($gTypeRow['total_perpanjangan'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                        Rp {{ number_format($gTypeRow['grand_total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- DIMENSI 4: LAPORAN BERDASARKAN PENYEWA                                    -->
        <!-- ========================================================================= -->
        @if ($filters['mode'] === 'penyewa')
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900" data-test="section-tenant-report">
                <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">Rekapitulasi Laporan Berdasarkan Penyewa</flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Ringkasan jumlah garasi aktif disewa (mendukung multi-sewa bersamaan), total transaksi, riwayat perpanjangan, dan kontribusi biaya per penyewa.
                    </flux:text>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3.5">Penyewa &amp; NIK KTP</th>
                                <th class="px-4 py-3.5">Kontak</th>
                                <th class="px-4 py-3.5 text-center">Garasi Aktif</th>
                                <th class="px-4 py-3.5 text-center">Total Transaksi</th>
                                <th class="px-4 py-3.5 text-right">Total Sewa Baru (IDR)</th>
                                <th class="px-4 py-3.5 text-right">Biaya Extend (IDR)</th>
                                <th class="px-4 py-3.5 text-right">Total Pembayaran (IDR)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($tenantReport as $tRow)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="tenant-report-row-{{ $tRow['penyewa_id'] }}">
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-zinc-900 dark:text-white">{{ $tRow['nama_lengkap'] }}</div>
                                        <div class="font-mono text-xs text-zinc-500">NIK: {{ $tRow['nik'] }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-zinc-600 dark:text-zinc-300">
                                        <div>{{ $tRow['no_telp'] }}</div>
                                        <div class="text-zinc-400">{{ $tRow['email'] }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 font-mono text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            {{ $tRow['active_rentals_count'] }} Unit
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center font-mono font-bold">
                                        {{ $tRow['total_transactions'] }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs font-semibold">
                                        Rp {{ number_format($tRow['total_sewa_baru'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-xs">
                                        Rp {{ number_format($tRow['total_perpanjangan'], 0, ',', '.') }}
                                        @if ($tRow['extension_count'] > 0)
                                            <div class="text-[11px] text-indigo-600 dark:text-indigo-400">({{ $tRow['extension_count'] }}x Extend)</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-right font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                        Rp {{ number_format($tRow['grand_total'], 0, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500">
                                        Tidak ada data penyewa yang ditemukan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- FITUR KHUSUS MONITORING: Daftar Garasi yang Sedang Aktif Disewa (UC-08)   -->
        <!-- ========================================================================= -->
        <div class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-xs dark:border-emerald-900/60 dark:bg-zinc-900" data-test="section-active-monitoring">
            <div class="flex flex-col justify-between gap-2 border-b border-emerald-200/80 bg-emerald-50/40 p-5 sm:flex-row sm:items-center dark:border-emerald-900/50 dark:bg-emerald-950/20">
                <div>
                    <flux:heading size="lg">Monitoring Real-Time Garasi yang Sedang Aktif Disewa</flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Daftar instan seluruh unit garasi yang saat ini sedang aktif disewa beserta penyewa, kendaraan, dan tanggal berakhirnya.
                    </flux:text>
                </div>
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 font-mono text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    {{ $activeMonitoring->count() }} Unit Sedang Disewa
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">Unit Garasi</th>
                            <th class="px-4 py-3">No. Transaksi</th>
                            <th class="px-4 py-3">Penyewa (NIK)</th>
                            <th class="px-4 py-3">Kendaraan</th>
                            <th class="px-4 py-3">Periode Sewa Aktif</th>
                            <th class="px-4 py-3 text-right">Total Sewa Baru</th>
                            <th class="px-4 py-3 text-right">Status Extend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($activeMonitoring as $activeItem)
                            @php
                                $activeExtFee = (float) $activeItem->perpanjanganSewa->sum('biaya_perpanjangan');
                            @endphp
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="monitoring-row-{{ $activeItem->garasi_id }}">
                                <td class="px-4 py-3.5">
                                    <span class="font-mono text-base font-bold text-indigo-600 dark:text-indigo-400">{{ $activeItem->garasi_id }}</span>
                                    <div class="text-xs text-zinc-500">{{ $activeItem->garasi?->tipe_garasi?->label() }}</div>
                                </td>
                                <td class="px-4 py-3.5 font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                    {{ $activeItem->nomor_transaksi }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $activeItem->penyewa?->nama_lengkap }}</div>
                                    <div class="font-mono text-xs text-zinc-500">{{ $activeItem->penyewa?->nik }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">{{ $activeItem->kendaraan?->plat_nomor }}</div>
                                    <div class="text-xs text-zinc-500">{{ $activeItem->kendaraan?->merek }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="text-xs text-zinc-500">{{ $activeItem->tanggal_mulai->format('d M Y') }} &ndash;</div>
                                    <div class="font-semibold text-emerald-700 dark:text-emerald-400">{{ $activeItem->tanggal_selesai->format('d M Y') }}</div>
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono text-xs font-semibold">
                                    Rp {{ number_format($activeItem->total_bayar, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @if ($activeItem->perpanjanganSewa->isNotEmpty())
                                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 font-mono text-xs font-bold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            +Rp {{ number_format($activeExtFee, 0, ',', '.') }} (Lunas)
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">Tidak ada extend</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-zinc-500">
                                    Saat ini tidak ada unit garasi yang sedang aktif disewa.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TABEL RINCIAN TRANSAKSI PENYEWAAN SESUAI FILTER AKTIF (10 DATA TERAKHIR)  -->
        <!-- ========================================================================= -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900" data-test="section-filtered-transactions">
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                <flux:heading size="lg">Rincian Transaksi Penyewaan Tersaring ({{ $transactions->take(10)->count() }} Data Terakhir)</flux:heading>
                <flux:text class="mt-0.5 text-xs">
                    Menampilkan maksimal 10 transaksi penyewaan terbaru sesuai filter aktif beserta rincian tarif pokok, biaya listrik EV, dan riwayat perpanjangan.
                </flux:text>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3.5">No. Transaksi</th>
                            <th class="px-4 py-3.5">Garasi</th>
                            <th class="px-4 py-3.5">Penyewa</th>
                            <th class="px-4 py-3.5">Kendaraan</th>
                            <th class="px-4 py-3.5">Periode Sewa</th>
                            <th class="px-4 py-3.5 text-right">Sewa Baru (IDR)</th>
                            <th class="px-4 py-3.5 text-right">Biaya Perpanjangan</th>
                            <th class="px-4 py-3.5 text-right">Total Gabungan (IDR)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($transactions->take(10) as $trx)
                            @php
                                $extCount = $trx->perpanjanganSewa->count();
                                $extFee = (float) $trx->perpanjanganSewa->sum('biaya_perpanjangan');
                                $combinedTotal = (float) $trx->total_bayar + $extFee;
                            @endphp
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="report-trx-row-{{ $trx->nomor_transaksi }}">
                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">{{ $trx->nomor_transaksi }}</div>
                                    <span class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $trx->isActive() ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' }}">
                                        {{ $trx->status_sewa->label() }}
                                    </span>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-base font-bold text-indigo-600 dark:text-indigo-400">{{ $trx->garasi_id }}</div>
                                    <div class="text-xs text-zinc-500">{{ $trx->garasi?->tipe_garasi?->label() }}</div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $trx->penyewa?->nama_lengkap }}</div>
                                    <div class="font-mono text-xs text-zinc-500">NIK: {{ $trx->penyewa?->nik }}</div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">{{ $trx->kendaraan?->plat_nomor }}</div>
                                    <div class="text-xs text-zinc-500">{{ $trx->kendaraan?->merek }}</div>
                                </td>

                                <td class="px-4 py-4 align-top">
                                    <div class="font-medium text-zinc-900 dark:text-white">
                                        {{ $trx->tanggal_mulai->format('d M Y') }} &ndash; {{ $trx->tanggal_selesai->format('d M Y') }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $trx->tipe_sewa->label() }} ({{ $trx->durasi }} {{ $trx->tipe_sewa->value === 'bulanan' ? 'Bulan' : 'Hari' }})
                                    </div>
                                </td>

                                <td class="px-4 py-4 text-right align-top font-mono text-xs">
                                    <div class="font-semibold text-zinc-900 dark:text-white">Rp {{ number_format($trx->total_bayar, 0, ',', '.') }}</div>
                                    <div class="text-[11px] text-zinc-500">Pokok: Rp {{ number_format($trx->tarif_pokok, 0, ',', '.') }}</div>
                                    @if ($trx->biaya_listrik > 0)
                                        <div class="text-[11px] text-emerald-600 dark:text-emerald-400">Listrik: Rp {{ number_format($trx->biaya_listrik, 0, ',', '.') }}</div>
                                    @endif
                                </td>

                                <td class="px-4 py-4 text-right align-top font-mono text-xs">
                                    @if ($extCount > 0)
                                        <div class="font-semibold text-zinc-900 dark:text-white">
                                            {{ $extCount }}x Extend (+Rp {{ number_format($extFee, 0, ',', '.') }})
                                        </div>
                                        <div class="mt-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            Lunas
                                        </div>
                                    @else
                                        <span class="text-zinc-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-4 text-right align-top font-mono text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                    Rp {{ number_format($combinedTotal, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-zinc-500">
                                    Tidak ada transaksi penyewaan yang sesuai dengan filter laporan yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts::app>
