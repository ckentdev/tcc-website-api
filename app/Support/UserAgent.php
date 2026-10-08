<?php

namespace App\Support;

final class UserAgent
{
    /**
     * @return array{browser: string, platform: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return ['browser' => 'Unknown', 'platform' => 'Unknown'];
        }

        return [
            'browser' => self::browser($ua),
            'platform' => self::platform($ua),
        ];
    }

    private static function browser(string $ua): string
    {
        if (preg_match('/Edg(?:e|A|iOS)?\//i', $ua)) {
            return 'Edge';
        }
        if (preg_match('/OPR\/|Opera/i', $ua)) {
            return 'Opera';
        }
        if (preg_match('/SamsungBrowser/i', $ua)) {
            return 'Samsung Internet';
        }
        if (preg_match('/Firefox|FxiOS/i', $ua)) {
            return 'Firefox';
        }
        if (preg_match('/Chrome|CriOS/i', $ua)) {
            return 'Chrome';
        }
        if (preg_match('/Safari/i', $ua)) {
            return 'Safari';
        }

        return 'Other';
    }

    private static function platform(string $ua): string
    {
        if (preg_match('/Android/i', $ua)) {
            return 'Android';
        }
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            return 'iOS';
        }
        if (preg_match('/Windows/i', $ua)) {
            return 'Windows';
        }
        if (preg_match('/Mac OS X|Macintosh/i', $ua)) {
            return 'macOS';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }

        return 'Other';
    }
}
