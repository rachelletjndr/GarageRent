<x-layouts::app :title="__('Manajemen Garasi')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">Manage Garasi</flux:heading>
                <flux:text class="mt-1">
                    Atur unit garasi (S001–S999, C001–C999, E001–E999), set daily rate &amp; tarif listrik kWh (IDR). Monthly rate otomatis 25x daily rate.
                </flux:text>
            </div>
            <flux:badge color="indigo" size="lg">
                Total Unit: {{ $statistics['total'] }} Garasi
            </flux:badge>
        </div>

        <!-- Flash Notification -->
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-200" data-test="garage-status-alert">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/50 dark:text-red-200" data-test="garage-error-alert">
                <div class="font-semibold">Terdapat kesalahan pada input formulir:</div>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Quota & Inventory Summary Cards per Type (BR-08 & BR-09) -->
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($statistics['by_type'] as $typeKey => $typeStat)
                <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900" data-test="quota-card-{{ $typeKey }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ $typeStat['label'] }}
                        </span>
                        <span class="rounded-md bg-zinc-100 px-2 py-0.5 font-mono text-xs font-bold text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
                            Prefix: {{ $typeStat['prefix'] }}
                        </span>
                    </div>

                    <div class="mt-3 flex items-baseline justify-between">
                        <div>
                            <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $typeStat['total'] }}</span>
                            <span class="text-xs text-zinc-500"> / 999 unit</span>
                        </div>
                        <div class="text-right text-xs">
                            <div class="text-zinc-500">ID Berikutnya:</div>
                            <div class="font-mono font-bold text-indigo-600 dark:text-indigo-400">{{ $typeStat['next_id'] }}</div>
                        </div>
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-zinc-100 pt-3 text-xs dark:border-zinc-800">
                        <span class="text-emerald-600 dark:text-emerald-400">
                            <strong>{{ $typeStat['available'] }}</strong> Tersedia
                        </span>
                        <span class="text-amber-600 dark:text-amber-400">
                            <strong>{{ $typeStat['used'] }}</strong> Digunakan
                        </span>
                        <span class="text-zinc-500">
                            Sisa Kuota: <strong>{{ $typeStat['remaining_quota'] }}</strong>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Bulk Garage Creation Form (FR-04, BR-03, BR-08, BR-09, BR-10) -->
        <div
            x-data="{
                tipe: '{{ old('tipe_garasi', 'sepeda') }}',
                harian: {{ is_numeric(old('harga_sewa_harian')) ? (float) old('harga_sewa_harian') : 20000 }},
                get bulanan() {
                    const val = Number(this.harian) || 0;
                    return val * 25;
                },
                formatRupiah(angka) {
                    return 'Rp ' + Number(angka).toLocaleString('id-ID');
                }
            }"
            class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="border-b border-zinc-200 pb-4 dark:border-zinc-800">
                <flux:heading size="lg">Add Garasi</flux:heading>
                <flux:text class="mt-0.5 text-sm">
                    Garasi baru akan mendapatkan nomor secara otomatis sesuai jenis garasi, dengan status awal tersedia.
                </flux:text>
            </div>

            <form method="POST" action="{{ route('admin.garages.store') }}" class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
                @csrf

                <!-- Tipe Garasi -->
                <div>
                    <label for="tipe_garasi" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        Tipe Garasi <span class="text-red-500">*</span>
                    </label>
                    <select
                        id="tipe_garasi"
                        name="tipe_garasi"
                        x-model="tipe"
                        required
                        class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        data-test="bulk-tipe-garasi"
                    >
                        <option value="sepeda">Sepeda / Motor (Prefix S)</option>
                        <option value="mobil">Mobil Bensin (Prefix C)</option>
                        <option value="mobil_listrik">Mobil Listrik / EV (Prefix E)</option>
                    </select>
                </div>

                <!-- Jumlah Unit -->
                <div>
                    <label for="jumlah" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        Jumlah Unit (Maks 999) <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="jumlah"
                        type="number"
                        name="jumlah"
                        value="{{ old('jumlah', 1) }}"
                        min="1"
                        max="999"
                        required
                        class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        data-test="bulk-jumlah"
                    />
                </div>

                <!-- Harga Sewa Harian (IDR) -->
                <div>
                    <label for="harga_sewa_harian" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        Harga Sewa Harian (IDR) <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="harga_sewa_harian"
                        type="number"
                        name="harga_sewa_harian"
                        x-model="harian"
                        min="1000"
                        step="500"
                        required
                        class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        data-test="bulk-harga-harian"
                    />
                </div>

                <!-- Harga Sewa Bulanan (Read-Only Otomatis 25x Harian - BR-03) -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        Harga Bulanan (25x Harian)
                    </label>
                    <div
                        class="mt-1.5 flex h-[38px] items-center rounded-lg border border-zinc-200 bg-zinc-100 px-3 text-sm font-bold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300"
                        data-test="bulk-harga-bulanan-readonly"
                        x-text="formatRupiah(bulanan)"
                    >
                        Rp 500.000
                    </div>
                    <div class="mt-1 text-[11px] text-zinc-500">Read-only (Dihitung otomatis)</div>
                </div>

                <!-- Harga Listrik per kWh (Khusus Mobil Listrik - BR-10) -->
                <div>
                    <label for="harga_kwh" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                        Tarif Listrik / kWh (IDR)
                        <span x-show="tipe === 'mobil_listrik'" class="text-red-500">*</span>
                    </label>
                    <input
                        id="harga_kwh"
                        type="number"
                        name="harga_kwh"
                        value="{{ old('harga_kwh') }}"
                        min="100"
                        step="100"
                        :disabled="tipe !== 'mobil_listrik'"
                        :required="tipe === 'mobil_listrik'"
                        placeholder="Khusus Garasi EV (mis: 2000)"
                        class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none disabled:cursor-not-allowed disabled:bg-zinc-100 disabled:text-zinc-400 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:disabled:bg-zinc-800/40"
                        data-test="bulk-harga-kwh"
                    />
                </div>

                <div class="sm:col-span-2 lg:col-span-5 flex justify-end border-t border-zinc-100 pt-3 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" data-test="bulk-create-submit">
                        Add Garasi
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex flex-col justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs lg:flex-row lg:items-center dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $tipeTabs = [
                        'all' => 'Semua Tipe (' . $statistics['total'] . ')',
                        'sepeda' => 'Sepeda - S (' . $statistics['by_type']['sepeda']['total'] . ')',
                        'mobil' => 'Mobil - C (' . $statistics['by_type']['mobil']['total'] . ')',
                        'mobil_listrik' => 'Mobil Listrik - E (' . $statistics['by_type']['mobil_listrik']['total'] . ')',
                    ];
                @endphp
                @foreach ($tipeTabs as $key => $label)
                    <a
                        href="{{ route('admin.garages.index', ['tipe' => $key === 'all' ? null : $key, 'status' => $statusFilter === 'all' ? null : $statusFilter, 'search' => $search ?: null]) }}"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $tipeFilter === $key ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.garages.index') }}" class="flex flex-wrap items-center gap-2">
                @if ($tipeFilter !== 'all')
                    <input type="hidden" name="tipe" value="{{ $tipeFilter }}" />
                @endif

                <select
                    name="status"
                    class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                >
                    <option value="" @selected($statusFilter === 'all')>Semua Status</option>
                    <option value="available" @selected($statusFilter === 'available')>Available ({{ $statistics['available'] }})</option>
                    <option value="used" @selected($statusFilter === 'used')>Used ({{ $statistics['used'] }})</option>
                </select>

                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari ID (mis: S001, E001)..."
                    class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 focus:border-zinc-500 focus:outline-none sm:w-48 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    data-test="search-garage-input"
                />

                <flux:button type="submit" size="sm" variant="primary">
                    Filter
                </flux:button>
            </form>
        </div>

        <!-- Garage Inventory Table -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3.5">Kode ID</th>
                            <th class="px-4 py-3.5">Tipe Garasi & Peruntukan</th>
                            <th class="px-4 py-3.5">Harga Sewa Harian (IDR)</th>
                            <th class="px-4 py-3.5">Harga Bulanan (25x Harian - Read Only)</th>
                            <th class="px-4 py-3.5">Tarif Listrik / kWh</th>
                            <th class="px-4 py-3.5">Status</th>
                            <th class="px-4 py-3.5 text-right">Pembaruan Tarif & Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($garages as $garasi)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="garage-row-{{ $garasi->id }}">
                                <!-- Kode ID -->
                                <td class="px-4 py-3.5 font-mono text-base font-bold text-zinc-900 dark:text-white">
                                    {{ $garasi->id }}
                                </td>

                                <!-- Tipe Garasi -->
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-zinc-900 dark:text-white">
                                        {{ $garasi->tipe_garasi->label() }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        @if ($garasi->tipe_garasi->value === 'sepeda')
                                            Kendaraan: Sepeda / Sepeda Motor
                                        @elseif ($garasi->tipe_garasi->value === 'mobil')
                                            Kendaraan: Mobil Bensin / Mobil Listrik (Tanpa Charging)
                                        @else
                                            Kendaraan: Mobil Listrik (Wajib Isi Daya Penuh)
                                        @endif
                                    </div>
                                </td>

                                <!-- Harga Harian -->
                                <td class="px-4 py-3.5 font-semibold text-zinc-900 dark:text-white">
                                    Rp {{ number_format($garasi->harga_sewa_harian, 0, ',', '.') }}
                                </td>

                                <!-- Harga Bulanan (Read-Only 25x Harian) -->
                                <td class="px-4 py-3.5">
                                    <div class="font-semibold text-indigo-700 dark:text-indigo-300">
                                        Rp {{ number_format($garasi->getHargaSewaBulanan(), 0, ',', '.') }}
                                    </div>
                                    <div class="text-[11px] text-zinc-400">Otomatis: 25 &times; Harian</div>
                                </td>

                                <!-- Tarif Listrik -->
                                <td class="px-4 py-3.5">
                                    @if ($garasi->isElectricGarage() && $garasi->tarif_kwh !== null)
                                        <span class="font-medium text-emerald-700 dark:text-emerald-300">
                                            Rp {{ number_format($garasi->tarif_kwh, 0, ',', '.') }} / kWh
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">Tidak Ada Charging</span>
                                    @endif
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-3.5">
                                    @if ($garasi->isAvailable())
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            available (Tersedia)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            used (Digunakan)
                                        </span>
                                    @endif
                                </td>

                                <!-- Pembaruan Tarif & Status -->
                                <td class="px-4 py-3.5 text-right">
                                    <form method="POST" action="{{ route('admin.garages.update', $garasi) }}" class="inline-flex flex-wrap items-center justify-end gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <input
                                            type="number"
                                            name="harga_sewa_harian"
                                            value="{{ (int) $garasi->harga_sewa_harian }}"
                                            min="1000"
                                            step="500"
                                            title="Harga Sewa Harian (IDR)"
                                            class="w-24 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                        />
                                        @if ($garasi->isElectricGarage())
                                            <input
                                                type="number"
                                                name="harga_kwh"
                                                value="{{ (int) $garasi->tarif_kwh }}"
                                                min="100"
                                                step="100"
                                                title="Tarif Listrik / kWh (IDR)"
                                                class="w-20 rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                            />
                                        @endif
                                        <select
                                            name="status"
                                            title="Status Operasional"
                                            class="rounded-md border border-zinc-300 bg-white px-2 py-1 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                        >
                                            <option value="available" @selected($garasi->isAvailable())>available</option>
                                            <option value="used" @selected($garasi->isUsed())>used</option>
                                        </select>
                                        <flux:button type="submit" size="sm" variant="subtle" data-test="update-garage-{{ $garasi->id }}">
                                            Simpan
                                        </flux:button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500">
                                    Belum ada unit garasi yang sesuai dengan filter pencarian.
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
