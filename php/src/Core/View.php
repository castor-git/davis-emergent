<?php
namespace App\Core;

class View {
    public static function render(string $tpl, array $data = [], string $layout = 'main'): void {
        extract($data);
        $content_path = __DIR__ . '/../Views/' . $tpl . '.php';
        ob_start();
        include $content_path;
        $content = ob_get_clean();
        if ($layout === null) { echo $content; return; }
        include __DIR__ . '/../Views/layouts/' . $layout . '.php';
    }
    public static function json($data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    public static function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
