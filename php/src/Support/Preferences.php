<?php
namespace App\Support;

/**
 * Explicit visitor preferences (cookie-based, first-party only):
 *   dv_pins  — comma-separated category slugs the visitor pinned  (boosted)
 *   dv_hides — comma-separated category slugs the visitor hid     (excluded)
 * Both are capped at 20 entries and expire in 180 days.
 */
class Preferences {
    private const COOKIE_PINS = 'dv_pins';
    private const COOKIE_HIDES = 'dv_hides';
    private const MAX_ENTRIES = 20;

    public static function pins(): array { return self::read(self::COOKIE_PINS); }
    public static function hides(): array { return self::read(self::COOKIE_HIDES); }

    public static function togglePin(string $slug): array {
        $pins = self::pins();
        if (in_array($slug, $pins, true)) $pins = array_values(array_diff($pins, [$slug]));
        else { array_unshift($pins, $slug); $pins = array_values(array_unique($pins)); }
        // A pinned category must not stay hidden
        $hides = array_values(array_diff(self::hides(), [$slug]));
        self::write(self::COOKIE_PINS, array_slice($pins, 0, self::MAX_ENTRIES));
        self::write(self::COOKIE_HIDES, $hides);
        return $pins;
    }

    public static function toggleHide(string $slug): array {
        $hides = self::hides();
        if (in_array($slug, $hides, true)) $hides = array_values(array_diff($hides, [$slug]));
        else { $hides[] = $slug; $hides = array_values(array_unique($hides)); }
        // A hidden category must not stay pinned
        $pins = array_values(array_diff(self::pins(), [$slug]));
        self::write(self::COOKIE_HIDES, array_slice($hides, 0, self::MAX_ENTRIES));
        self::write(self::COOKIE_PINS, $pins);
        return $hides;
    }

    private static function read(string $name): array {
        $raw = $_COOKIE[$name] ?? '';
        if ($raw === '') return [];
        $parts = array_map('trim', explode(',', $raw));
        return array_values(array_filter($parts, fn($s) => (bool)preg_match('/^[a-z0-9\-]+$/', $s)));
    }

    private static function write(string $name, array $list): void {
        $value = implode(',', $list);
        setcookie($name, $value, [
            'expires' => time() + 180*86400,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE[$name] = $value;
    }
}
