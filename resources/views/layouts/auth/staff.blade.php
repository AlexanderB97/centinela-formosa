<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#12151a] antialiased">
        <main class="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 md:p-10">
            <div class="w-full max-w-sm sm:max-w-md">
                {{ $slot }}
            </div>
        </main>
    </body>
</html>
