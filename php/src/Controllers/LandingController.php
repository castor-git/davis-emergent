<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Landing;

class LandingController {
    public function show(array $p): void {
        $l = Landing::bySlug($p['slug']);
        if (!$l) { http_response_code(404); View::render('pages/404',['title'=>'Not Found']); return; }
        Landing::incrementViews((int)$l['id']);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = Landing::videos($l, $page, 24);
        View::render('pages/landing', [
            'title' => $l['title'],
            'landing' => $l,
            'items' => $data['items'],
            'pages' => $data['pages'],
            'page' => $data['page'],
            'total' => $data['total'],
        ]);
    }
}
