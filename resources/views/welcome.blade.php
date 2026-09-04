<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>SIARPU NT II - Sistem Arsip Digital Dokumen Pekerjaan</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 text-center">
            <h1 class="text-2xl font-semibold text-gray-800">SIARPU NT II</h1>
            <p class="mt-2 text-sm text-gray-500 max-w-md">
                Sistem Arsip Digital Dokumen Pekerjaan<br>
                Balai Besar Wilayah Sungai Nusa Tenggara II
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('public.library.index') }}" class="px-5 py-2.5 bg-gray-800 text-white rounded-md text-sm font-medium hover:bg-gray-700">
                    Perpustakaan Digital Publik
                </a>
                @if (Route::has('login'))
                    <a href="{{ route('login') }}" class="px-5 py-2.5 bg-white border border-gray-300 text-gray-700 rounded-md text-sm font-medium hover:bg-gray-50">
                        Login Staf
                    </a>
                @endif
            </div>
        </div>
    </body>
</html>
