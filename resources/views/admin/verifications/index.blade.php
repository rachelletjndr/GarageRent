<x-layouts::app :title="__('Verifikasi Pendaftaran Penyewa (KTP)')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <!-- Header -->
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">Verifikasi Pendaftaran & Validasi KTP Penyewa</flux:heading>
                <flux:text class="mt-1">
                    Review 16 digit NIK KTP pendaftar baru sebelum kasih akses login dan booking garasi.
                </flux:text>
            </div>
        </div>

        <!-- Flash Notification -->
        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/50 dark:text-emerald-200" data-test="verification-status-alert">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800/60 dark:bg-red-950/50 dark:text-red-200">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Statistics Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Total Pendaftar</div>
                <div class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white" data-test="stat-total">{{ $statistics['total'] }}</div>
                <div class="mt-1 text-xs text-zinc-500">Seluruh akun calon penyewa</div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-5 shadow-xs dark:border-amber-800/60 dark:bg-amber-950/20">
                <div class="text-xs font-medium uppercase tracking-wider text-amber-700 dark:text-amber-300">Menunggu Verifikasi</div>
                <div class="mt-2 text-3xl font-bold text-amber-700 dark:text-amber-300" data-test="stat-pending">{{ $statistics['pending'] }}</div>
                <div class="mt-1 text-xs text-amber-600 dark:text-amber-400">Perlu cek validitas NIK KTP</div>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-5 shadow-xs dark:border-emerald-800/60 dark:bg-emerald-950/20">
                <div class="text-xs font-medium uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Disetujui (Approved)</div>
                <div class="mt-2 text-3xl font-bold text-emerald-700 dark:text-emerald-300" data-test="stat-approved">{{ $statistics['approved'] }}</div>
                <div class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">Dapat login & menyewa garasi</div>
            </div>

            <div class="rounded-xl border border-red-200 bg-red-50/60 p-5 shadow-xs dark:border-red-800/60 dark:bg-red-950/20">
                <div class="text-xs font-medium uppercase tracking-wider text-red-700 dark:text-red-300">Ditolak (Rejected)</div>
                <div class="mt-2 text-3xl font-bold text-red-700 dark:text-red-300" data-test="stat-rejected">{{ $statistics['rejected'] }}</div>
                <div class="mt-1 text-xs text-red-600 dark:text-red-400">NIK KTP tidak memenuhi syarat</div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="flex flex-col justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap gap-2">
                @php
                    $tabs = [
                        'all' => 'Semua (' . $statistics['total'] . ')',
                        'pending' => 'Pending (' . $statistics['pending'] . ')',
                        'approved' => 'Approved (' . $statistics['approved'] . ')',
                        'rejected' => 'Rejected (' . $statistics['rejected'] . ')',
                    ];
                @endphp
                @foreach ($tabs as $key => $label)
                    <a
                        href="{{ route('admin.verifications.index', ['status' => $key === 'all' ? null : $key, 'search' => $search ?: null]) }}"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $statusFilter === $key ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('admin.verifications.index') }}" class="flex items-center gap-2">
                @if ($statusFilter !== 'all')
                    <input type="hidden" name="status" value="{{ $statusFilter }}" />
                @endif
                <input
                    type="text"
                    name="search"
                    value="{{ $search }}"
                    placeholder="Cari NIK, nama, email, telp..."
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none sm:w-64 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"
                    data-test="search-tenant-input"
                />
                <flux:button type="submit" size="sm" variant="primary">
                    Cari
                </flux:button>
                @if ($search !== '')
                    <a
                        href="{{ route('admin.verifications.index', ['status' => $statusFilter === 'all' ? null : $statusFilter]) }}"
                        class="text-xs text-zinc-500 underline hover:text-zinc-800 dark:hover:text-zinc-200"
                    >
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <!-- Tenant Verification Table -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:bg-zinc-800/70 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3.5">Calon Penyewa</th>
                            <th class="px-4 py-3.5">NIK KTP & Analisis Validitas</th>
                            <th class="px-4 py-3.5">Kontak</th>
                            <th class="px-4 py-3.5">Status Verifikasi</th>
                            <th class="px-4 py-3.5 text-right">Tindakan Admin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                        @forelse ($registrations as $penyewa)
                            @php
                                $ktpInfo = $verificationService->inspectKtpNik($penyewa->nik);
                            @endphp
                            <tr class="hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40" data-test="tenant-row-{{ $penyewa->id }}">
                                <!-- Biodata -->
                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-zinc-900 dark:text-white">{{ $penyewa->nama_lengkap }}</div>
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $penyewa->user?->email }}</div>
                                    <div class="mt-1 text-[11px] text-zinc-400">
                                        Daftar: {{ $penyewa->created_at?->format('d M Y H:i') ?? '-' }}
                                    </div>
                                </td>

                                <!-- NIK KTP & Structural Validation -->
                                <td class="px-4 py-4 align-top">
                                    <div class="font-mono text-sm font-bold tracking-wide text-zinc-900 dark:text-zinc-100">
                                        {{ $penyewa->nik }}
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[11px]">
                                        @if ($ktpInfo['is_valid_format'])
                                            <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                                Format 16 Digit Valid
                                            </span>
                                        @else
                                            <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                Periksa Struktur Tanggal/Kode
                                            </span>
                                        @endif
                                        <span class="text-zinc-500 dark:text-zinc-400">
                                            Wilayah: {{ $ktpInfo['kode_wilayah'] }} | Lhr: {{ $ktpInfo['tanggal_lahir_kode'] }} ({{ $ktpInfo['jenis_kelamin_estimasi'] }})
                                        </span>
                                    </div>
                                </td>

                                <!-- Kontak -->
                                <td class="px-4 py-4 align-top">
                                    <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $penyewa->no_telp }}</div>
                                </td>

                                <!-- Status -->
                                <td class="px-4 py-4 align-top">
                                    @if ($penyewa->isPending())
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                            Menunggu Verifikasi
                                        </span>
                                    @elseif ($penyewa->isApproved())
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            Disetujui (Approved)
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800 dark:bg-red-950 dark:text-red-300">
                                            Ditolak (Rejected)
                                        </span>
                                    @endif

                                    @if ($penyewa->verifier)
                                        <div class="mt-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                            Oleh: {{ $penyewa->verifier->name }}
                                            @if ($penyewa->tanggal_verifikasi)
                                                ({{ $penyewa->tanggal_verifikasi->format('d/m/Y H:i') }})
                                            @endif
                                        </div>
                                    @endif

                                    @if ($penyewa->isRejected() && $penyewa->catatan_penolakan)
                                        <div class="mt-1.5 rounded-md border border-red-200 bg-red-50 p-2 text-xs text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                                            <strong>Alasan:</strong> {{ $penyewa->catatan_penolakan }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Tindakan Admin -->
                                <td class="px-4 py-4 text-right align-top">
                                    @if ($penyewa->isPending())
                                        <div class="flex flex-col items-end gap-2">
                                            <form method="POST" action="{{ route('admin.verifications.approve', $penyewa) }}">
                                                @csrf
                                                @method('PATCH')
                                                <flux:button
                                                    type="submit"
                                                    size="sm"
                                                    variant="primary"
                                                    data-test="approve-tenant-{{ $penyewa->id }}"
                                                >
                                                    Terima (Approve KTP)
                                                </flux:button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.verifications.reject', $penyewa) }}" class="flex flex-col items-end gap-1.5 sm:flex-row sm:items-center">
                                                @csrf
                                                @method('PATCH')
                                                <input
                                                    type="text"
                                                    name="catatan_penolakan"
                                                    placeholder="Alasan tolak KTP..."
                                                    maxlength="500"
                                                    class="w-44 rounded-lg border border-zinc-300 bg-white px-2.5 py-1 text-xs text-zinc-800 focus:border-red-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                                    data-test="reject-note-{{ $penyewa->id }}"
                                                />
                                                <flux:button
                                                    type="submit"
                                                    size="sm"
                                                    variant="danger"
                                                    data-test="reject-tenant-{{ $penyewa->id }}"
                                                >
                                                    Tolak
                                                </flux:button>
                                            </form>
                                        </div>
                                    @elseif ($penyewa->isApproved())
                                        <span class="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                            Verifikasi Selesai
                                        </span>
                                    @else
                                        <span class="text-xs font-medium text-zinc-400 dark:text-zinc-500">
                                            Pendaftaran Ditolak
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    Tidak ada data pendaftaran calon penyewa yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($registrations->hasPages())
                <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    {{ $registrations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>

