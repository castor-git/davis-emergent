<?php
namespace App\Core;

class App {
    public static array $config = [];
    public static ?\PDO $db = null;

    public static function boot(): void {
        self::$config = require __DIR__ . '/../../config/config.php';
        $c = self::$config['db'];
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
        self::$db = new \PDO($dsn, $c['user'], $c['pass'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        Schema::migrate(self::$db);
        Seeder::run();
    }

    public static function config(string $key, $default = null) {
        $parts = explode('.', $key);
        $val = self::$config;
        foreach ($parts as $p) { if (!isset($val[$p])) return $default; $val = $val[$p]; }
        return $val;
    }
}
