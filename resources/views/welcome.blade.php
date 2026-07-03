<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'FollowMyLink Redirect Tester'])
    </head>
    <body class="min-h-screen bg-white text-zinc-950 antialiased dark:bg-zinc-950 dark:text-white">
        <main class="mx-auto flex w-full max-w-7xl flex-col gap-10 px-4 py-6 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 border-b border-zinc-200 pb-6 dark:border-zinc-800 md:flex-row md:items-end md:justify-between">
                <div class="max-w-3xl">
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">FollowMyLink</p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-normal text-zinc-950 dark:text-white">Redirect tester</h1>
                    <p class="mt-3 text-base leading-7 text-zinc-600 dark:text-zinc-300">
                        Trace server redirects, client-side hints, canonical tags, and security headers from the same request path a crawler or browser would use.
                    </p>
                </div>

                <a href="{{ route('home') }}" class="text-sm font-medium text-zinc-600 hover:text-zinc-950 dark:text-zinc-300 dark:hover:text-white">New test</a>
            </header>

            <livewire:redirect-tester-form />

            <section class="grid gap-4 border-t border-zinc-200 pt-8 dark:border-zinc-800 md:grid-cols-3">
                <div>
                    <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">Manual redirects</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Requests disable automatic following so every hop, status code, and Location target stays visible.</p>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">Crawler-aware checks</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Switch user agents to compare what desktop browsers, mobile browsers, and common crawlers receive.</p>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-zinc-950 dark:text-white">Defensive fetching</h2>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">Private networks, metadata addresses, unsupported schemes, credentials, and unusual ports are blocked before requests are sent.</p>
                </div>
            </section>
        </main>
        @fluxScripts
    </body>
</html>
