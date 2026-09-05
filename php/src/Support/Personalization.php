<?php
namespace App\Support;

/**
 * Lightweight per-visitor personalization signal.
 * Category interactions are tallied into a compact cookie `dv_taste`
 * (format: "slug:count;slug:count..."), capped at 10 entries.
 */
class Personalization {
    private const COOKIE = 'dv_taste';
    private const MAX_ENTRIES = 10;

    public static function record(array $categorySlugs): void {
        $current = self::read();
        foreach ($categorySlugs as $slug) {
            $slug = trim((string)$slug);
            if ($slug === '') continue;
            $current[$slug] = ($current[$slug] ?? 0) + 1;
        }
        arsort($current);
        $current = array_slice($current, 0, self::MAX_ENTRIES, true);
        self::write($current);
    }

    public static function topCategory(): ?string {
        $t = self::read();
        if (!$t) return null;
        arsort($t);
        return array_key_first($t);
    }

    public static function taste(): array {
        return self::read();
    }

    private static function read(): array {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if ($raw === '') return [];
        $out = [];
        foreach (explode(';', $raw) as $pair) {
            [$k, $v] = array_pad(explode(':', $pair, 2), 2, '0');
            $k = preg_replace('/[^a-z0-9\-]/', '', $k);
            if ($k) $out[$k] = (int)$v;
        }
        return $out;
    }

    private static function write(array $t): void {
        $enc = implode(';', array_map(fn($k,$v)=>$k.':'.$v, array_keys($t), array_values($t)));
        setcookie(self::COOKIE, $enc, [
            'expires' => time() + 90*86400,
            'path' => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $enc;
    }
}
