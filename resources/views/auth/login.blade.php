<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · ALLVA Accounting</title>
    <link rel="icon" href="{{ asset('brand/icons/favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Sora:wght@300;400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
<div class="grid min-h-screen lg:grid-cols-[1fr_480px]">

    {{-- Brand panel. Stacked lockup on navy, per the brand kit's guidance for
         login and empty states. --}}
    <div class="relative hidden flex-col justify-between bg-navy p-12 lg:flex">
        <div class="flex items-center gap-3">
            <svg width="28" height="28" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                <path d="M54 10 L54 86 L14 108 Z" fill="#FFFFFF"/>
                <path d="M66 10 L106 108 L66 86 Z" fill="#4FE3FF"/>
            </svg>
            <div>
                <div class="font-display text-[17px] font-medium leading-none tracking-[0.06em] text-white">ALLVA</div>
                <div class="mt-1 text-[6px] font-medium tracking-[0.36em] text-dark-muted ps-[0.36em]">ACCOUNTING</div>
            </div>
        </div>

        <div class="max-w-lg">
            <h2 class="font-display text-3xl font-light leading-tight text-white">
                Every dinar of income and expense,<br>tracked and accounted for.
            </h2>
            <p class="mt-5 max-w-md text-sm leading-relaxed text-dark-text">
                The complete general ledger behind eTrackify — student subscriptions,
                revenue sharing, partner distributions, payroll and reporting.
            </p>

            <div class="mt-10 grid max-w-md grid-cols-3 gap-6 border-t border-dark-border pt-7">
                <div>
                    <div class="font-display text-2xl font-medium text-white">5</div>
                    <div class="eyebrow !text-dark-muted mt-1">Partners</div>
                </div>
                <div>
                    <div class="font-display text-2xl font-medium text-white">50<span class="text-signal">%</span></div>
                    <div class="eyebrow !text-dark-muted mt-1">Revenue share</div>
                </div>
                <div>
                    <div class="font-display text-2xl font-medium text-white">IQD</div>
                    <div class="eyebrow !text-dark-muted mt-1">Currency</div>
                </div>
            </div>
        </div>

        <div class="eyebrow !text-dark-muted">
            ALLVA Company · Baghdad, Iraq
        </div>
    </div>

    {{-- Form panel --}}
    <div class="flex items-center justify-center bg-white p-8">
        <div class="w-full max-w-[340px]">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <svg width="26" height="26" viewBox="0 0 120 120" fill="none" aria-hidden="true">
                    <path d="M54 10 L54 86 L14 108 Z" fill="#0A1F3D"/>
                    <path d="M66 10 L106 108 L66 86 Z" fill="#00C2E8"/>
                </svg>
                <div>
                    <div class="font-display text-[16px] font-medium leading-none tracking-[0.06em]">ALLVA</div>
                    <div class="mt-1 text-[6px] font-medium tracking-[0.36em] text-faint ps-[0.36em]">ACCOUNTING</div>
                </div>
            </div>

            <h1 class="font-display text-xl font-medium">Sign in</h1>
            <p class="mt-1.5 text-xs text-muted">Use the account your administrator issued you.</p>

            @if ($errors->any())
                <div class="mt-5 flex items-start gap-2.5 rounded-md border border-negative/25 bg-negative-soft
                            px-3.5 py-2.5 text-xs text-negative" role="alert">
                    <x-icon name="alert" class="mt-px size-4 shrink-0"/>
                    <div>{{ $errors->first() }}</div>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="mt-6 flex flex-col gap-4">
                @csrf

                <div>
                    <label class="label" for="email">Email address</label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="username"
                           class="input @error('email') input-invalid @enderror"
                           value="{{ old('email') }}" placeholder="you@allva.iq">
                </div>

                <div>
                    <label class="label" for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                           class="input @error('password') input-invalid @enderror" placeholder="••••••••">
                </div>

                <label class="flex cursor-pointer items-center gap-2 text-xs text-muted">
                    <input type="checkbox" name="remember" value="1" class="size-3.5 accent-navy">
                    Keep me signed in on this device
                </label>

                <button type="submit" class="btn btn-primary mt-1 w-full justify-center">Sign in</button>
            </form>

            <p class="mt-8 text-[11px] leading-relaxed text-faint">
                Every action you take in this system is recorded against your name,
                with the date and time.
            </p>
        </div>
    </div>
</div>
</body>
</html>
