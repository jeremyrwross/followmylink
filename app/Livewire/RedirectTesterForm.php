<?php

namespace App\Livewire;

use App\Services\RedirectTester;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class RedirectTesterForm extends Component
{
    public string $url = '';

    public string $userAgent = 'desktop_chrome';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $result = null;

    public function mount(): void
    {
        $this->url = (string) request()->query('url', '');
    }

    public function test(RedirectTester $tester): void
    {
        $this->validate([
            'url' => ['required', 'string', 'max:2048'],
            'userAgent' => ['required', 'string', 'in:'.implode(',', array_keys(RedirectTester::userAgents()))],
        ], [
            'url.required' => 'Enter a URL to test.',
            'userAgent.in' => 'Choose a supported user agent.',
        ]);

        $key = 'followmylink:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 20)) {
            throw ValidationException::withMessages([
                'url' => 'Too many redirect checks. Please wait a minute and try again.',
            ]);
        }

        RateLimiter::hit($key, 60);

        $this->result = $tester->test($this->url, $this->userAgent)->toArray();
        $this->url = $this->result['normalized_url'];
    }

    public function testWithScheme(string $scheme): void
    {
        $host = preg_replace('#^https?://#i', '', trim($this->url));

        $this->url = $scheme.'://'.$host;
        $this->test(app(RedirectTester::class));
    }

    /**
     * @return array<string, array{label: string, value: string}>
     */
    public function userAgents(): array
    {
        return RedirectTester::userAgents();
    }

    public function render(): mixed
    {
        return view('livewire.redirect-tester-form');
    }
}
