<x-layouts::app :title="__('Riwayat & Masa Sewa Garasi Saya')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">My Rentals &amp; Lease History</flux:heading>
                <flux:text class="mt-1">
                    Pantau semua garasi yang sedang kamu sewa beserta rincian pembayaran dan tanggal berakhirnya.
                </flux:text>
            </div>
            <flux:button variant="primary" :href="route('tenant.rentals.create')" wire:navigate data-test="create-rental-link">
                + Book Garasi Baru
            </flux:button>
        </div>

        <!-- Flash Notification -->
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-200" data-test="rental-status-alert">
                {{ session('status') }}
            </div>
        @endif

        <!-- Extension Rule Notice (BR-12 / SRS Section 5) -->
        <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-4 text-xs text-indigo-900 dark:border-indigo-800/60 dark:bg-indigo-950/40 dark:text-indigo-200">
            <strong>Need to Extend?</strong> Kalau kamu mau perpanjang masa sewa garasi yang masih aktif, cukup <strong>hubungi Admin</strong> ya. Begitu diproses Admin, tanggal berakhir sewa kamu bakal otomatis ter-update di halaman ini.
        </div>

        <!-- Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Garasi Aktif Disewa</div>
                <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400" data-test="active-rental-count">{{ $activeCount }} Unit</div>
                <div class="mt-1 text-xs text-zinc-500">Mendukung multi-sewa bersamaan</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Total Transaksi Sewa</div>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">{{ $rentals->total() }}</div>
                <div class="mt-1 text-xs text-zinc-500">Riwayat pemesanan Anda</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Akumulasi Total Bayar (IDR)</div>
                <div class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    Rp {{ number_format($totalSpent, 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-zinc-500">Total biaya sewa baru</div>
            </div>
        </div>

        <!-- Rental Transactions Table -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3.5">No. Transaksi</th>
                            <th class="px-4 py-3.5">Unit Garasi</th>
                            <th class="px-4 py-3.5">Kendaraan</th>
                            <th class="px-4 py-3.5">Masa Sewa & Durasi</th>
                            <th class="px-4 py-3.5">Rincian Biaya (IDR)</th>
                            <th class="px-4 py-3.5">Bukti Bayar</th>
                            <th class="px-4 py-3.5">Status Sewa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($rentals as $rental)
                            @php
                                $initialPayment = $rental->getBuktiBayarSewaBaru();
                            @endphp
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="rental-row-{{ $rental->id }}">
                                <!-- Nomor Transaksi -->
                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                        {{ $rental->nomor_transaksi }}
                                    </div>
                                    <div class="mt-1 text-[11px] text-zinc-400">
                                        Dibuat: {{ $rental->created_at?->format('d/m/Y H:i') ?? '-' }}
                                    </div>
                                </td>

                                <!-- Unit Garasi -->
                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-base font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $rental->garasi_id }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $rental->garasi?->tipe_garasi?->label() ?? '-' }}
                                    </div>
                                </td>

                                <!-- Kendaraan -->
                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                        {{ $rental->kendaraan?->plat_nomor }}
                                    </div>
                                    <div class="text-xs text-zinc-600 dark:text-zinc-300">
                                        {{ $rental->kendaraan?->merek }}
                                    </div>
                                    <div class="text-[11px] text-zinc-400">
                                        {{ $rental->kendaraan?->tipe_kendaraan?->label() }}
                                        @if ($rental->kendaraan?->isElectricVehicle())
                                            ({{ number_format((float) $rental->kendaraan->kapasitas_kwh, 1, ',', '.') }} kWh)
                                        @endif
                                    </div>
                                </td>

                                <!-- Masa Sewa -->
                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-zinc-900 dark:text-white" data-test="tenant-rental-period-{{ $rental->id }}">
                                        {{ $rental->tanggal_mulai->format('d M Y') }} &ndash; {{ $rental->tanggal_selesai->format('d M Y') }}
                                    </div>
                                    <div class="mt-0.5 text-xs text-zinc-500">
                                        @if ($rental->tipe_sewa->value === 'bulanan')
                                            Sewa Bulanan (1 Bulan Kalender)
                                        @else
                                            Sewa Harian ({{ $rental->durasi }} Hari Awal)
                                        @endif
                                    </div>
                                    @if ($rental->perpanjanganSewa->isNotEmpty())
                                        @php
                                            $latestExt = $rental->perpanjanganSewa->sortByDesc('id')->first();
                                            $totalAddedDays = $rental->perpanjanganSewa->sum('selisih_hari');
                                            $totalExtPaid = $rental->perpanjanganSewa->sum('biaya_perpanjangan');
                                        @endphp
                                        <div class="mt-1.5 rounded-md border border-indigo-200 bg-indigo-50/70 px-2 py-1 text-[11px] text-indigo-800 dark:border-indigo-800/60 dark:bg-indigo-950/40 dark:text-indigo-300" data-test="tenant-extension-badge-{{ $rental->id }}">
                                            <div class="font-semibold">Diperpanjang +{{ $totalAddedDays }} Hari oleh Admin</div>
                                            <div>
                                                Selesai Baru: <strong>{{ $latestExt->tanggal_selesai_baru->format('d M Y') }}</strong>
                                                | Biaya Extend: <strong class="text-emerald-700 dark:text-emerald-400">Rp {{ number_format($totalExtPaid, 0, ',', '.') }} (Lunas)</strong>
                                            </div>
                                        </div>
                                    @endif
                                </td>

                                <!-- Rincian Biaya -->
                                <td class="px-4 py-4 align-top">
                                    <div class="text-xs text-zinc-500">
                                        Pokok: Rp {{ number_format($rental->tarif_pokok, 0, ',', '.') }}
                                    </div>
                                    @if ($rental->biaya_listrik > 0)
                                        <div class="text-xs text-emerald-600 dark:text-emerald-400">
                                            Listrik EV: Rp {{ number_format($rental->biaya_listrik, 0, ',', '.') }}
                                        </div>
                                    @endif
                                    @if ($rental->perpanjanganSewa->isNotEmpty())
                                        <div class="text-xs text-indigo-600 dark:text-indigo-400">
                                            Extend: Rp {{ number_format($rental->perpanjanganSewa->sum('biaya_perpanjangan'), 0, ',', '.') }}
                                        </div>
                                    @endif
                                    <div class="mt-1 font-bold text-zinc-900 dark:text-white">
                                        Total: Rp {{ number_format($rental->total_bayar + $rental->perpanjanganSewa->sum('biaya_perpanjangan'), 0, ',', '.') }}
                                    </div>
                                </td>

                                <!-- Bukti Bayar -->
                                <td class="px-4 py-4 align-top">
                                    @if ($initialPayment)
                                        <div class="font-mono text-[11px] text-zinc-500">{{ $initialPayment->nomor_pembayaran }}</div>
                                        <a
                                            href="{{ route('payments.proof', $initialPayment) }}"
                                            target="_blank"
                                            class="mt-1 inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300"
                                        >
                                            Bukti Terunggah
                                        </a>
                                    @else
                                        <span class="text-xs text-zinc-400">-</span>
                                    @endif
                                </td>

                                <!-- Status Sewa -->
                                <td class="px-4 py-4 align-top">
                                    @if ($rental->isActive())
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            Aktif Disewa
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                                            {{ $rental->status_sewa->label() }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500">
                                    Anda belum memiliki transaksi penyewaan garasi. Klik tombol <strong>+ Sewa Garasi Baru</strong> untuk memulai pemesanan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rentals->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $rentals->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
