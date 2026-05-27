<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Solario">

        <title>{{ config('app.name', 'Solario') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-white dark:bg-zinc-950 min-h-dvh flex flex-col items-center px-6 pt-24 sm:pt-32">
        <div class="flex flex-col items-center gap-16">
            <div class="w-36 h-36 rounded-[32px] shadow-xl overflow-hidden">
                <img src="/apple-touch-icon.png" alt="Solario" class="w-full h-full">
            </div>

            <div class="flex flex-col items-center gap-4">
                <h1 class="text-5xl sm:text-6xl font-bold tracking-tight text-zinc-900 dark:text-zinc-100">
                    Solario
                </h1>

                <p class="text-lg text-zinc-500 dark:text-zinc-400 text-center max-w-xs">
                    Your solar energy at a glance.
                </p>
            </div>

            @auth
                <flux:button href="{{ route('dashboard') }}" variant="primary">
                    Go to Dashboard
                </flux:button>
            @else
                <flux:button href="{{ route('login') }}" variant="primary">
                    Sign In
                </flux:button>
            @endauth
        </div>
    </body>
</html>
