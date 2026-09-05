<?php
namespace App\Controllers;

use App\Core\{View, Cache, App};
use App\Models\{Video, Taxonomy};

class HomeController {
    public function index(): void {
        $data = Cache::remember('home:v1', 300, function () {
            return [
                'featured' => Video::paginate(['featured'=>1,'sort'=>'popular'], 1, 8)['items'],
                'popular' => Video::paginate(['sort'=>'popular'], 1, 12)['items'],
                'newest' => Video::paginate(['sort'=>'newest'], 1, 12)['items'],
                'top_rated' => Video::paginate(['sort'=>'rating'], 1, 6)['items'],
                'categories' => Taxonomy::popularCategories(18),
                'tags' => Taxonomy::popularTags(30),
            ];
        });
        View::render('pages/home', $data + ['title' => App::config('app_name') . ' — ' . App::config('tagline')]);
    }
}
