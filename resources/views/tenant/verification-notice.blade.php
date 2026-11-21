<x-layouts::app :title="__('Status Verifikasi Akun Penyewa')">
    <div class="mx-auto flex w-full max-w-2xl flex-col gap-6 py-8">
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 pb-4 dark:border-zinc-800">
                <div>
                    <flux:heading size="lg">Status Verifikasi Identitas (KTP)</flux:heading>
                    <flux:text class="mt-0.5 text-sm">
                        Sistem Informasi Penyewaan Garasi Kendaraan (SIM-Garasi)
                    </flux:text>
                </div>

                @if ($penyewa?->isPending())
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        Menunggu Verifikasi Admin
                    </span>
                @elseif ($penyewa?->isRejected())
                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-800 dark:bg-red-950 dark:text-red-300">
                        Pendaftaran Ditolak
                    </span>
                @endif
            </div>

            @if (session('warning'))
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($penyewa)
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">NIK KTP (16 Digit)</div>
                        <div class="mt-1 font-mono text-base font-bold text-zinc-900 dark:text-white">{{ $penyewa->nik }}</div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Nama Lengkap Sesuai KTP</div>
                        <div class="mt-1 text-base font-semibold text-zinc-900 dark:text-white">{{ $penyewa->nama_lengkap }}</div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Nomor Telepon / WhatsApp</div>
                        <div class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $penyewa->no_telp }}</div>
                    </div>

                    <div class="rounded-xl bg-zinc-50 p-4 dark:bg-zinc-800/60">
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">Alamat Email</div>
                        <div class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $user->email }}</div>
                    </div>
                </div>

                @if ($penyewa->isRejected() && $penyewa->catatan_penolakan)
                    <div class="mt-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                        <strong>Alasan Penolakan dari Admin:</strong>
                        <p class="mt-1">{{ $penyewa->catatan_penolakan }}</p>
                    </div>
                @else
                    <div class="mt-5 rounded-xl border border-zinc-200 bg-zinc-50 p-4 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-800/40 dark:text-zinc-300">
                        Permohonan pendaftaran Anda telah tercatat. Admin akan segera memverifikasi keabsahan data NIK KTP Anda sebelum fitur pemesanan garasi dapat diakses.
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-layouts::app>

