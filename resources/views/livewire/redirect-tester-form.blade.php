<div class="flex flex-col gap-8">
    <form wire:submit="test" class="rounded-2xl border border-border-muted bg-panel p-3 shadow-[0_0_32px_rgba(124,58,237,0.12)] sm:p-4">
        <div class="grid gap-3 lg:grid-cols-[1fr_16rem_auto] lg:items-end">
            <flux:field>
                <flux:label class="text-text-muted!">URL</flux:label>
                <flux:input type="url" wire:model="url" placeholder="https://example.com/page" autocomplete="url" icon="link" />
                <flux:error name="url" />
            </flux:field>

            <flux:field>
                <flux:label class="text-text-muted!">User agent</flux:label>
                <flux:select wire:model="userAgent" class="bg-panel-soft!">
                    @foreach ($this->userAgents() as $key => $agent)
                        <flux:select.option value="{{ $key }}">{{ $agent['label'] }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="userAgent" />
            </flux:field>

            <flux:button type="submit" variant="primary" icon="arrow-path" class="w-full bg-link-purple! text-white! hover:bg-link-purple-soft! hover:text-[#25005a]! lg:w-auto">
                Run test
            </flux:button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <flux:button type="button" size="sm" variant="subtle" wire:click="testWithScheme('https')" icon="lock-closed" class="border-border-muted! bg-panel-soft! text-text-base! hover:bg-panel-muted!">Try HTTPS</flux:button>
            <flux:button type="button" size="sm" variant="subtle" wire:click="testWithScheme('http')" icon="globe-alt" class="border-border-muted! bg-panel-soft! text-text-base! hover:bg-panel-muted!">Try HTTP</flux:button>
            @if ($result)
                <flux:button type="button" size="sm" variant="subtle" onclick="navigator.clipboard.writeText('{{ route('home') }}?url={{ urlencode($result['normalized_url']) }}')" icon="link" class="border-border-muted! bg-panel-soft! text-text-base! hover:bg-panel-muted!">Copy share URL</flux:button>
            @endif
        </div>

        <div wire:loading.flex wire:target="test,testWithScheme" class="mt-4 items-center gap-3 rounded-xl border border-link-purple/50 bg-link-purple/15 px-3 py-2 text-sm text-link-purple-soft">
            <flux:icon.arrow-path class="size-4 animate-spin" />
            Running redirect check...
        </div>
    </form>

    @if ($result)
        <section class="grid gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-border-muted bg-panel p-4">
                <p class="text-xs font-semibold uppercase text-text-muted">Final status</p>
                <p class="mt-2 font-display text-3xl font-semibold text-text-strong">{{ $result['final_status'] ?? 'Blocked' }}</p>
            </div>
            <div class="rounded-2xl border border-border-muted bg-panel p-4">
                <p class="text-xs font-semibold uppercase text-text-muted">Redirects</p>
                <p class="mt-2 font-display text-3xl font-semibold text-text-strong">{{ $result['redirect_count'] }}</p>
            </div>
            <div class="rounded-2xl border border-border-muted bg-panel p-4">
                <p class="text-xs font-semibold uppercase text-text-muted">Duration</p>
                <p class="mt-2 font-display text-3xl font-semibold text-text-strong">{{ $result['duration_ms'] }}ms</p>
            </div>
            <div class="rounded-2xl border border-border-muted bg-panel p-4">
                <p class="text-xs font-semibold uppercase text-text-muted">User agent</p>
                <p class="mt-2 text-sm font-semibold text-text-strong">{{ $result['user_agent']['label'] }}</p>
            </div>
        </section>

        @if ($result['warnings'])
            <section class="rounded-2xl border border-status-warning/40 bg-status-warning-bg p-4">
                <h2 class="text-sm font-semibold text-status-warning">Warnings</h2>
                <div class="mt-3 grid gap-2">
                    @foreach ($result['warnings'] as $warning)
                        <div class="text-sm text-text-base" wire:key="warning-{{ $loop->index }}">
                            <span class="font-medium">{{ str_replace('_', ' ', $warning['code']) }}:</span>
                            {{ $warning['message'] }}
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-border-muted bg-panel">
            <div class="flex flex-col gap-3 border-b border-border-muted p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-display text-2xl font-semibold text-text-strong">Redirect chain</h2>
                    <p class="mt-1 break-all text-sm text-text-muted">{{ $result['final_url'] }}</p>
                </div>
                <div class="flex gap-2">
                    <flux:button type="button" size="sm" variant="subtle" icon="clipboard" onclick="copyRedirectJson()" class="border-border-muted! bg-panel-soft! text-text-base! hover:bg-panel-muted!">Copy JSON</flux:button>
                    <flux:button type="button" size="sm" variant="subtle" icon="arrow-down-tray" onclick="downloadRedirectJson()" class="border-border-muted! bg-panel-soft! text-text-base! hover:bg-panel-muted!">Download</flux:button>
                </div>
            </div>

            <div class="relative p-4 sm:p-6">
                <div class="absolute bottom-8 left-8 top-8 hidden w-px bg-linear-to-b from-link-purple via-link-purple-soft/50 to-border-muted sm:block"></div>
                @foreach ($result['chain'] as $hop)
                    <article class="relative grid gap-3 py-4 first:pt-0 last:pb-0 sm:grid-cols-[2.5rem_1fr]" wire:key="hop-{{ $hop['index'] }}">
                        <div class="hidden sm:block">
                            <span class="relative z-10 flex size-8 items-center justify-center rounded-full border-2 {{ ($hop['status'] ?? 0) >= 200 && ($hop['status'] ?? 0) < 300 ? 'border-status-success bg-status-success-bg text-status-success' : 'border-link-purple bg-midnight text-link-purple-soft' }}">
                                @if (($hop['status'] ?? 0) >= 200 && ($hop['status'] ?? 0) < 300)
                                    <flux:icon.check class="size-4" />
                                @else
                                    <span class="size-2 rounded-full bg-current"></span>
                                @endif
                            </span>
                        </div>
                        <div class="min-w-0 rounded-2xl border border-border-muted bg-panel-soft p-4 transition hover:border-link-purple/70">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex rounded-full {{ ($hop['status'] ?? 0) >= 200 && ($hop['status'] ?? 0) < 300 ? 'bg-status-success-bg text-status-success' : 'bg-link-purple/20 text-link-purple-soft' }} px-3 py-1 text-sm font-semibold">{{ $hop['status'] ?? 'ERR' }}</span>
                                        <span class="text-sm text-text-muted">Hop {{ $hop['index'] + 1 }}</span>
                                    </div>
                                    <p class="mt-3 break-all text-sm font-semibold text-text-strong">{{ $hop['url'] }}</p>
                                </div>
                                <div class="shrink-0 text-left sm:text-right">
                                    <p class="text-sm font-semibold text-text-base">{{ $hop['duration_ms'] }}ms</p>
                                    <p class="text-xs text-text-muted">{{ $hop['ip_address'] }}</p>
                                </div>
                            </div>
                            <p class="mt-2 text-xs text-text-muted">{{ $hop['content_type'] ?? 'unknown content type' }}</p>
                            @if ($hop['redirect_to'])
                                <p class="mt-3 break-all rounded-xl border border-link-purple/30 bg-link-purple/10 px-3 py-2 text-sm text-link-purple-soft">{{ $hop['redirect_type'] }} redirect to {{ $hop['redirect_to'] }}</p>
                            @endif
                            <details class="mt-3">
                                <summary class="cursor-pointer text-sm font-medium text-link-purple-soft">Headers</summary>
                                <dl class="mt-2 grid gap-2 overflow-x-auto rounded-xl border border-border-muted bg-midnight p-3 text-xs">
                                    @foreach ($hop['headers'] as $name => $value)
                                        <div class="grid gap-1 md:grid-cols-[12rem_1fr]" wire:key="hop-{{ $hop['index'] }}-header-{{ $name }}">
                                            <dt class="font-mono text-link-purple-soft">{{ $name }}</dt>
                                            <dd class="break-all font-mono text-text-muted">{{ $value }}</dd>
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
            <div class="rounded-2xl border border-border-muted bg-panel p-5">
                <div class="flex items-center gap-2">
                    <flux:icon.magnifying-glass-circle class="size-6 text-status-warning" />
                    <h2 class="font-display text-xl font-semibold text-text-strong">SEO insights</h2>
                </div>
                <dl class="mt-4 grid gap-3 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-text-muted">HTTPS upgrade</dt>
                        <dd class="font-medium text-text-strong">{{ $result['https_upgrade'] ? 'Yes' : 'No' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-text-muted">Canonical</dt>
                        <dd class="break-all text-right font-medium text-text-strong">{{ $result['canonical']['url'] ?? $result['canonical']['message'] }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-4">
                        <dt class="text-text-muted">Canonical match</dt>
                        <dd class="font-medium text-text-strong">{{ is_bool($result['canonical']['matches_final_url']) ? ($result['canonical']['matches_final_url'] ? 'Yes' : 'No') : 'Not scanned' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-border-muted bg-panel p-5">
                <div class="flex items-center gap-2">
                    <flux:icon.shield-check class="size-6 text-link-purple-soft" />
                    <h2 class="font-display text-xl font-semibold text-text-strong">Security headers</h2>
                </div>
                <p class="mt-2 text-sm text-text-muted">Score {{ $result['security_headers']['score'] }} / 5</p>
                <dl class="mt-4 grid gap-2 text-sm">
                    @foreach ($result['security_headers']['present'] as $name => $value)
                        <div class="flex items-start justify-between gap-4" wire:key="present-{{ $name }}">
                            <dt class="font-mono text-text-muted">{{ $name }}</dt>
                            <dd class="rounded-full bg-status-success-bg px-2 py-1 text-right text-xs font-semibold text-status-success">Present</dd>
                        </div>
                    @endforeach
                    @foreach ($result['security_headers']['missing'] as $name)
                        <div class="flex items-start justify-between gap-4" wire:key="missing-{{ $name }}">
                            <dt class="font-mono text-text-muted">{{ $name }}</dt>
                            <dd class="rounded-full bg-status-danger-bg px-2 py-1 text-right text-xs font-semibold text-status-danger">Missing</dd>
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
