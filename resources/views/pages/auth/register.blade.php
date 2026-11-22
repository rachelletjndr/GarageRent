<x-layouts::auth :title="__('Registrasi Penyewa')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Registrasi Akun Penyewa')"
            :description="__('Isi data diri sesuai KTP buat bikin akun penyewa garasi baru.')"
        />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300">
            <strong>KTP Verification Info:</strong> Akun baru otomatis berstatus <em>Pending</em> dan sudah bisa dipakai login begitu NIK KTP kamu di-approve Admin.
        </div>

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
            @csrf

            <!-- NIK KTP (16 Digit) -->
            <flux:input
                name="nik"
                :label="__('NIK KTP (16 Digit Angka)')"
                :value="old('nik')"
                type="text"
                inputmode="numeric"
                maxlength="16"
                required
                autofocus
                placeholder="Contoh: 3171010105900001"
                data-test="register-nik-input"
            />

            <!-- Nama Lengkap Sesuai KTP -->
            <flux:input
                name="name"
                :label="__('Nama Lengkap (Sesuai KTP)')"
                :value="old('name')"
                type="text"
                maxlength="100"
                required
                autocomplete="name"
                :placeholder="__('Masukkan nama lengkap sesuai KTP')"
                data-test="register-name-input"
            />

            <!-- Nomor Telepon / WhatsApp -->
            <flux:input
                name="no_telp"
                :label="__('Nomor Telepon / WhatsApp Aktif')"
                :value="old('no_telp')"
                type="text"
                inputmode="numeric"
                maxlength="15"
                required
                autocomplete="tel"
                placeholder="Contoh: 081234567890"
                data-test="register-phone-input"
            />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Alamat Email')"
                :value="old('email')"
                type="email"
                maxlength="100"
                required
                autocomplete="email"
                placeholder="email@contoh.com"
                data-test="register-email-input"
            />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password (Minimal 8 Karakter)')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Minimal 8 karakter')"
                viewable
                data-test="register-password-input"
            />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Konfirmasi Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Ulangi password Anda')"
                viewable
                data-test="register-password-confirmation-input"
            />

            <div class="flex items-center justify-end pt-1">
                <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                    {{ __('Daftar Akun Penyewa') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Sudah memiliki akun terverifikasi?') }}</span>
            <flux:link :href="route('login')" wire:navigate>
                {{ __('Login') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth>
