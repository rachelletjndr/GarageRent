<x-layouts::app :title="__('Dashboard - SIM-Garasi')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <!-- Welcome Header -->
        <div class="flex flex-col justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading size="xl">Welcome back, {{ $user?->name }}</flux:heading>
                <flux:text class="mt-1">
                    Smart Garage Rental &amp; Management System (SIM-Garasi)
                </flux:text>
            </div>

            @if ($user?->isAdmin())
                <div class="flex flex-wrap items-center gap-2">
                    <flux:button variant="subtle" :href="route('admin.reports.index')" wire:navigate data-test="dash-link-reports">
                        Reports
                    </flux:button>
                    <flux:button variant="subtle" :href="route('admin.extensions.index')" wire:navigate data-test="dash-link-extensions">
                        Extend Sewa
                    </flux:button>
                    <flux:button variant="primary" :href="route('admin.garages.index')" wire:navigate data-test="dash-link-garages">
                        + Add Garasi
                    </flux:button>
                </div>
            @endif
        </div>

        <!-- ========================================================================= -->
        <!-- ADMIN UNIFIED DASHBOARD                                                   -->
        <!-- ========================================================================= -->
        @if ($user?->isAdmin())
            <!-- 4 Executive KPI Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- KPI 1: Total Unit Garasi -->
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Total Garage Units</div>
                    <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white" data-test="admin-kpi-total-garages">
                        {{ $garageStats['total'] ?? 0 }} Unit
                    </div>
                    <div class="mt-1 text-xs text-zinc-500">
                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ $garageStats['available'] ?? 0 }} Available</span>
                        &bull;
                        <span class="font-semibold text-amber-600 dark:text-amber-400">{{ $garageStats['used'] ?? 0 }} Used</span>
                    </div>
                </div>

                <!-- KPI 2: Verifikasi KTP Penyewa -->
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">KTP Verification</div>
                    <div class="mt-2 text-3xl font-bold text-amber-600 dark:text-amber-400" data-test="admin-kpi-pending-verifications">
                        {{ $verificationStats['pending'] ?? 0 }} Pending
                    </div>
                    <div class="mt-1 text-xs text-zinc-500">
                        {{ $verificationStats['approved'] ?? 0 }} Approved &bull; {{ $verificationStats['rejected'] ?? 0 }} Rejected
                    </div>
                </div>

                <!-- KPI 3: Sewa Aktif & Perpanjangan -->
                <div class="rounded-xl border border-indigo-200 bg-indigo-50/40 p-5 shadow-xs dark:border-indigo-800/60 dark:bg-indigo-950/20">
                    <div class="text-xs font-medium uppercase tracking-wider text-indigo-700 dark:text-indigo-300">Active &amp; Extended</div>
                    <div class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400" data-test="admin-kpi-total-extensions">
                        {{ $extensionStats['total_extensions'] ?? 0 }}x Extend
                    </div>
                    <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-400">
                        {{ $extensionStats['active_rentals'] ?? 0 }} Active Rentals &bull; Rp {{ number_format($extensionStats['total_extension_revenue'] ?? 0, 0, ',', '.') }}
                    </div>
                </div>

                <!-- KPI 4: Total Pendapatan Akumulatif (IDR) -->
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-5 shadow-xs dark:border-emerald-800/60 dark:bg-emerald-950/20">
                    <div class="text-xs font-medium uppercase tracking-wider text-emerald-800 dark:text-emerald-300">Total Revenue (IDR)</div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-700 dark:text-emerald-400" data-test="admin-kpi-total-revenue">
                        Rp {{ number_format($financialMetrics['grand_total_pendapatan'] ?? 0, 0, ',', '.') }}
                    </div>
                    <div class="mt-1 text-xs text-emerald-700/80 dark:text-emerald-300/80">
                        New Bookings + Extensions
                    </div>
                </div>
            </div>

            <!-- Quick Navigation Hub -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a
                    href="{{ route('admin.verifications.index') }}"
                    wire:navigate
                    class="group flex flex-col justify-between rounded-xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-indigo-500"
                >
                    <div>
                        <h3 class="mt-1 text-base font-bold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                            Verifikasi KTP Penyewa
                        </h3>
                        <p class="mt-1 text-xs text-zinc-500">
                            Review 16 digit NIK KTP pendaftar baru, lalu approve atau reject dengan catatan singkat.
                        </p>
                    </div>
                    <div class="mt-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Review KTP &rarr;
                    </div>
                </a>

                <a
                    href="{{ route('admin.garages.index') }}"
                    wire:navigate
                    class="group flex flex-col justify-between rounded-xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-indigo-500"
                >
                    <div>
                        <h3 class="mt-1 text-base font-bold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                            Manage Garasi
                        </h3>
                        <p class="mt-1 text-xs text-zinc-500">
                            Add unit garasi otomatis (S001, C001, E001), atur daily rate, monthly rate (25x), &amp; tarif kWh.
                        </p>
                    </div>
                    <div class="mt-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Manage Units &rarr;
                    </div>
                </a>

                <a
                    href="{{ route('admin.extensions.index') }}"
                    wire:navigate
                    class="group flex flex-col justify-between rounded-xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-indigo-500"
                >
                    <div>
                        <h3 class="mt-1 text-base font-bold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                            Extend Masa Sewa
                        </h3>
                        <p class="mt-1 text-xs text-zinc-500">
                            Update tanggal selesai sewa aktif, cek hitungan biaya extend otomatis, dan upload bukti bayar.
                        </p>
                    </div>
                    <div class="mt-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        Extend Rental &rarr;
                    </div>
                </a>

                <a
                    href="{{ route('admin.reports.index') }}"
                    wire:navigate
                    class="group flex flex-col justify-between rounded-xl border border-zinc-200 bg-white p-5 shadow-xs transition hover:border-indigo-400 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-indigo-500"
                >
                    <div>
                        <h3 class="mt-1 text-base font-bold text-zinc-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400">
                            Reports &amp; Analytics
                        </h3>
                        <p class="mt-1 text-xs text-zinc-500">
                            Pantau laporan transaksi bulanan, harian, per tipe garasi, dan per penyewa.
                        </p>
                    </div>
                    <div class="mt-4 text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                        View Reports &rarr;
                    </div>
                </a>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- GARAGE INVENTORY SUMMARY (SHARED BY ADMIN & APPROVED TENANT)              -->
        <!-- ========================================================================= -->
        @if (isset($garageStats))
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <flux:heading size="lg">Garage Availability Overview</flux:heading>
                        <flux:text class="mt-0.5 text-xs">
                            Live status ketersediaan unit Garasi Sepeda (S), Mobil Bensin (C), dan Mobil Listrik (E).
                        </flux:text>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-3">
                    @foreach ($garageStats['by_type'] as $typeKey => $stat)
                        @php
                            $occupancyPct = $stat['total'] > 0 ? round(($stat['used'] / $stat['total']) * 100) : 0;
                        @endphp
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $stat['label'] }}</span>
                                <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400">
                                    Prefix {{ $stat['prefix'] }}
                                </span>
                            </div>
                            <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">
                                {{ $stat['available'] }} <span class="text-sm font-normal text-zinc-500">Available / {{ $stat['total'] }} Unit</span>
                            </div>

                            <!-- Occupancy Progress Bar -->
                            <div class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                                <div
                                    class="h-full rounded-full bg-indigo-600 transition-all dark:bg-indigo-500"
                                    style="width: {{ $occupancyPct }}%"
                                ></div>
                            </div>

                            <div class="mt-2 flex items-center justify-between text-xs text-zinc-500">
                                <span>In Use: <strong>{{ $stat['used'] }}</strong> unit</span>
                                <span>Remaining Quota: <strong>{{ $stat['remaining_quota'] }}</strong></span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- ADMIN INTEGRATED OPERATIONAL TABLES (ACTIVE RENTALS & PENDING KTP)        -->
        <!-- ========================================================================= -->
        @if ($user?->isAdmin())
            <div class="grid gap-6 lg:grid-cols-12">
                <!-- Left 7 Cols: Garasi yang Sedang Aktif Disewa (Direct Link to Extend) -->
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs lg:col-span-7 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5 dark:border-zinc-800">
                        <div>
                            <flux:heading size="lg">Active Garage Rentals</flux:heading>
                            <flux:text class="mt-0.5 text-xs">
                                Shortcut cepat buat extend masa sewa garasi yang sedang aktif berjalan.
                            </flux:text>
                        </div>
                        <flux:button size="sm" variant="subtle" :href="route('admin.extensions.index')" wire:navigate>
                            View All
                        </flux:button>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                                <tr>
                                    <th class="px-4 py-3">Garage</th>
                                    <th class="px-4 py-3">Tenant &amp; Vehicle</th>
                                    <th class="px-4 py-3">Rental Period</th>
                                    <th class="px-4 py-3 text-right">Quick Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                                @forelse ($activeRentals->take(5) as $rental)
                                    <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                        <td class="px-4 py-3 align-top">
                                            <span class="text-sm font-bold text-indigo-600 dark:text-indigo-400">{{ $rental->garasi_id }}</span>
                                            <div class="text-[11px] text-zinc-500">{{ $rental->nomor_transaksi }}</div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-zinc-900 dark:text-white">{{ $rental->penyewa?->nama_lengkap }}</div>
                                            <div class="text-xs text-zinc-500">{{ $rental->kendaraan?->plat_nomor ?? 'Tanpa Plat' }} ({{ $rental->kendaraan?->merek }})</div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="text-xs text-zinc-500">{{ $rental->tanggal_mulai->format('d/m/Y') }} &ndash;</div>
                                            <div class="font-semibold text-emerald-700 dark:text-emerald-400">{{ $rental->tanggal_selesai->format('d/m/Y') }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right align-top">
                                            <a
                                                href="{{ route('admin.extensions.index', ['penyewaan_id' => $rental->id]) }}"
                                                wire:navigate
                                                data-test="dash-extend-rental-{{ $rental->garasi_id }}"
                                                class="inline-flex items-center rounded-lg border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300"
                                            >
                                                Extend
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-xs text-zinc-500">
                                            Belum ada transaksi sewa garasi yang aktif saat ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Right 5 Cols: Antrean Verifikasi KTP Calon Penyewa (Pending) -->
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs lg:col-span-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between border-b border-zinc-200 p-5 dark:border-zinc-800">
                        <div>
                            <flux:heading size="lg">Pending KTP Queue</flux:heading>
                            <flux:text class="mt-0.5 text-xs">
                                Pendaftar baru yang menunggu approval KTP dari Admin.
                            </flux:text>
                        </div>
                        <flux:button size="sm" variant="subtle" :href="route('admin.verifications.index')" wire:navigate>
                            Manage KTP
                        </flux:button>
                    </div>

                    <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($pendingTenants as $candidate)
                            <div class="flex items-center justify-between gap-3 p-4 hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                <div>
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $candidate->nama_lengkap }}</div>
                                    <div class="text-xs text-zinc-500">NIK: {{ $candidate->nik }}</div>
                                    <div class="text-[11px] text-zinc-400">{{ $candidate->user?->email }} &bull; {{ $candidate->no_telp }}</div>
                                </div>
                                <a
                                    href="{{ route('admin.verifications.index', ['status' => 'pending', 'search' => $candidate->nik]) }}"
                                    wire:navigate
                                    class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 transition hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                                >
                                    Review
                                </a>
                            </div>
                        @empty
                            <div class="p-6 text-center text-xs text-zinc-500">
                                All clear! Semua pendaftaran penyewa sudah diverifikasi.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <!-- ========================================================================= -->
        <!-- TENANT UNIFIED DASHBOARD (APPROVED PENYEWA)                               -->
        <!-- ========================================================================= -->
        @if ($penyewa)
            @if ($tenantSummary)
                <!-- 4 Tenant KPI Cards -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Active Garages</div>
                        <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400" data-test="tenant-dash-active-garages">
                            {{ $tenantSummary['active_count'] }} Unit
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">Bisa sewa lebih dari 1 garasi sekaligus</div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Total Bookings &amp; Vehicles</div>
                        <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">
                            {{ $tenantSummary['total_transactions'] }} Sewa
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">{{ $tenantSummary['vehicle_count'] }} kendaraan tersimpan</div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Extension History</div>
                        <div class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                            {{ $tenantSummary['extension_count'] }}x Extend
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">
                            Total Extend: Rp {{ number_format($tenantSummary['total_extension_fee'], 0, ',', '.') }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Total Spent (IDR)</div>
                        <div class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-400">
                            Rp {{ number_format($tenantSummary['grand_total_spent'], 0, ',', '.') }}
                        </div>
                        <div class="mt-1 text-xs text-zinc-500">New Bookings + Extensions</div>
                    </div>
                </div>
            @endif

            <!-- Tenant Active Rentals & Lease Duration Monitoring Table -->
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:heading size="lg">My Active Garages &amp; Lease Status</flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Daftar unit garasi yang sedang kamu sewa beserta tanggal berakhir terbarunya.
                    </flux:text>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Transaction ID</th>
                                <th class="px-4 py-3">Garage Unit</th>
                                <th class="px-4 py-3">Vehicle</th>
                                <th class="px-4 py-3">Lease Period</th>
                                <th class="px-4 py-3 text-right">Amount (IDR)</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @forelse ($tenantActiveRentals as $activeRental)
                                <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="tenant-dash-rental-{{ $activeRental->garasi_id }}">
                                    <td class="px-4 py-3.5 text-xs font-bold text-zinc-900 dark:text-white">
                                        {{ $activeRental->nomor_transaksi }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-base font-bold text-indigo-600 dark:text-indigo-400">{{ $activeRental->garasi_id }}</span>
                                        <div class="text-xs text-zinc-500">{{ $activeRental->garasi?->tipe_garasi?->label() }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="text-xs font-bold text-zinc-900 dark:text-white">{{ $activeRental->kendaraan?->plat_nomor ?? 'Tanpa Plat (Sepeda)' }}</div>
                                        <div class="text-xs text-zinc-500">{{ $activeRental->kendaraan?->merek }}</div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-semibold text-zinc-900 dark:text-white">
                                            {{ $activeRental->tanggal_mulai->format('d M Y') }} &ndash; {{ $activeRental->tanggal_selesai->format('d M Y') }}
                                        </div>
                                        <div class="text-xs text-zinc-500">
                                            {{ $activeRental->tipe_sewa->label() }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 text-right text-xs font-semibold text-zinc-900 dark:text-white">
                                        Rp {{ number_format($activeRental->total_bayar, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            Active
                                        </span>
                                        @if ($activeRental->perpanjanganSewa->isNotEmpty())
                                            <div class="mt-1 text-[11px] font-medium text-indigo-600 dark:text-indigo-400">
                                                Diperpanjang +{{ $activeRental->perpanjanganSewa->sum('selisih_hari') }} Hari (Lunas)
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-xs text-zinc-500">
                                        Kamu belum punya sewa garasi yang aktif. Yuk book garasi baru lewat menu di kiri atas!
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tenant Verified Profile Card -->
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-4 dark:border-zinc-800">
                    <div>
                        <flux:heading size="lg">Verified KTP Profile</flux:heading>
                        <flux:text class="mt-0.5 text-sm">Data identitas KTP kamu yang terdaftar untuk transaksi sewa garasi</flux:text>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ $penyewa->status_verifikasi->label() }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <div class="text-xs text-zinc-500">NIK KTP (16 Digits)</div>
                        <div class="mt-1 text-base font-bold text-zinc-900 dark:text-white">{{ $penyewa->nik }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Full Name</div>
                        <div class="mt-1 font-semibold text-zinc-900 dark:text-white">{{ $penyewa->nama_lengkap }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Phone / WhatsApp</div>
                        <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $penyewa->no_telp }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
