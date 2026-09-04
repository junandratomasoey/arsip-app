<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Perpustakaan Digital Arsip Pekerjaan - BBWS NT II</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="min-h-screen flex flex-col">
            <header class="bg-white border-b border-gray-200">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-4">
                    <a href="{{ route('public.library.index') }}" wire:navigate class="flex items-baseline gap-2">
                        <span class="font-semibold text-gray-800">Perpustakaan Digital Arsip Pekerjaan</span>
                        <span class="hidden sm:inline text-xs text-gray-400">BBWS Nusa Tenggara II</span>
                    </a>
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="text-sm text-gray-500 hover:text-gray-700 whitespace-nowrap">Login Staf</a>
                    @endif
                </div>
            </header>

            @if (isset($header))
                <div class="bg-white border-b border-gray-100">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                        {{ $header }}
                    </div>
                </div>
            @endif

            <main class="flex-1">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {{ $slot }}
                </div>
            </main>

            <footer class="border-t border-gray-200 py-6 text-center text-xs text-gray-400">
                Balai Besar Wilayah Sungai Nusa Tenggara II
            </footer>
        </div>

        @stack('scripts')
    </body>
</html>
