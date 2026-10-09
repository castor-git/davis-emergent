<?php
namespace App\Controllers;

use App\Core\{View, App};
use App\Models\{Video, Taxonomy};
use App\Models\Credit;

class BrowseController {
    public function videos(array $params = []): void {
        $filters = $_GET + $params;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = (int)App::config('per_page', 24);
        $data = Video::paginate($filters, $page, $per);
        $data['filters'] = $filters;
        $data['categories'] = Taxonomy::popularCategories(30);
        $data['tags'] = Taxonomy::popularTags(40);
        $data['title'] = 'Browse videos';
        View::render('pages/browse', $data);
    }

    public function category(array $p): void {
        $cat = Taxonomy::categoryBySlug($p['slug']);
        if (!$cat) { http_response_code(404); View::render('pages/404',['title'=>'Not Found']); return; }
        $_GET['category'] = $cat['slug'];
        $filters = $_GET;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = Video::paginate($filters, $page, (int)App::config('per_page'));
        $data['filters'] = $filters;
        $data['category'] = $cat;
        $data['categories'] = Taxonomy::popularCategories(30);
        $data['tags'] = Taxonomy::popularTags(40);
        $data['title'] = $cat['name'] . ' videos';
        View::render('pages/browse', $data);
    }

    public function tag(array $p): void {
        $tag = Taxonomy::tagBySlug($p['slug']);
        if (!$tag) { http_response_code(404); View::render('pages/404',['title'=>'Not Found']); return; }
        $_GET['tag'] = $tag['slug'];
        $filters = $_GET;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = Video::paginate($filters, $page, (int)App::config('per_page'));
        $data['filters'] = $filters;
        $data['tag'] = $tag;
        $data['categories'] = Taxonomy::popularCategories(30);
        $data['tags'] = Taxonomy::popularTags(40);
        $data['title'] = '#' . $tag['name'] . ' videos';
        View::render('pages/browse', $data);
    }

    public function actor(array $p): void {
        $actor = Credit::actorBySlug($p['slug']);
        if (!$actor) {
            http_response_code(404);
            View::render('pages/404', ['title' => 'Not Found']);
            return;
        }
        $this->renderCreditListing($actor, 'actor', 'Aktorzy');
    }

    public function studio(array $p): void {
        $studio = Credit::studioBySlug($p['slug']);
        if (!$studio) {
            http_response_code(404);
            View::render('pages/404', ['title' => 'Not Found']);
            return;
        }
        $this->renderCreditListing($studio, 'studio', 'Wytwórnia');
    }

    public function video(array $p): void {
        $video = Video::bySlug($p['slug']);
        if (!$video) { http_response_code(404); View::render('pages/404',['title'=>'Not Found']); return; }
        Video::incrementViews((int)$video['id']);
        $cats = Video::categoriesFor((int)$video['id']);
        $tags = Video::tagsFor((int)$video['id']);
        $actors = Video::actorsFor((int)$video['id']);
        $studios = Video::studiosFor((int)$video['id']);
        // Personalization signal — remember which categories this visitor consumes
        \App\Support\Personalization::record(array_column($cats, 'slug'));
        $related = Video::related((int)$video['id'], 8);
        View::render('pages/video', [
            'title' => $video['title'],
            'video' => $video,
            'cats' => $cats,
            'tags_list' => $tags,
            'actors' => $actors,
            'studios' => $studios,
            'related' => $related,
        ]);
    }

    public function search(): void {
        $q = trim((string)($_GET['q'] ?? ''));
        $_GET['q'] = $q;
        $filters = $_GET;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = Video::paginate($filters, $page, (int)App::config('per_page'));
        if ($q !== '' && $page === 1) {
            \App\Models\SearchQuery::log($q, (int)$data['total']);
        }
        $data['filters'] = $filters;
        $data['categories'] = Taxonomy::popularCategories(30);
        $data['tags'] = Taxonomy::popularTags(40);
        $data['title'] = 'Search: ' . $q;
        $data['is_search'] = true;
        View::render('pages/browse', $data);
    }

    public function suggest(): void {
        $q = trim((string)($_GET['q'] ?? ''));
        if (strlen($q) < 2) { View::json(['items'=>[]]); return; }
        View::json(['items' => Video::suggest($q)]);
    }

    private function renderCreditListing(array $credit, string $type, string $label): void {
        $_GET[$type] = $credit['slug'];
        $filters = $_GET;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $data = Video::paginate($filters, $page, (int)App::config('per_page'));
        $data['filters'] = $filters;
        $data['categories'] = Taxonomy::popularCategories(30);
        $data['tags'] = Taxonomy::popularTags(40);
        $data['title'] = $label . ': ' . $credit['name'];
        View::render('pages/browse', $data);
    }
}
