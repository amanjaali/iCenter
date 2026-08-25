<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · ALLVA Accounting</title>

    {{-- Brand kit: symbol alone on navy, never the wordmark (brand rule 4). --}}
    <link rel="icon" href="{{ asset('brand/icons/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('brand/icons/app-icon-1024.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen">
<div class="flex min-h-screen" x-data="{ mobileNav: false }">

    {{-- Sidebar --------------------------------------------------------- --}}
    <aside
        class="fixed inset-y-0 start-0 z-40 flex w-[232px] shrink-0 flex-col bg-navy px-4 py-5
               transition-transform lg:static lg:translate-x-0 no-print"
        :class="mobileNav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
        @include('partials.sidebar')
    </aside>

    <div
        x-show="mobileNav" x-cloak x-transition.opacity
        @click="mobileNav = false"
        class="fixed inset-0 z-30 bg-navy/40 lg:hidden"
    ></div>

    {{-- Main ------------------------------------------------------------ --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-[62px] shrink-0 items-center justify-between gap-4
                       border-b border-rule bg-white px-5 lg:px-7 no-print">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button" @click="mobileNav = true"
                    class="btn btn-ghost btn-sm lg:hidden" aria-label="Open navigation"
                >
                    <x-icon name="menu" class="size-[18px]"/>
                </button>
                <div class="min-w-0">
                    <h1 class="truncate font-display text-[19px] font-medium leading-tight">@yield('title', 'Dashboard')</h1>
                    @hasSection('subtitle')
                        <div class="eyebrow truncate">@yield('subtitle')</div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                @yield('actions')
            </div>
        </header>

        <main class="flex-1 px-5 py-6 lg:px-7">
            @include('partials.flash')
            @yield('content')
        </main>

        <footer class="px-5 pb-6 lg:px-7 no-print">
            <div class="eyebrow">
                ALLVA Accounting · eTrackify · {{ config('allva.currency.code') }} ·
                {{ now()->format('j M Y') }}
            </div>
        </footer>
    </div>
</div>

@livewireScripts
</body>
</html>
