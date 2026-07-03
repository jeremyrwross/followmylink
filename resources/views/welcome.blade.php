<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => 'FollowMyLink | URL Redirect Checker & Link Tracer'])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    </head>
    <body class="fml-shell min-h-screen bg-midnight text-text-base antialiased selection:bg-link-purple selection:text-white">
        <header class="sticky top-0 z-50 border-b border-border-muted bg-midnight/95 backdrop-blur">
            <div class="mx-auto flex w-full max-w-[1120px] items-center justify-center px-4 py-4 sm:justify-between sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-link-purple-soft">
                    <x-app-logo-icon class="size-9" />
                    <span class="font-display text-2xl font-semibold text-link-purple-soft">FollowMyLink</span>
                </a>

                <div class="hidden items-center gap-3 sm:flex">
                    <span class="rounded-full border border-border-muted bg-panel-soft px-3 py-1 text-xs font-semibold text-text-muted">Real-time diagnostics</span>
                    <a href="{{ route('home') }}" class="rounded-lg bg-link-purple px-4 py-2 text-sm font-semibold text-white transition hover:bg-link-purple-soft hover:text-[#25005a]">
                        New test
                    </a>
                </div>
            </div>
        </header>

        <main class="mx-auto flex w-full max-w-[1120px] flex-col gap-12 px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
            <section class="flex flex-col items-center gap-5 text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-border-muted bg-panel px-4 py-2 text-sm text-text-muted">
                    <span class="size-2 rounded-full bg-status-success"></span>
                    Debug redirects, SEO signals, and headers in one trace
                </div>

                <div class="max-w-3xl">
                    <h1 class="font-display text-4xl font-semibold leading-tight text-text-strong sm:text-5xl lg:text-6xl">
                        Free URL Redirect Checker & Link Tracer
                    </h1>
                    <span class="sr-only">Redirect tester</span>
                    <p class="mx-auto mt-4 max-w-2xl text-base leading-7 text-text-muted sm:text-lg">
                        Trace complex HTTP redirect chains, debug 301 and 302 jumps, compare user agents, and inspect technical SEO headers with a focused diagnostic tool.
                    </p>
                </div>

                <div class="grid w-full max-w-2xl grid-cols-1 gap-3 text-sm text-text-muted sm:grid-cols-3">
                    <div class="flex items-center justify-center gap-2">
                        <flux:icon.check-circle class="size-4 text-status-success" />
                        10-hop depth
                    </div>
                    <div class="flex items-center justify-center gap-2">
                        <flux:icon.shield-check class="size-4 text-link-purple-soft" />
                        SSRF guarded
                    </div>
                    <div class="flex items-center justify-center gap-2">
                        <flux:icon.bolt class="size-4 text-status-warning" />
                        Latency tracking
                    </div>
                </div>
            </section>

            <livewire:redirect-tester-form />

            <section class="grid gap-4 md:grid-cols-3">
                <article class="rounded-2xl border border-border-muted bg-panel p-5">
                    <flux:icon.arrow-path-rounded-square class="size-6 text-link-purple-soft" />
                    <h2 class="mt-4 font-display text-xl font-semibold text-text-strong">Manual redirect tracing</h2>
                    <p class="mt-2 text-sm leading-6 text-text-muted">Automatic following is disabled so every hop, status code, destination, and response time stays visible.</p>
                </article>
                <article class="rounded-2xl border border-border-muted bg-panel p-5">
                    <flux:icon.cursor-arrow-rays class="size-6 text-status-warning" />
                    <h2 class="mt-4 font-display text-xl font-semibold text-text-strong">Crawler-aware checks</h2>
                    <p class="mt-2 text-sm leading-6 text-text-muted">Switch user agents to compare what desktop browsers, mobile browsers, and common crawlers receive.</p>
                </article>
                <article class="rounded-2xl border border-border-muted bg-panel p-5">
                    <flux:icon.lock-closed class="size-6 text-status-success" />
                    <h2 class="mt-4 font-display text-xl font-semibold text-text-strong">Defensive fetching</h2>
                    <p class="mt-2 text-sm leading-6 text-text-muted">Private networks, metadata addresses, unsupported schemes, credentials, and unusual ports are blocked before requests are sent.</p>
                </article>
            </section>
        </main>

        <footer class="border-t border-border-muted bg-midnight">
            <div class="mx-auto flex w-full max-w-[1120px] flex-col items-center justify-between gap-4 px-4 py-8 text-center text-sm text-text-muted sm:flex-row sm:px-6 sm:text-left lg:px-8">
                <div class="flex items-center gap-2">
                    <x-app-logo-icon class="size-6" />
                    <span class="font-display text-lg font-semibold text-link-purple-soft">FollowMyLink</span>
                </div>
                <p>Built for technical SEOs, developers, and security-minded link audits.</p>
            </div>
        </footer>

        @fluxScripts
    </body>
</html>
