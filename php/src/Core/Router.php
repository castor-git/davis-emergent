<?php
namespace App\Core;

class Router {
    private array $routes = [];
    public function get(string $pattern, callable $h): void { $this->add('GET', $pattern, $h); }
    public function post(string $pattern, callable $h): void { $this->add('POST', $pattern, $h); }
    private function add(string $m, string $p, callable $h): void {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $p) . '/?$#';
        $this->routes[] = compact('m', 'regex', 'h');
    }
    public function dispatch(string $method, string $uri): void {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes as $r) {
            if ($r['m'] === $method && preg_match($r['regex'], $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($r['h'], $params);
                return;
            }
        }
        http_response_code(404);
        View::render('pages/404', ['title' => 'Not Found']);
    }
}
