<x-layouts::app :title="__('Perpanjangan Masa Sewa Garasi')">
    <div
        class="flex h-full w-full flex-1 flex-col gap-6"
        x-data="{
            rentals: @js($activeRentalsPayload),
            selectedRentalId: '{{ $initialRentalId }}',
            tanggalSelesaiBaru: '{{ old('tanggal_selesai_baru', '') }}',
            fileError: '',
            fileInfo: '',

            validateUploadFile(event) {
                this.fileError = '';
                this.fileInfo = '';
                const file = event.target.files?.[0];
                if (!file) return;

                const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const allowedExts = ['jpg', 'jpeg', 'png'];
                const maxBytes = 2 * 1024 * 1024; // 2 MB

                if (!allowedTypes.includes(file.type) && !allowedExts.includes(ext)) {
                    this.fileError = 'Format file tidak sesuai (' + (ext ? '.' + ext : 'tidak dikenal') + '). Harap unggah gambar berformat JPG, JPEG, atau PNG.';
                    return;
                }

                if (file.size > maxBytes) {
                    const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
                    this.fileError = 'Ukuran gambar terlalu besar (' + sizeMb + ' MB). Ukuran maksimal file yang diizinkan adalah 2 MB.';
                    return;
                }

                const sizeKb = Math.max(1, Math.round(file.size / 1024));
                this.fileInfo = 'File valid: ' + file.name + ' (' + sizeKb + ' KB)';
            },

            init() {
                if (this.selectedRentalId && !this.tanggalSelesaiBaru) {
                    const found = this.selectedRental;
                    if (found) {
                        this.tanggalSelesaiBaru = found.default_tanggal_selesai_baru;
                    }
                }
            },

            get selectedRental() {
                if (!this.selectedRentalId) {
                    return null;
                }
                return this.rentals.find(r => String(r.id) === String(this.selectedRentalId)) || null;
            },

            selectRental(rentalId) {
                this.selectedRentalId = String(rentalId);
                const found = this.selectedRental;
                if (found) {
                    this.tanggalSelesaiBaru = found.default_tanggal_selesai_baru;
                }
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },

            get selisihHari() {
                const rental = this.selectedRental;
                if (!rental || !this.tanggalSelesaiBaru) {
                    return 0;
                }
                const oldParts = rental.tanggal_selesai_lama.split('-').map(Number);
                const newParts = this.tanggalSelesaiBaru.split('-').map(Number);
                if (oldParts.length !== 3 || newParts.length !== 3) {
                    return 0;
                }
                const oldDate = Date.UTC(oldParts[0], oldParts[1] - 1, oldParts[2]);
                const newDate = Date.UTC(newParts[0], newParts[1] - 1, newParts[2]);
                const diffDays = Math.round((newDate - oldDate) / 86400000);
                return diffDays > 0 ? diffDays : 0;
            },

            get biayaPerpanjangan() {
                const rental = this.selectedRental;
                if (!rental || this.selisihHari <= 0) {
                    return 0;
                }
                return this.selisihHari * Number(rental.harga_sewa_harian);
            },

            formatRupiah(amount) {
                const absVal = Math.abs(Number(amount) || 0);
                const formatted = new Intl.NumberFormat('id-ID').format(absVal);
                return 'Rp ' + formatted;
            }
        }"
    >
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">Extend Masa Sewa Garasi</flux:heading>
                <flux:text class="mt-1">
                    Pilih garasi yang sedang aktif disewa, update tanggal selesai baru, dan upload bukti bayar extend-nya.
                </flux:text>
            </div>
        </div>

        <!-- Flash Notification -->
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-200" data-test="extension-status-alert">
                {{ session('status') }}
            </div>
        @endif

        <!-- Validation Errors Alert -->
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/50 dark:text-red-200" data-test="extension-error-alert">
                <div class="font-semibold">Terdapat kesalahan pada input perpanjangan sewa:</div>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Summary Cards -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Garasi Sedang Disewa Aktif</div>
                <div class="mt-2 text-3xl font-bold text-emerald-600 dark:text-emerald-400" data-test="stat-active-rentals">
                    {{ $statistics['active_rentals'] }} Unit
                </div>
                <div class="mt-1 text-xs text-zinc-500">Garasi aktif saat ini</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Total Transaksi Extend</div>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white" data-test="stat-total-extensions">
                    {{ $statistics['total_extensions'] }} Kali
                </div>
                <div class="mt-1 text-xs text-zinc-500">+{{ $statistics['total_extension_days'] }} hari tambahan masa sewa</div>
            </div>

            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500">Total Pembayaran Extend (Lunas)</div>
                <div class="mt-2 text-2xl font-bold text-indigo-600 dark:text-indigo-400">
                    Rp {{ number_format($statistics['total_extension_revenue'], 0, ',', '.') }}
                </div>
                <div class="mt-1 text-xs text-zinc-500">Akumulasi biaya perpanjangan tersimpan</div>
            </div>
        </div>

        <!-- Form & Simplified Cost Summary Grid -->
        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Left Column: Extension Form -->
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs lg:col-span-7 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 pb-4 dark:border-zinc-800">
                    <flux:heading size="lg">Formulir Perpanjangan Masa Sewa Garasi</flux:heading>
                    <flux:text class="mt-1 text-xs">
                        Pilih transaksi garasi yang sedang disewa aktif, tentukan Tanggal Selesai Baru, dan unggah bukti pembayaran perpanjangan dari penyewa.
                    </flux:text>
                </div>

                <form method="POST" action="{{ route('admin.extensions.store') }}" enctype="multipart/form-data" class="mt-5 space-y-5">
                    @csrf

                    <!-- Pilih Transaksi Garasi Aktif -->
                    <div>
                        <flux:label for="penyewaan_id">Pilih Garasi yang Sedang Disewa (Status Aktif)</flux:label>
                        <select
                            id="penyewaan_id"
                            name="penyewaan_id"
                            x-model="selectedRentalId"
                            @change="if (selectedRental) { tanggalSelesaiBaru = selectedRental.default_tanggal_selesai_baru; }"
                            required
                            data-test="select-active-rental"
                            class="mt-1.5 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-2xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        >
                            <option value="">-- Pilih Garasi & Penyewa Aktif --</option>
                            @foreach ($activeRentals as $activeRental)
                                <option value="{{ $activeRental->id }}">
                                    [{{ $activeRental->garasi_id }}] {{ $activeRental->nomor_transaksi }} &mdash; {{ $activeRental->penyewa?->nama_lengkap }} ({{ $activeRental->kendaraan?->plat_nomor }}) | Selesai: {{ $activeRental->tanggal_selesai->format('d/m/Y') }}
                                </option>
                            @endforeach
                        </select>
                        @error('penyewaan_id')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Selected Rental Snapshot Info -->
                    <template x-if="selectedRental">
                        <div class="grid gap-3 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-xs sm:grid-cols-2 dark:border-zinc-700 dark:bg-zinc-800/60">
                            <div>
                                <span class="text-zinc-500">Unit Garasi & Tipe:</span>
                                <div class="mt-0.5 font-mono text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                    <span x-text="selectedRental.garasi_id"></span> (<span x-text="selectedRental.tipe_garasi_label"></span>)
                                </div>
                            </div>
                            <div>
                                <span class="text-zinc-500">Tarif Sewa Harian Garasi:</span>
                                <div class="mt-0.5 font-semibold text-zinc-900 dark:text-white" x-text="formatRupiah(selectedRental.harga_sewa_harian) + ' / hari'"></div>
                            </div>
                            <div>
                                <span class="text-zinc-500">Penyewa & Kendaraan:</span>
                                <div class="mt-0.5 font-semibold text-zinc-900 dark:text-white">
                                    <span x-text="selectedRental.penyewa_nama"></span> &mdash; <span class="font-mono" x-text="selectedRental.kendaraan_plat"></span>
                                </div>
                            </div>
                            <div>
                                <span class="text-zinc-500">Tanggal Selesai Lama (Saat Ini):</span>
                                <div class="mt-0.5 font-mono text-sm font-bold text-amber-600 dark:text-amber-400" x-text="selectedRental.tanggal_selesai_lama_formatted + ' (' + selectedRental.tanggal_selesai_lama + ')'"></div>
                            </div>
                        </div>
                    </template>

                    <!-- Perubahan Tanggal Selesai Baru -->
                    <div>
                        <flux:label for="tanggal_selesai_baru">Tanggal Selesai Baru</flux:label>
                        <input
                            id="tanggal_selesai_baru"
                            type="date"
                            name="tanggal_selesai_baru"
                            x-model="tanggalSelesaiBaru"
                            :min="selectedRental ? selectedRental.min_tanggal_selesai_baru : ''"
                            required
                            data-test="input-tanggal-selesai-baru"
                            class="mt-1.5 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-2xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                        />
                        <p class="mt-1 text-xs text-zinc-500">
                            Setiap penambahan hari dihitung dengan tarif sewa harian garasi terkait.
                        </p>
                        @error('tanggal_selesai_baru')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Upload Bukti Pembayaran Perpanjangan -->
                    <div>
                        <div class="flex items-center justify-between">
                            <flux:label for="bukti_bayar_perpanjangan">Unggah Bukti Pembayaran Perpanjangan</flux:label>
                            <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">
                                Format: JPG, JPEG, PNG &bull; Maks. 2 MB
                            </span>
                        </div>
                        <input
                            id="bukti_bayar_perpanjangan"
                            type="file"
                            name="bukti_bayar_perpanjangan"
                            accept=".jpg,.jpeg,.png,image/jpeg,image/png,image/jpg"
                            @change="validateUploadFile($event)"
                            required
                            data-test="input-bukti-bayar-perpanjangan"
                            :class="fileError ? 'border-red-500 bg-red-50/40 dark:border-red-500 dark:bg-red-950/20' : 'border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-800'"
                            class="mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm text-zinc-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-zinc-200 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                        />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            Ketentuan upload: Hanya mendukung file gambar (<strong>.jpg, .jpeg, .png</strong>) dengan ukuran maksimal <strong>2 MB (2.048 KB)</strong>.
                        </p>

                        <!-- Real-time Client-side Error State -->
                        <div
                            x-show="fileError !== ''"
                            style="display: none;"
                            data-test="extension-file-error-state"
                            class="mt-2 rounded-lg border border-red-300 bg-red-50 p-3 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300"
                        >
                            <span x-text="fileError"></span>
                        </div>

                        <!-- Real-time Client-side Valid File Info -->
                        <div
                            x-show="fileInfo !== '' && fileError === ''"
                            style="display: none;"
                            class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50/70 px-3 py-2 text-xs font-medium text-emerald-700 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <span x-text="fileInfo"></span>
                        </div>

                        @error('bukti_bayar_perpanjangan')
                            <div class="mt-2 rounded-lg border border-red-300 bg-red-50 p-3 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                        <flux:button type="submit" variant="primary" x-bind:disabled="fileError !== ''" data-test="submit-extension-btn">
                            Simpan Perpanjangan Sewa
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- Right Column: Clean & Simplified Cost Information -->
            <div class="flex flex-col justify-between rounded-xl border border-zinc-200 bg-white p-6 shadow-xs lg:col-span-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div>
                    <div class="border-b border-zinc-200 pb-4 dark:border-zinc-800">
                        <flux:heading size="lg">Ringkasan Biaya Perpanjangan</flux:heading>
                    </div>

                    <div class="mt-5 space-y-3.5 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">Tanggal Selesai Lama:</span>
                            <span class="font-mono font-semibold text-zinc-900 dark:text-white" x-text="selectedRental ? selectedRental.tanggal_selesai_lama : '-'"></span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">Tanggal Selesai Baru:</span>
                            <span class="font-mono font-semibold text-indigo-600 dark:text-indigo-400" x-text="tanggalSelesaiBaru || '-'"></span>
                        </div>

                        <div class="flex items-center justify-between border-t border-zinc-200 pt-3 dark:border-zinc-800">
                            <span class="text-zinc-600 dark:text-zinc-400">Selisih Hari Tambahan:</span>
                            <span class="font-bold text-zinc-900 dark:text-white" data-test="calc-selisih-hari" x-text="selisihHari + ' Hari'"></span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">Tarif Sewa Harian:</span>
                            <span class="font-mono text-zinc-900 dark:text-white" x-text="selectedRental ? formatRupiah(selectedRental.harga_sewa_harian) : 'Rp 0'"></span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-zinc-600 dark:text-zinc-400">Biaya Perpanjangan:</span>
                            <span class="font-semibold text-zinc-900 dark:text-white" data-test="calc-biaya-perpanjangan" x-text="formatRupiah(biayaPerpanjangan)"></span>
                        </div>

                        <!-- Total Biaya Perpanjangan Box -->
                        <div class="mt-5 rounded-xl border border-indigo-200 bg-indigo-50/70 p-5 dark:border-indigo-800/70 dark:bg-indigo-950/40">
                            <div class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
                                Total Biaya Perpanjangan
                            </div>
                            <div
                                class="mt-1.5 font-mono text-3xl font-extrabold text-indigo-600 dark:text-indigo-400"
                                data-test="calc-total-biaya-perpanjangan"
                                x-text="formatRupiah(biayaPerpanjangan)"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 1: Daftar Garasi yang Sedang Disewa (Active Rentals Table) -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col justify-between gap-4 border-b border-zinc-200 p-5 sm:flex-row sm:items-center dark:border-zinc-800">
                <div>
                    <flux:heading size="lg">Daftar Garasi yang Sedang Disewa Aktif</flux:heading>
                    <flux:text class="mt-0.5 text-xs">
                        Klik tombol <strong>Pilih untuk Perpanjang</strong> pada salah satu baris garasi untuk memuat datanya ke formulir di atas.
                    </flux:text>
                </div>

                <!-- Filter & Search -->
                <form method="GET" action="{{ route('admin.extensions.index') }}" class="flex flex-wrap items-center gap-2">
                    <select
                        name="tipe_garasi"
                        class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    >
                        <option value="">Semua Tipe Garasi</option>
                        @foreach ($garageTypes as $type)
                            <option value="{{ $type->value }}" @selected($currentType === $type->value)>
                                {{ $type->label() }} ({{ $type->prefix() }})
                            </option>
                        @endforeach
                    </select>

                    <input
                        type="text"
                        name="search"
                        value="{{ $currentSearch }}"
                        placeholder="Cari kode garasi / penyewa / plat..."
                        class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    />

                    <flux:button type="submit" size="sm" variant="subtle">Filter</flux:button>
                    @if ($currentSearch || $currentType)
                        <flux:button size="sm" variant="ghost" :href="route('admin.extensions.index')" wire:navigate>Reset</flux:button>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3.5">Kode Garasi</th>
                            <th class="px-4 py-3.5">No. Transaksi</th>
                            <th class="px-4 py-3.5">Penyewa (NIK)</th>
                            <th class="px-4 py-3.5">Kendaraan</th>
                            <th class="px-4 py-3.5">Tarif Harian</th>
                            <th class="px-4 py-3.5">Masa Sewa Aktif</th>
                            <th class="px-4 py-3.5">Riwayat Extend</th>
                            <th class="px-4 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($activeRentals as $rental)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="active-rental-row-{{ $rental->garasi_id }}">
                                <td class="px-4 py-3.5 align-top">
                                    <span class="font-mono text-base font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $rental->garasi_id }}
                                    </span>
                                    <div class="text-xs text-zinc-500">
                                        {{ $rental->garasi?->tipe_garasi?->label() }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                        {{ $rental->nomor_transaksi }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500">
                                        {{ $rental->tipe_sewa->label() }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    <div class="font-semibold text-zinc-900 dark:text-white">
                                        {{ $rental->penyewa?->nama_lengkap }}
                                    </div>
                                    <div class="font-mono text-xs text-zinc-500">
                                        NIK: {{ $rental->penyewa?->nik }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                        {{ $rental->kendaraan?->plat_nomor }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        {{ $rental->kendaraan?->merek }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top font-mono text-xs font-semibold text-zinc-900 dark:text-white">
                                    Rp {{ number_format((float) ($rental->garasi?->harga_sewa_harian ?? 0), 0, ',', '.') }}/hari
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    <div class="text-xs text-zinc-500">
                                        Mulai: {{ $rental->tanggal_mulai->format('d M Y') }}
                                    </div>
                                    <div class="font-semibold text-emerald-700 dark:text-emerald-400" data-test="active-end-date-{{ $rental->garasi_id }}">
                                        Selesai: {{ $rental->tanggal_selesai->format('d M Y') }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    @if ($rental->perpanjanganSewa->isNotEmpty())
                                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                                            {{ $rental->perpanjanganSewa->count() }}x Diperpanjang
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">Belum pernah</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3.5 text-right align-top">
                                    <button
                                        type="button"
                                        @click="selectRental('{{ $rental->id }}')"
                                        data-test="pick-rental-{{ $rental->garasi_id }}"
                                        class="inline-flex cursor-pointer items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900/60"
                                    >
                                        Pilih untuk Perpanjang
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-sm text-zinc-500">
                                    Tidak ada transaksi garasi aktif yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Riwayat Perpanjangan Sewa & Bukti Pembayaran -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 p-5 dark:border-zinc-800">
                <flux:heading size="lg">Riwayat Perpanjangan Sewa &amp; Bukti Pembayaran</flux:heading>
                <flux:text class="mt-0.5 text-xs">
                    Daftar riwayat perpanjangan tanggal selesai sewa yang telah dibayar dan disimpan beserta bukti pembayarannya.
                </flux:text>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3.5">No. Perpanjangan</th>
                            <th class="px-4 py-3.5">Garasi &amp; Penyewa</th>
                            <th class="px-4 py-3.5">Perubahan Tanggal Selesai</th>
                            <th class="px-4 py-3.5">Selisih Hari</th>
                            <th class="px-4 py-3.5">Biaya Extend (Lunas)</th>
                            <th class="px-4 py-3.5">Bukti Pembayaran</th>
                            <th class="px-4 py-3.5">Diproses Oleh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($extensionHistory as $ext)
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="extension-history-row-{{ $ext->nomor_perpanjangan }}">
                                <td class="px-4 py-3.5 align-top">
                                    <div class="font-mono text-xs font-bold text-zinc-900 dark:text-white">
                                        {{ $ext->nomor_perpanjangan }}
                                    </div>
                                    <div class="text-[11px] text-zinc-500">
                                        Ref: {{ $ext->penyewaan?->nomor_transaksi }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    <div class="font-mono text-sm font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ $ext->penyewaan?->garasi_id }}
                                    </div>
                                    <div class="text-xs font-medium text-zinc-900 dark:text-white">
                                        {{ $ext->penyewaan?->penyewa?->nama_lengkap }}
                                    </div>
                                    <div class="font-mono text-[11px] text-zinc-500">
                                        {{ $ext->penyewaan?->kendaraan?->plat_nomor }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    <div class="text-xs text-zinc-500 line-through">
                                        Lama: {{ $ext->tanggal_selesai_lama->format('d M Y') }}
                                    </div>
                                    <div class="font-semibold text-emerald-700 dark:text-emerald-400">
                                        Baru: {{ $ext->tanggal_selesai_baru->format('d M Y') }}
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        +{{ $ext->selisih_hari }} Hari
                                    </span>
                                    <div class="mt-0.5 text-[11px] text-zinc-500">
                                        @ Rp {{ number_format($ext->tarif_harian_snapshot, 0, ',', '.') }}/hari
                                    </div>
                                </td>

                                <td class="px-4 py-3.5 align-top font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                                    Rp {{ number_format($ext->biaya_perpanjangan, 0, ',', '.') }}
                                </td>

                                <td class="px-4 py-3.5 align-top">
                                    @if ($ext->pembayaran)
                                        <div class="font-mono text-[11px] text-zinc-500">{{ $ext->pembayaran->nomor_pembayaran }}</div>
                                        <a
                                            href="{{ route('payments.proof', $ext->pembayaran) }}"
                                            target="_blank"
                                            class="mt-0.5 inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300"
                                        >
                                            Bukti Valid
                                        </a>
                                    @else
                                        <span class="text-xs text-zinc-400">-</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3.5 align-top text-xs text-zinc-600 dark:text-zinc-300">
                                    <div class="font-medium">{{ $ext->admin?->name ?? 'Admin' }}</div>
                                    <div class="text-[11px] text-zinc-400">{{ $ext->created_at?->format('d/m/Y H:i') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-zinc-500">
                                    Belum ada riwayat transaksi perpanjangan masa sewa garasi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($extensionHistory->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $extensionHistory->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
