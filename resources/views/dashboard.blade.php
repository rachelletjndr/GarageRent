<x-layouts::app :title="__('Dashboard - SIM-Garasi')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        <!-- Welcome Banner -->
        <div class="flex flex-col justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-6 shadow-xs sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <flux:heading size="xl">Selamat Datang, {{ $user?->name }}</flux:heading>
                <flux:text class="mt-1">
                    Sistem Informasi Penyewaan Garasi Kendaraan (SIM-Garasi)
                </flux:text>
            </div>
            <div>
                <flux:badge color="{{ $user?->isAdmin() ? 'indigo' : 'emerald' }}" size="lg">
                    Role: {{ $user?->role?->label() ?? 'Penyewa' }}
                </flux:badge>
            </div>
        </div>

        @if ($user?->isAdmin() && $verificationStats)
            <!-- Admin User Management & Verification Summary -->
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <flux:heading size="lg">Manajemen Verifikasi Pendaftaran Penyewa (KTP)</flux:heading>
                        <flux:text class="mt-0.5 text-sm">
                            Ringkasan status verifikasi identitas NIK KTP calon penyewa garasi.
                        </flux:text>
                    </div>
                    <flux:button variant="primary" :href="route('admin.verifications.index')" wire:navigate>
                        Kelola Verifikasi KTP
                    </flux:button>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-4">
                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        <div class="text-xs text-zinc-500">Total Pendaftar</div>
                        <div class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $verificationStats['total'] }}</div>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-800/50 dark:bg-amber-950/20">
                        <div class="text-xs text-amber-700 dark:text-amber-300">Menunggu Verifikasi (Pending)</div>
                        <div class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $verificationStats['pending'] }}</div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-800/50 dark:bg-emerald-950/20">
                        <div class="text-xs text-emerald-700 dark:text-emerald-300">Disetujui (Approved)</div>
                        <div class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $verificationStats['approved'] }}</div>
                    </div>
                    <div class="rounded-xl border border-red-200 bg-red-50/50 p-4 dark:border-red-800/50 dark:bg-red-950/20">
                        <div class="text-xs text-red-700 dark:text-red-300">Ditolak (Rejected)</div>
                        <div class="mt-1 text-2xl font-bold text-red-700 dark:text-red-300">{{ $verificationStats['rejected'] }}</div>
                    </div>
                </div>
            </div>
        @elseif ($penyewa)
            <!-- Tenant Verified Profile Card -->
            <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 pb-4 dark:border-zinc-800">
                    <div>
                        <flux:heading size="lg">Profil Identitas Penyewa (KTP)</flux:heading>
                        <flux:text class="mt-0.5 text-sm">Data kependudukan terdaftar untuk transaksi penyewaan garasi</flux:text>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        {{ $penyewa->status_verifikasi->label() }}
                    </span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <div class="text-xs text-zinc-500">NIK KTP (16 Digit)</div>
                        <div class="mt-1 font-mono text-base font-bold text-zinc-900 dark:text-white">{{ $penyewa->nik }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Nama Lengkap Sesuai KTP</div>
                        <div class="mt-1 font-semibold text-zinc-900 dark:text-white">{{ $penyewa->nama_lengkap }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-zinc-500">Nomor Telepon / WhatsApp</div>
                        <div class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $penyewa->no_telp }}</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts::app>
