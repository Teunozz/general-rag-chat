<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parsed = parse_url($value);

        if (! $parsed || empty($parsed['host'])) {
            $fail('The :attribute must be a valid URL.');
            return;
        }

        $host = $parsed['host'];
        $scheme = strtolower($parsed['scheme'] ?? '');

        // Only allow http/https
        if (! in_array($scheme, ['http', 'https'])) {
            $fail('The :attribute must use HTTP or HTTPS.');
            return;
        }

        // Block localhost and common local hostnames
        $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1', '[::1]'];
        if (in_array(strtolower($host), $blockedHosts)) {
            $fail('The :attribute must not point to a local address.');
            return;
        }

        // Resolve hostname to IP and check for private ranges
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // Cannot resolve — allow and let the actual request fail later
            return;
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                $fail('The :attribute must not point to a private or reserved IP address.');
                return;
            }
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
