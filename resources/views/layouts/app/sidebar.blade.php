<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body
        class="flex min-h-screen flex-col bg-zinc-50 dark:bg-zinc-950"
        x-data="{ sidebarOpen: false }"
        @keydown.escape.window="sidebarOpen = false"
    >
        <!-- Top Navigation Header with Hamburger Menu (Full Width on Desktop & Mobile) -->
        <header class="sticky top-0 z-30 w-full border-b border-zinc-200 bg-white/95 backdrop-blur-xs dark:border-zinc-800 dark:bg-zinc-900/95">
            <div class="mx-auto flex h-16 w-full max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="sidebarOpen = !sidebarOpen"
                        :aria-expanded="sidebarOpen.toString()"
                        aria-label="Toggle Menu Navigasi"
                        data-test="hamburger-menu-button"
                        class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg border border-zinc-200 bg-zinc-50 text-zinc-700 transition hover:bg-zinc-100 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                    >
                        <!-- Three Horizontal Lines (Hamburger Icon) -->
                        <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
                </div>

                <!-- Right User Dropdown -->
                <div class="flex items-center gap-3">
                <flux:dropdown position="bottom" align="end">
                    <flux:profile
                        :name="auth()->user()->name"
                        :initials="auth()->user()->initials()"
                        icon-trailing="chevron-down"
                    />

                    <flux:menu>
                        <flux:menu.radio.group>
                            <div class="p-0 text-sm font-normal">
                                <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                    <flux:avatar
                                        :name="auth()->user()->name"
                                        :initials="auth()->user()->initials()"
                                    />

                                    <div class="grid flex-1 text-start text-sm leading-tight">
                                        <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                        <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                    </div>
                                </div>
                            </div>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <flux:menu.radio.group>
                            <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                                {{ __('Settings') }}
                            </flux:menu.item>
                        </flux:menu.radio.group>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item
                                as="button"
                                type="submit"
                                icon="arrow-right-start-on-rectangle"
                                class="w-full cursor-pointer"
                                data-test="logout-button"
                            >
                                {{ __('Log out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
                </div>
            </div>
        </header>

        <!-- Backdrop Overlay when Hamburger Drawer is Open -->
        <div
            x-show="sidebarOpen"
            x-transition.opacity.duration.200ms
            @click="sidebarOpen = false"
            style="display: none;"
            data-test="sidebar-overlay"
            class="fixed inset-0 z-40 bg-zinc-900/50 backdrop-blur-xs"
        ></div>

        <!-- Slide-Over Hamburger Navigation Drawer (Hidden by Default on Desktop & Mobile) -->
        <aside
            x-show="sidebarOpen"
            x-transition:enter="transition ease-out duration-200 transform"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150 transform"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            style="display: none;"
            data-test="hamburger-sidebar-drawer"
            class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-e border-zinc-200 bg-zinc-50 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
        >
            <!-- Drawer Header -->
            <div class="flex h-16 items-center justify-between border-b border-zinc-200 px-4 dark:border-zinc-800">
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <button
                    type="button"
                    @click="sidebarOpen = false"
                    aria-label="Tutup Menu Navigasi"
                    data-test="close-sidebar-button"
                    class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-200/70 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white"
                >
                    <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Drawer Navigation Links -->
            <div class="flex-1 overflow-y-auto px-3 py-4">
                <div class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                    Main Menu
                </div>

                <nav class="space-y-1">
                    <a
                        href="{{ route('dashboard') }}"
                        wire:navigate
                        @click="sidebarOpen = false"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                    >
                        <span>{{ __('Dashboard') }}</span>
                    </a>

                    @if (auth()->user()?->isAdmin())
                        <a
                            href="{{ route('admin.verifications.index') }}"
                            wire:navigate
                            @click="sidebarOpen = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.verifications.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        >
                            <span>{{ __('Verifikasi KTP Penyewa') }}</span>
                        </a>

                        <a
                            href="{{ route('admin.garages.index') }}"
                            wire:navigate
                            @click="sidebarOpen = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.garages.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        >
                            <span>{{ __('Manage Garasi') }}</span>
                        </a>

                        <a
                            href="{{ route('admin.extensions.index') }}"
                            wire:navigate
                            @click="sidebarOpen = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.extensions.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        >
                            <span>{{ __('Extend Sewa') }}</span>
                        </a>

                        <a
                            href="{{ route('admin.reports.index') }}"
                            wire:navigate
                            @click="sidebarOpen = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('admin.reports.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        >
                            <span>{{ __('Reports & Analytics') }}</span>
                        </a>
                    @elseif (auth()->user()?->isPenyewa() && auth()->user()?->penyewa?->isApproved())
                        <a
                            href="{{ route('tenant.garages.index') }}"
                            wire:navigate
                            @click="sidebarOpen = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('tenant.garages.*') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        >
                            <span>{{ __('Katalog & Pricing Garasi') }}</span>
                        </a>

                        <a
                            href="{{ route('tenant.rentals.create') }}"
                            wire:navigate
                            @click="sidebarOpen = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('tenant.rentals.create') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        >
                            <span>{{ __('Book Garasi Baru') }}</span>
                        </a>

                        <a
                            href="{{ route('tenant.rentals.index') }}"
                            wire:navigate
                            @click="sidebarOpen = false"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('tenant.rentals.index') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300' : 'text-zinc-700 hover:bg-zinc-200/60 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                        >
                            <span>{{ __('My Rentals & History') }}</span>
                        </a>
                    @endif
                </nav>
            </div>

            <!-- Drawer Footer -->
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex items-center justify-between">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-zinc-900 dark:text-white">
                            {{ auth()->user()->name }}
                        </div>
                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">
                            {{ auth()->user()->email }}
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
