<?php

namespace App\Services\RedirectTesting;

interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array;
}
