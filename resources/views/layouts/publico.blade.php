<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        @livewireStyles
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="flex min-h-screen flex-col bg-neutral-100 antialiased">
        <header class="bg-[#12151a]">
            <nav aria-label="{{ __('Principal') }}" class="mx-auto flex w-full max-w-3xl items-center px-4 py-3 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[#22c55e]" wire:navigate>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="size-8 text-[#22c55e]" aria-hidden="true" focusable="false">
                        <path fill="currentColor" d="M12 1.5 3 5.25v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12v-6L12 1.5Zm0 2.18 7 2.92v4.65c0 4.43-2.98 8.6-7 9.78-4.02-1.18-7-5.35-7-9.78V6.6l7-2.92Z" />
                        <path fill="currentColor" d="M12 6.5a3.5 3.5 0 0 0-1.5 6.66V17h3v-3.84A3.5 3.5 0 0 0 12 6.5Z" />
                    </svg>
                    <span class="font-semibold text-white">Centinela Formosa</span>
                </a>

                <ul class="ml-auto flex items-center gap-1">
                    <li>
                        <a
                            href="{{ route('impacto') }}"
                            @if (request()->routeIs('impacto')) aria-current="page" @endif
                            @class([
                                'rounded-md px-3 py-2 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-[#22c55e]',
                                'bg-white/10 text-white' => request()->routeIs('impacto'),
                                'text-neutral-300 hover:bg-white/5 hover:text-white' => ! request()->routeIs('impacto'),
                            ])
                            wire:navigate
                        >
                            {{ __('Impacto') }}
                        </a>
                    </li>
                </ul>
            </nav>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-6 sm:px-6 sm:py-10">
            {{ $slot }}
        </main>

        <footer class="bg-[#12151a]">
            <p class="mx-auto flex w-full max-w-3xl flex-wrap items-center justify-center gap-x-2 gap-y-1 px-4 py-5 text-center text-xs text-neutral-400 sm:px-6 sm:text-sm">
                <span class="font-medium text-neutral-200">Centinela Formosa</span>
                <span aria-hidden="true">·</span>
                <span>Formosa Hack 2026</span>
                <span aria-hidden="true">·</span>
                <span>{{ __('Herramienta educativa de seguridad digital') }}</span>
            </p>
        </footer>

        @livewireScripts
    </body>
</html>
