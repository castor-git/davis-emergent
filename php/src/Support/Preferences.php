<?php
namespace App\Support;

/**
 * Explicit visitor preferences (cookie-based, first-party only).
 * Supports two taxonomies: 'category' and 'tag'.
 * Cookies (each capped at 20 slugs, 180-day expiry):
 *   category → dv_pins / dv_hides
 *   tag      → dv_tag_pins / dv_tag_hides
 */
class Preferences {
    private const MAX_ENTRIES = 20;
    private const COOKIES = [
        'category' => ['pin' => 'dv_pins',     'hide' => 'dv_hides'],
        'tag'      => ['pin' => 'dv_tag_pins', 'hide' => 'dv_tag_hides'],
    ];

    private static function ck(string $type, string $action): string {
        return self::COOKIES[$type][$action] ?? throw new \InvalidArgumentException("bad type/action");
    }

    public static function pins(string $type = 'category'): array  { return self::read(self::ck($type,'pin')); }
    public static function hides(string $type = 'category'): array { return self::read(self::ck($type,'hide')); }

    public static function togglePin(string $slug, string $type = 'category'): array {
        $pins = self::pins($type);
        if (in_array($slug, $pins, true)) $pins = array_values(array_diff($pins, [$slug]));
        else { array_unshift($pins, $slug); $pins = array_values(array_unique($pins)); }
        $hides = array_values(array_diff(self::hides($type), [$slug]));
        self::write(self::ck($type,'pin'), array_slice($pins, 0, self::MAX_ENTRIES));
        self::write(self::ck($type,'hide'), $hides);
        return $pins;
    }

    public static function toggleHide(string $slug, string $type = 'category'): array {
        $hides = self::hides($type);
        if (in_array($slug, $hides, true)) $hides = array_values(array_diff($hides, [$slug]));
        else { $hides[] = $slug; $hides = array_values(array_unique($hides)); }
        $pins = array_values(array_diff(self::pins($type), [$slug]));
        self::write(self::ck($type,'hide'), array_slice($hides, 0, self::MAX_ENTRIES));
        self::write(self::ck($type,'pin'), $pins);
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
