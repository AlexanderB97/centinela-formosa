<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @livewireStyles
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body x-data="{ sidebarAbierto: false }" @keydown.escape.window="sidebarAbierto = false" class="min-h-screen bg-neutral-100 antialiased lg:flex">
        {{-- Barra superior (solo mobile) --}}
        <header class="flex items-center gap-3 bg-[#12151a] px-4 py-3 text-white lg:hidden">
            <button
                type="button"
                @click="sidebarAbierto = true"
                aria-controls="staff-sidebar"
                :aria-expanded="sidebarAbierto.toString()"
                aria-expanded="false"
                class="rounded-md p-2 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f]"
            >
                <span class="sr-only">{{ __('Abrir menú') }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <span class="font-semibold">Centinela Formosa</span>
        </header>

        {{-- Fondo del drawer (solo mobile) --}}
        <div x-show="sidebarAbierto" x-cloak @click="sidebarAbierto = false" class="fixed inset-0 z-30 bg-black/50 lg:hidden" aria-hidden="true"></div>

        <aside
            id="staff-sidebar"
            :class="sidebarAbierto ? 'translate-x-0 visible' : '-translate-x-full invisible'"
            class="invisible fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-[#12151a] text-neutral-300 transition-[translate,visibility] lg:visible lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
        >
            <div class="flex items-center justify-between gap-2 px-5 py-6">
                <a href="{{ route('staff.dashboard') }}" class="flex items-center gap-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f]" wire:navigate>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-8 text-[#8a5a1f]" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 1.5 3 5.25v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12v-6L12 1.5Zm0 2.18 7 2.92v4.65c0 4.43-2.98 8.6-7 9.78-4.02-1.18-7-5.35-7-9.78V6.6l7-2.92Z" />
                        <path fill="currentColor" d="M12 6.5a3.5 3.5 0 0 0-1.5 6.66V17h3v-3.84A3.5 3.5 0 0 0 12 6.5Z" />
                    </svg>
                    <span class="font-semibold text-white">Centinela Formosa</span>
                </a>

                <button
                    type="button"
                    @click="sidebarAbierto = false"
                    class="rounded-md p-2 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f] lg:hidden"
                >
                    <span class="sr-only">{{ __('Cerrar menú') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false">
                        <path stroke-linecap="round" d="M6 6l12 12M18 6 6 18" />
                    </svg>
                </button>
            </div>

            @php
                $claseLink = 'block rounded-md border-l-4 px-3 py-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[#8a5a1f]';
                $claseActivo = 'border-[#8a5a1f] bg-white/10 text-white';
                $claseInactivo = 'border-transparent hover:bg-white/5 hover:text-white';
            @endphp

            <nav aria-label="{{ __('Panel de staff') }}" class="flex-1 px-3">
                <ul class="flex flex-col gap-1">
                    <li>
                        <a
                            href="{{ route('staff.dashboard') }}"
                            @if (request()->routeIs('staff.dashboard')) aria-current="page" @endif
                            class="{{ $claseLink }} {{ request()->routeIs('staff.dashboard') ? $claseActivo : $claseInactivo }}"
                            wire:navigate
                        >
                            {{ __('Dashboard') }}
                        </a>
                    </li>

                    @if (auth('staff')->user()?->isAdmin())
                        <li>
                            <a
                                href="{{ route('staff.usuarios') }}"
                                @if (request()->routeIs('staff.usuarios')) aria-current="page" @endif
                                class="{{ $claseLink }} {{ request()->routeIs('staff.usuarios') ? $claseActivo : $claseInactivo }}"
                                wire:navigate
                            >
                                {{ __('Gestión de staff') }}
                            </a>
                        </li>
                    @endif
                </ul>
            </nav>
        </aside>

        <div class="min-w-0 flex-1">
            <main class="mx-auto w-full max-w-6xl p-4 sm:p-6 lg:p-10">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>
