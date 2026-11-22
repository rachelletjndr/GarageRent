<x-layouts::app :title="__('Sewa Garasi Baru')">
    <div
        x-data="{
            tanggalMulai: '{{ old('tanggal_mulai', $defaultTanggalMulai) }}',
            tipeSewa: '{{ old('tipe_sewa', 'harian') }}',
            durasiHari: {{ is_numeric(old('durasi_hari')) ? (int) old('durasi_hari') : 1 }},
            tipeKendaraan: '{{ old('tipe_kendaraan', 'sepeda_motor') }}',
            platNomor: '{{ old('plat_nomor', '') }}',
            merek: '{{ old('merek', '') }}',
            kapasitasKwh: '{{ old('kapasitas_kwh', '') }}',
            garasiId: '{{ old('garasi_id', $selectedGarageId) }}',
            fileError: '',
            fileInfo: '',
            garages: @js($garagesPayload),
            savedVehicles: @js($savedVehicles->map(fn ($v) => [
                'id' => $v->id,
                'tipe_kendaraan' => $v->tipe_kendaraan->value,
                'plat_nomor' => $v->plat_nomor,
                'merek' => $v->merek,
                'kapasitas_kwh' => $v->kapasitas_kwh !== null ? (float) $v->kapasitas_kwh : '',
            ])->values()->all()),

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

            applySavedVehicle(vehicleId) {
                if (!vehicleId) return;
                const found = this.savedVehicles.find(v => String(v.id) === String(vehicleId));
                if (!found) return;
                this.tipeKendaraan = found.tipe_kendaraan;
                this.platNomor = found.plat_nomor;
                this.merek = found.merek;
                this.kapasitasKwh = found.kapasitas_kwh;
                this.garasiId = '';
            },

            get daysInSelectedMonth() {
                if (!this.tanggalMulai) return 30;
                const parts = this.tanggalMulai.split('-');
                if (parts.length !== 3) return 30;
                const year = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10);
                return new Date(year, month, 0).getDate();
            },

            get maxDurasiHarian() {
                return Math.max(1, this.daysInSelectedMonth - 1);
            },

            get tanggalSelesai() {
                if (!this.tanggalMulai) return '-';
                const parts = this.tanggalMulai.split('-');
                if (parts.length !== 3) return '-';
                const year = parseInt(parts[0], 10);
                const month = parseInt(parts[1], 10) - 1;
                const day = parseInt(parts[2], 10);

                if (this.tipeSewa === 'harian') {
                    const dur = Math.max(1, parseInt(this.durasiHari, 10) || 1);
                    const end = new Date(year, month, day + dur - 1);
                    return this.formatIsoDate(end);
                } else {
                    const targetMonth = month + 1;
                    const maxDayInTarget = new Date(year, targetMonth + 1, 0).getDate();
                    const clampedDay = Math.min(day, maxDayInTarget);
                    const nextMonthDate = new Date(year, targetMonth, clampedDay);
                    nextMonthDate.setDate(nextMonthDate.getDate() - 1);
                    return this.formatIsoDate(nextMonthDate);
                }
            },

            formatIsoDate(d) {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            },

            isGarageCompatible(g) {
                if (this.tipeKendaraan === 'sepeda' || this.tipeKendaraan === 'sepeda_motor') {
                    return g.tipe_garasi === 'sepeda';
                }
                if (this.tipeKendaraan === 'mobil_bensin') {
                    return g.tipe_garasi === 'mobil';
                }
                if (this.tipeKendaraan === 'mobil_listrik') {
                    return g.tipe_garasi === 'mobil' || g.tipe_garasi === 'mobil_listrik';
                }
                return false;
            },

            isGarageAvailableForDates(g) {
                const start = this.tanggalMulai;
                const end = this.tanggalSelesai;
                if (!start || end === '-') return g.status === 'available';

                for (const b of g.active_bookings) {
                    if (b.start <= end && b.end >= start) {
                        return false;
                    }
                }

                if (g.status === 'used' && g.active_bookings.length === 0) {
                    return false;
                }

                return true;
            },

            get compatibleAvailableGarages() {
                return this.garages.filter(g => this.isGarageCompatible(g) && this.isGarageAvailableForDates(g));
            },

            get selectedGarage() {
                return this.compatibleAvailableGarages.find(g => g.id === this.garasiId) || null;
            },

            get tarifPokok() {
                const g = this.selectedGarage;
                if (!g) return 0;
                if (this.tipeSewa === 'bulanan') {
                    return g.harga_bulanan;
                }
                const dur = Math.max(1, parseInt(this.durasiHari, 10) || 1);
                return g.harga_harian * dur;
            },

            get biayaListrik() {
                const g = this.selectedGarage;
                if (!g) return 0;
                if (this.tipeKendaraan === 'mobil_listrik' && g.tipe_garasi === 'mobil_listrik') {
                    const kwh = Math.max(0, parseFloat(this.kapasitasKwh) || 0);
                    return kwh * g.tarif_kwh;
                }
                return 0;
            },

            get totalBayar() {
                return this.tarifPokok + this.biayaListrik;
            },

            formatRupiah(val) {
                return 'Rp ' + Math.round(Number(val) || 0).toLocaleString('id-ID');
            }
        }"
        class="flex h-full w-full flex-1 flex-col gap-6"
    >
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">Book Garasi Baru</flux:heading>
                <flux:text class="mt-1">
                    Pilih tanggal mulai, durasi sewa, detail kendaraan kamu, unit garasi yang available, lalu upload bukti pembayaran (IDR).
                </flux:text>
            </div>
            <flux:button variant="subtle" :href="route('tenant.rentals.index')" wire:navigate>
                My Rentals History
            </flux:button>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/50 dark:text-red-200" data-test="rental-error-alert">
                <div class="font-semibold">Mohon periksa kembali input pemesanan Anda:</div>
                <ul class="mt-1 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tenant.rentals.store') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
            @csrf

            <!-- Left Column: Form Inputs (2 Cols) -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <!-- Section 1: Jadwal & Durasi Penyewaan (BR-04 & BR-05) -->
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
                        <flux:heading size="lg">1. Jadwal & Durasi Penyewaan</flux:heading>
                        <flux:text class="text-xs">
                            Maksimal 1 kali pemesanan adalah 1 bulan kalender aktual. Maksimal sewa harian adalah (jumlah hari bulan terkait &minus; 1) hari.
                        </flux:text>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <!-- Tanggal Mulai -->
                        <div>
                            <label for="tanggal_mulai" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Tanggal Mulai Sewa <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="tanggal_mulai"
                                type="date"
                                name="tanggal_mulai"
                                x-model="tanggalMulai"
                                @change="if (durasiHari > maxDurasiHarian) { durasiHari = maxDurasiHarian; }"
                                min="{{ $defaultTanggalMulai }}"
                                required
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-tanggal-mulai"
                            />
                            <div class="mt-1 text-[11px] text-zinc-500">Default hari ini, dapat memilih tanggal masa depan (Advance Booking).</div>
                        </div>

                        <!-- Jenis Durasi Sewa -->
                        <div>
                            <label for="tipe_sewa" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Jenis Durasi Sewa <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="tipe_sewa"
                                name="tipe_sewa"
                                x-model="tipeSewa"
                                required
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-tipe-sewa"
                            >
                                <option value="harian">Sewa Harian (1 s/d Maks Hari Bulan &minus; 1)</option>
                                <option value="bulanan">Sewa Bulanan (1 Bulan Kalender Aktual)</option>
                            </select>
                        </div>

                        <!-- Durasi Hari (Kondisional Harian) -->
                        <div x-show="tipeSewa === 'harian'">
                            <label for="durasi_hari" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Durasi Sewa Harian (Hari) <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="durasi_hari"
                                type="number"
                                name="durasi_hari"
                                x-model="durasiHari"
                                min="1"
                                :max="maxDurasiHarian"
                                :required="tipeSewa === 'harian'"
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-durasi-hari"
                            />
                            <div class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">
                                Batas maksimal sewa harian bulan ini: <strong x-text="maxDurasiHarian + ' hari'"></strong> (Bulan memiliki <span x-text="daysInSelectedMonth"></span> hari).
                            </div>
                        </div>

                        <!-- Durasi Bulan (Kondisional Bulanan) -->
                        <div x-show="tipeSewa === 'bulanan'">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Durasi Sewa Bulanan
                            </label>
                            <input type="hidden" name="durasi_bulan" value="1" />
                            <div class="mt-1.5 flex h-[38px] items-center rounded-lg border border-zinc-200 bg-zinc-100 px-3 text-sm font-semibold text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300">
                                1 Bulan Kalender Aktual (<span x-text="daysInSelectedMonth"></span> Hari Kalender)
                            </div>
                            <div class="mt-1 text-[11px] text-zinc-500">Maksimal durasi 1 kali pemesanan adalah 1 bulan kalender.</div>
                        </div>

                        <!-- Tanggal Selesai Otomatis -->
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                Tanggal Selesai Sewa (Otomatis)
                            </label>
                            <div
                                class="mt-1.5 flex h-[38px] items-center rounded-lg border border-indigo-200 bg-indigo-50/60 px-3 font-mono text-sm font-bold text-indigo-800 dark:border-indigo-800/60 dark:bg-indigo-950/30 dark:text-indigo-300"
                                data-test="rental-tanggal-selesai-preview"
                                x-text="tanggalSelesai"
                            ></div>
                            <div class="mt-1 text-[11px] text-zinc-500">Dihitung otomatis oleh sistem berdasarkan kalender riil.</div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Spesifikasi Kendaraan (BR-01 & BR-02) -->
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
                        <flux:heading size="lg">2. Data Spesifikasi Kendaraan</flux:heading>
                        <flux:text class="text-xs">
                            Jenis kendaraan menentukan tipe unit garasi yang kompatibel (Sepeda/Motor &rarr; S, Mobil Bensin &rarr; C, Mobil Listrik &rarr; C atau E).
                        </flux:text>
                    </div>

                    @if ($savedVehicles->isNotEmpty())
                        <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50/50 p-3 dark:border-indigo-800/60 dark:bg-indigo-950/30">
                            <label for="saved_vehicle_picker" class="block text-xs font-semibold text-indigo-900 dark:text-indigo-200">
                                Gunakan Kendaraan Terdaftar Saya (Opsional)
                            </label>
                            <select
                                id="saved_vehicle_picker"
                                @change="applySavedVehicle($event.target.value)"
                                class="mt-1.5 w-full rounded-lg border border-indigo-300 bg-white px-3 py-1.5 text-xs text-zinc-900 focus:border-indigo-500 focus:outline-none dark:border-indigo-700 dark:bg-zinc-800 dark:text-white"
                            >
                                <option value="">-- Input Kendaraan Baru / Pilih Kendaraan Terdaftar --</option>
                                @foreach ($savedVehicles as $sv)
                                    <option value="{{ $sv->id }}">
                                        {{ $sv->plat_nomor }} &mdash; {{ $sv->merek }} ({{ $sv->tipe_kendaraan->label() }}{{ $sv->isElectricVehicle() ? ' - '.$sv->kapasitas_kwh.' kWh' : '' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <!-- Tipe Kendaraan -->
                        <div>
                            <label for="tipe_kendaraan" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Jenis / Tipe Kendaraan <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="tipe_kendaraan"
                                name="tipe_kendaraan"
                                x-model="tipeKendaraan"
                                @change="garasiId = ''; if (tipeKendaraan === 'sepeda') { platNomor = ''; }"
                                required
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-tipe-kendaraan"
                            >
                                <option value="sepeda">Sepeda (Garasi S)</option>
                                <option value="sepeda_motor">Sepeda Motor (Garasi S)</option>
                                <option value="mobil_bensin">Mobil Bensin / Konvensional (Garasi C)</option>
                                <option value="mobil_listrik">Mobil Listrik / EV (Garasi C atau Garasi Listrik E)</option>
                            </select>
                        </div>

                        <!-- Plat Nomor (Kecuali Sepeda) -->
                        <div x-show="tipeKendaraan !== 'sepeda'">
                            <label for="plat_nomor" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Nomor Plat Kendaraan <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="plat_nomor"
                                type="text"
                                name="plat_nomor"
                                x-model="platNomor"
                                maxlength="15"
                                :required="tipeKendaraan !== 'sepeda'"
                                placeholder="Contoh: B 1234 XYZ"
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm uppercase text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-plat-nomor"
                            />
                        </div>

                        <!-- Merek Kendaraan -->
                        <div>
                            <label for="merek" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Merek / Model Kendaraan <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="merek"
                                type="text"
                                name="merek"
                                x-model="merek"
                                maxlength="50"
                                required
                                placeholder="Contoh: Honda Vario 160 / Hyundai Ioniq 5"
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-merek"
                            />
                        </div>

                        <!-- Kapasitas Baterai kWh (Khusus Mobil Listrik) -->
                        <div x-show="tipeKendaraan === 'mobil_listrik'">
                            <label for="kapasitas_kwh" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Kapasitas Baterai (kWh) <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="kapasitas_kwh"
                                type="number"
                                name="kapasitas_kwh"
                                x-model="kapasitasKwh"
                                min="1"
                                step="0.1"
                                :required="tipeKendaraan === 'mobil_listrik'"
                                placeholder="Contoh: 50"
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-kapasitas-kwh"
                            />
                            <div class="mt-1 text-[11px] text-emerald-700 dark:text-emerald-300">
                                Di Garasi Listrik (E), baterai wajib diisi penuh: Biaya Listrik = Kapasitas (kWh) &times; Tarif/kWh.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Pemilihan Garasi Tersedia & Upload Bukti Bayar (FR-05 & FR-08) -->
                <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
                        <flux:heading size="lg">3. Pilih Unit Garasi Tersedia & Upload Bukti Pembayaran</flux:heading>
                        <flux:text class="text-xs">
                            Hanya menampilkan unit garasi yang kompatibel dengan kendaraan Anda dan bebas bentrok jadwal pada rentang tanggal tersebut.
                        </flux:text>
                    </div>

                    <div class="mt-4 grid gap-5">
                        <!-- Pilihan Garasi -->
                        <div>
                            <label for="garasi_id" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                Pilih Unit Garasi Tersedia <span class="text-red-500">*</span>
                            </label>
                            <select
                                id="garasi_id"
                                name="garasi_id"
                                x-model="garasiId"
                                required
                                class="mt-1.5 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2.5 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                                data-test="rental-garasi-id"
                            >
                                <option value="">-- Pilih Unit Garasi --</option>
                                <template x-for="g in compatibleAvailableGarages" :key="g.id">
                                    <option
                                        :value="g.id"
                                        x-text="`${g.id} — ${g.label} | Harian: ${formatRupiah(g.harga_harian)} | Bulanan: ${formatRupiah(g.harga_bulanan)}${g.tipe_garasi === 'mobil_listrik' ? ' | Listrik: ' + formatRupiah(g.tarif_kwh) + '/kWh' : ' (Tanpa Charging)'}`"
                                    ></option>
                                </template>
                            </select>
                            <div x-show="compatibleAvailableGarages.length === 0" class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                Tidak ada unit garasi yang kompatibel dan tersedia pada rentang tanggal ini. Silakan pilih tanggal lain atau hubungi Admin.
                            </div>
                        </div>

                        <!-- Upload Bukti Pembayaran -->
                        <div>
                            <div class="flex items-center justify-between">
                                <label for="bukti_bayar" class="block text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:text-zinc-300">
                                    Unggah Berkas Bukti Pembayaran <span class="text-red-500">*</span>
                                </label>
                                <span class="text-[11px] font-medium text-zinc-500 dark:text-zinc-400">
                                    Format: JPG, JPEG, PNG &bull; Maks. 2 MB
                                </span>
                            </div>
                            <input
                                id="bukti_bayar"
                                type="file"
                                name="bukti_bayar"
                                accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                                @change="validateUploadFile($event)"
                                required
                                :class="fileError ? 'border-red-500 bg-red-50/40 dark:border-red-500 dark:bg-red-950/20' : 'border-zinc-300 bg-white dark:border-zinc-700 dark:bg-zinc-800'"
                                class="mt-1.5 block w-full rounded-lg border px-3 py-2 text-sm text-zinc-700 file:mr-4 file:rounded-md file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-zinc-700 dark:text-zinc-200 dark:file:bg-white dark:file:text-zinc-900"
                                data-test="rental-bukti-bayar"
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                Ketentuan upload: Hanya mendukung file gambar (<strong>.jpg, .jpeg, .png</strong>) dengan ukuran maksimal <strong>2 MB (2.048 KB)</strong>.
                            </p>

                            <!-- Real-time Client-side Error State -->
                            <div
                                x-show="fileError !== ''"
                                style="display: none;"
                                data-test="rental-file-error-state"
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

                            <!-- Server-side Error State -->
                            @error('bukti_bayar')
                                <div class="mt-2 rounded-lg border border-red-300 bg-red-50 p-3 text-xs font-medium text-red-700 dark:border-red-800 dark:bg-red-950/50 dark:text-red-300">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Transparent Live Pricing Breakdown (FR-07 & NFR-USE-03) -->
            <div class="flex flex-col gap-6">
                <div class="sticky top-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="border-b border-zinc-200 pb-3 dark:border-zinc-800">
                        <flux:heading size="lg">Rincian Biaya Sewa Baru (IDR)</flux:heading>
                        <flux:text class="text-xs">
                            Kalkulasi otomatis transparan sesuai tarif resmi Admin.
                        </flux:text>
                    </div>

                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Unit Garasi Dipilih:</span>
                            <span class="font-mono font-bold text-zinc-900 dark:text-white" x-text="selectedGarage ? selectedGarage.id + ' (' + selectedGarage.label + ')' : 'Belum dipilih'"></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-zinc-500">Periode Sewa:</span>
                            <span class="font-mono text-xs font-semibold text-zinc-800 dark:text-zinc-200" x-text="tanggalMulai + ' s/d ' + tanggalSelesai"></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-zinc-500">Jenis & Durasi:</span>
                            <span class="font-semibold text-zinc-800 dark:text-zinc-200" x-text="tipeSewa === 'bulanan' ? 'Bulanan (1 Bulan Kalender)' : 'Harian (' + durasiHari + ' Hari)'"></span>
                        </div>

                        <div class="border-t border-zinc-100 pt-3 dark:border-zinc-800">
                            <div class="flex justify-between">
                                <span class="text-zinc-600 dark:text-zinc-400">Tarif Pokok Sewa:</span>
                                <span class="font-semibold text-zinc-900 dark:text-white" data-test="preview-tarif-pokok" x-text="formatRupiah(tarifPokok)"></span>
                            </div>
                            <div class="text-[11px] text-zinc-400" x-show="selectedGarage">
                                <span x-show="tipeSewa === 'harian'" x-text="durasiHari + ' hari × ' + formatRupiah(selectedGarage?.harga_harian || 0)"></span>
                                <span x-show="tipeSewa === 'bulanan'" x-text="'Tarif Bulanan Tetap (25 × ' + formatRupiah(selectedGarage?.harga_harian || 0) + ')'"></span>
                            </div>
                        </div>

                        <div>
                            <div class="flex justify-between">
                                <span class="text-zinc-600 dark:text-zinc-400">Biaya Pengisian Listrik (EV):</span>
                                <span class="font-semibold text-emerald-700 dark:text-emerald-300" data-test="preview-biaya-listrik" x-text="formatRupiah(biayaListrik)"></span>
                            </div>
                            <div class="text-[11px] text-zinc-400" x-show="tipeKendaraan === 'mobil_listrik' && selectedGarage?.tipe_garasi === 'mobil_listrik'">
                                <span x-text="(kapasitasKwh || 0) + ' kWh × ' + formatRupiah(selectedGarage?.tarif_kwh || 0) + '/kWh (Isi Penuh)'"></span>
                            </div>
                            <div class="text-[11px] text-zinc-400" x-show="tipeKendaraan === 'mobil_listrik' && selectedGarage?.tipe_garasi === 'mobil'">
                                Parkir di Garasi Mobil Biasa (C) — Tanpa Biaya Listrik (Rp 0)
                            </div>
                        </div>

                        <div class="border-t-2 border-zinc-200 pt-3 dark:border-zinc-700">
                            <div class="flex items-baseline justify-between">
                                <span class="text-base font-bold text-zinc-900 dark:text-white">Total Bayar:</span>
                                <span class="text-2xl font-extrabold text-indigo-600 dark:text-indigo-400" data-test="preview-total-bayar" x-text="formatRupiah(totalBayar)"></span>
                            </div>
                            <div class="mt-1 text-[11px] text-zinc-500">
                                Total Bayar = Tarif Pokok Sewa + Biaya Listrik.
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <flux:button type="submit" variant="primary" class="w-full" x-bind:disabled="fileError !== ''" data-test="submit-rental-button">
                            Simpan Transaksi & Unggah Bukti Bayar
                        </flux:button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-layouts::app>
