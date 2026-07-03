<?php

namespace App\Services\RedirectTesting;

final class NativeDnsResolver implements DnsResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        $records = dns_get_record($host, DNS_A + DNS_AAAA);

        if ($records === false) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
