<x-layouts::app :title="__('Katalog & Daftar Tarif Garasi')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">Katalog Unit & Daftar Tarif Resmi Garasi</flux:heading>
                <flux:text class="mt-1">
                    Cek pilihan unit garasi lengkap dengan daily rate, monthly rate (25x harian), tarif EV charging (IDR), dan status availability-nya.
                </flux:text>
            </div>
            <flux:badge color="emerald" size="lg">
                Available: {{ $statistics['available'] }} / {{ $statistics['total'] }} Unit
            </flux:badge>
        </div>

        <!-- Compatibility Rules Guidance Banner (BR-01 & BR-02) -->
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="font-mono text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400">Kode S001 – S999</div>
                <div class="mt-1 font-semibold text-zinc-900 dark:text-white">Garasi Sepeda / Motor</div>
                <p class="mt-1 text-xs text-zinc-500">Khusus untuk kendaraan Sepeda dan Sepeda Motor. Tersedia: <strong>{{ $statistics['by_type']['sepeda']['available'] }} unit</strong>.</p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="font-mono text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400">Kode C001 – C999</div>
                <div class="mt-1 font-semibold text-zinc-900 dark:text-white">Garasi Mobil Bensin</div>
                <p class="mt-1 text-xs text-zinc-500">Untuk Mobil Bensin maupun Mobil Listrik (tanpa fasilitas charging listrik). Tersedia: <strong>{{ $statistics['by_type']['mobil']['available'] }} unit</strong>.</p>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="font-mono text-xs font-bold uppercase text-indigo-600 dark:text-indigo-400">Kode E001 – E999</div>
                <div class="mt-1 font-semibold text-zinc-900 dark:text-white">Garasi Mobil Listrik (EV)</div>
                <p class="mt-1 text-xs text-zinc-500">Khusus Mobil Listrik dengan fasilitas EV Charging penuh (Biaya = Kapasitas kWh &times; Tarif/kWh). Tersedia: <strong>{{ $statistics['by_type']['mobil_listrik']['available'] }} unit</strong>.</p>
            </div>
        </div>

        <!-- Filter & Search -->
        <div class="flex flex-col justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap gap-2">
                @php
                    $tabs = [
                        'all' => 'Semua Tipe',
                        'sepeda' => 'Garasi Sepeda (S)',
                        'mobil' => 'Garasi Mobil (C)',
                        'mobil_listrik' => 'Garasi Mobil Listrik (E)',
                    ];
                @endphp
                @foreach ($tabs as $key => $label)
                    <a
                        href="{{ route('tenant.garages.index', ['tipe' => $key === 'all' ? null : $key, 'status' => $statusFilter === 'all' ? null : $statusFilter]) }}"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $tipeFilter === $key ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('tenant.garages.index') }}" class="flex items-center gap-2">
                @if ($tipeFilter !== 'all')
                    <input type="hidden" name="tipe" value="{{ $tipeFilter }}" />
                @endif
                <select
                    name="status"
                    class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                >
                    <option value="" @selected($statusFilter === 'all')>Semua Status</option>
                    <option value="available" @selected($statusFilter === 'available')>Hanya yang Tersedia (Available)</option>
                    <option value="used" @selected($statusFilter === 'used')>Sedang Digunakan (Used)</option>
                </select>
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari Kode ID..."
                    class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 sm:w-40 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                />
                <flux:button type="submit" size="sm" variant="primary">
                    Cari
                </flux:button>
            </form>
        </div>

        <!-- Read-only Garage List -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3.5">Kode ID Garasi</th>
                            <th class="px-4 py-3.5">Tipe Garasi</th>
                            <th class="px-4 py-3.5">Harga Sewa Harian (IDR)</th>
                            <th class="px-4 py-3.5">Harga Sewa Bulanan (IDR)</th>
                            <th class="px-4 py-3.5">Tarif Listrik / kWh (IDR)</th>
                            <th class="px-4 py-3.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($garages as $garasi)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                <td class="px-4 py-3.5 font-mono text-base font-bold text-zinc-900 dark:text-white">
                                    {{ $garasi->id }}
                                </td>
                                <td class="px-4 py-3.5 font-medium text-zinc-900 dark:text-white">
                                    {{ $garasi->tipe_garasi->label() }}
                                </td>
                                <td class="px-4 py-3.5 font-semibold text-zinc-900 dark:text-white">
                                    Rp {{ number_format($garasi->harga_sewa_harian, 0, ',', '.') }} / hari
                                </td>
                                <td class="px-4 py-3.5 font-semibold text-indigo-700 dark:text-indigo-300">
                                    Rp {{ number_format($garasi->getHargaSewaBulanan(), 0, ',', '.') }} / bulan
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($garasi->isElectricGarage() && $garasi->tarif_kwh !== null)
                                        <span class="font-medium text-emerald-700 dark:text-emerald-300">
                                            Rp {{ number_format($garasi->tarif_kwh, 0, ',', '.') }} / kWh
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($garasi->isAvailable())
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            Tersedia (Available)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            Sedang Digunakan (Used)
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-zinc-500">
                                    Tidak ada unit garasi yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($garages->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $garages->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
