<?php

use App\Livewire\RedirectTesterForm;
use App\Services\RedirectTesting\DnsResolver;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('renders the homepage tester', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('FollowMyLink')
        ->assertSee('Redirect tester');
});

it('validates required url input', function () {
    Livewire::test(RedirectTesterForm::class)
        ->set('url', '')
        ->call('test')
        ->assertHasErrors(['url']);
});

it('renders a successful result and selected user agent', function () {
    app()->bind(DnsResolver::class, fn () => new class implements DnsResolver
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });

    Http::fake([
        'https://example.com/' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    Livewire::test(RedirectTesterForm::class)
        ->set('url', 'https://example.com')
        ->set('userAgent', 'slack')
        ->call('test')
        ->assertHasNoErrors()
        ->assertSee('200')
        ->assertSee('Slack unfurl bot');
});

it('rate limits the public route with a friendly response', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->get('/')->assertSuccessful();
    }

    $this->get('/')
        ->assertTooManyRequests()
        ->assertSee('Too many redirect checks. Please wait a minute and try again.');
});
