<div class="flex flex-col gap-6">
    <form wire:submit="test" class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/40">
        <div class="grid gap-4 lg:grid-cols-[1fr_16rem_auto] lg:items-end">
            <flux:field>
                <flux:label>URL</flux:label>
                <flux:input type="url" wire:model="url" placeholder="https://example.com/page" autocomplete="url" />
                <flux:error name="url" />
            </flux:field>

            <flux:field>
                <flux:label>User agent</flux:label>
                <flux:select wire:model="userAgent">
                    @foreach ($this->userAgents() as $key => $agent)
                        <flux:select.option value="{{ $key }}">{{ $agent['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="userAgent" />
            </flux:field>

            <flux:button type="submit" variant="primary" icon="arrow-path" class="w-full lg:w-auto">
                Run test
            </flux:button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <flux:button type="button" size="sm" variant="subtle" wire:click="testWithScheme('https')" icon="lock-closed">Try HTTPS</flux:button>
            <flux:button type="button" size="sm" variant="subtle" wire:click="testWithScheme('http')" icon="globe-alt">Try HTTP</flux:button>
            @if ($result)
                <flux:button type="button" size="sm" variant="subtle" onclick="navigator.clipboard.writeText('{{ route('home') }}?url={{ urlencode($result['normalized_url']) }}')" icon="link">Copy share URL</flux:button>
            @endif
        </div>

        <div wire:loading.flex wire:target="test,testWithScheme" class="mt-4 items-center gap-3 rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-sm text-teal-900 dark:border-teal-900/60 dark:bg-teal-950/40 dark:text-teal-100">
            <flux:icon.arrow-path class="size-4 animate-spin" />
            Running redirect check...
        </div>
    </form>

    @if ($result)
        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Final status</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result['final_status'] ?? 'Blocked' }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Redirects</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result['redirect_count'] }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Duration</p>
                <p class="mt-2 text-2xl font-semibold">{{ $result['duration_ms'] }}ms</p>
            </div>
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <p class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">User agent</p>
                <p class="mt-2 text-sm font-semibold">{{ $result['user_agent']['label'] }}</p>
            </div>
        </section>

        @if ($result['warnings'])
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/70 dark:bg-amber-950/30">
                <h2 class="text-sm font-semibold text-amber-950 dark:text-amber-100">Warnings</h2>
                <div class="mt-3 grid gap-2">
                    @foreach ($result['warnings'] as $warning)
                        <div class="text-sm text-amber-900 dark:text-amber-100" wire:key="warning-{{ $loop->index }}">
                            <span class="font-medium">{{ str_replace('_', ' ', $warning['code']) }}:</span>
                            {{ $warning['message'] }}
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-lg border border-zinc-200 dark:border-zinc-800">
            <div class="flex flex-col gap-3 border-b border-zinc-200 p-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Redirect chain</h2>
                    <p class="mt-1 break-all text-sm text-zinc-600 dark:text-zinc-400">{{ $result['final_url'] }}</p>
                </div>
                <div class="flex gap-2">
                    <flux:button type="button" size="sm" variant="subtle" icon="clipboard" onclick="copyRedirectJson()">Copy JSON</flux:button>
                    <flux:button type="button" size="sm" variant="subtle" icon="arrow-down-tray" onclick="downloadRedirectJson()">Download</flux:button>
                </div>
            </div>

            <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @foreach ($result['chain'] as $hop)
                    <article class="grid gap-3 p-4 md:grid-cols-[7rem_1fr]" wire:key="hop-{{ $hop['index'] }}">
                        <div>
                            <span class="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-sm font-semibold text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100">{{ $hop['status'] ?? 'ERR' }}</span>
                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">Hop {{ $hop['index'] + 1 }} · {{ $hop['duration_ms'] }}ms</p>
                        </div>
                        <div class="min-w-0">
                            <p class="break-all text-sm font-medium">{{ $hop['url'] }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $hop['ip_address'] }} · {{ $hop['content_type'] ?? 'unknown content type' }}</p>
                            @if ($hop['redirect_to'])
                                <p class="mt-2 break-all text-sm text-teal-700 dark:text-teal-300">{{ $hop['redirect_type'] }} redirect to {{ $hop['redirect_to'] }}</p>
                            @endif
                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm font-medium text-zinc-700 dark:text-zinc-300">Headers</summary>
                                <dl class="mt-2 grid gap-2 rounded-md bg-zinc-50 p-3 text-xs dark:bg-zinc-900">
                                    @foreach ($hop['headers'] as $name => $value)
                                        <div class="grid gap-1 md:grid-cols-[12rem_1fr]" wire:key="hop-{{ $hop['index'] }}-header-{{ $name }}">
                                            <dt class="font-mono text-zinc-500 dark:text-zinc-400">{{ $name }}</dt>
                                            <dd class="break-all font-mono">{{ $value }}</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            </details>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <h2 class="text-base font-semibold">SEO insights</h2>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400">HTTPS upgrade</dt>
                        <dd class="font-medium">{{ $result['https_upgrade'] ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400">Canonical</dt>
                        <dd class="break-all text-right font-medium">{{ $result['canonical']['url'] ?? $result['canonical']['message'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-zinc-500 dark:text-zinc-400">Canonical match</dt>
                        <dd class="font-medium">{{ is_bool($result['canonical']['matches_final_url']) ? ($result['canonical']['matches_final_url'] ? 'Yes' : 'No') : 'Not scanned' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <h2 class="text-base font-semibold">Security headers</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Score {{ $result['security_headers']['score'] }} / 5</p>
                <dl class="mt-4 grid gap-2 text-sm">
                    @foreach ($result['security_headers']['present'] as $name => $value)
                        <div class="flex items-start justify-between gap-4" wire:key="present-{{ $name }}">
                            <dt class="font-mono text-zinc-600 dark:text-zinc-400">{{ $name }}</dt>
                            <dd class="text-right text-emerald-700 dark:text-emerald-300">Present</dd>
                        </div>
                    @endforeach
                    @foreach ($result['security_headers']['missing'] as $name)
                        <div class="flex items-start justify-between gap-4" wire:key="missing-{{ $name }}">
                            <dt class="font-mono text-zinc-600 dark:text-zinc-400">{{ $name }}</dt>
                            <dd class="text-right text-zinc-500 dark:text-zinc-400">Missing</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        <script type="application/json" id="redirect-result-json">@json($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)</script>
        <script>
            function redirectResultJson() {
                return document.getElementById('redirect-result-json')?.textContent || '{}';
            }

            function copyRedirectJson() {
                navigator.clipboard.writeText(redirectResultJson());
            }

            function downloadRedirectJson() {
                const blob = new Blob([redirectResultJson()], { type: 'application/json' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'followmylink-redirect-test.json';
                link.click();
                URL.revokeObjectURL(link.href);
            }
        </script>
    @endif
</div>
