<?php
namespace App\Controllers;

use App\Core\{View, Cache, App};
use App\Models\{Video, Taxonomy};

class HomeController {
    public function index(): void {
        // Personalized bits (top category + explicit prefs) live outside the shared home cache
        $topCat = \App\Support\Personalization::topCategory();
        $pins = \App\Support\Preferences::pins();
        $hides = \App\Support\Preferences::hides();
        // Pinned categories override the implicit top category
        $boost = $pins[0] ?? $topCat;

        $data = Cache::remember('home:v1', 300, function () {
            return [
                'featured' => Video::paginate(['featured'=>1,'sort'=>'popular'], 1, 12)['items'],
                'popular' => Video::paginate(['sort'=>'popular'], 1, 12)['items'],
                'newest' => Video::paginate(['sort'=>'newest'], 1, 12)['items'],
                'top_rated' => Video::paginate(['sort'=>'rating'], 1, 6)['items'],
                'categories' => Taxonomy::popularCategories(30),
                'tags' => Taxonomy::popularTags(30),
            ];
        });

        // Reorder chips: pinned first, hidden filtered out
        if ($pins || $hides) {
            $data['categories'] = self::reorderChips($data['categories'], $pins, $hides);
        }
        $tagPins = \App\Support\Preferences::pins('tag');
        $tagHides = \App\Support\Preferences::hides('tag');
        if ($tagPins || $tagHides) {
            $data['tags'] = self::reorderChips($data['tags'], $tagPins, $tagHides);
        }
        $data['tag_pins'] = $tagPins;
        $data['tag_hides'] = $tagHides;

        $data['trending_landings'] = \App\Models\Landing::trending(6, $boost);
        $data['top_category'] = $topCat;
        $data['pins'] = $pins;
        $data['hides'] = $hides;
        $data['boost_category'] = $boost;
        $data['taste_row'] = null;
        if ($boost && !in_array($boost, $hides, true)) {
            $cat = Taxonomy::categoryBySlug($boost);
            if ($cat && (int)$cat['video_count'] >= 3) {
                $items = Video::paginate(['category'=>$boost,'sort'=>'popular'], 1, 12)['items'];
                if (count($items) >= 3) $data['taste_row'] = ['category'=>$cat, 'items'=>$items, 'is_pin'=>in_array($boost, $pins, true)];
            }
        }
        View::render('pages/home', $data + ['title' => App::config('app_name') . ' — ' . App::config('tagline')]);
    }

    private static function reorderChips(array $rows, array $pins, array $hides): array {
        $pinnedRows = []; $rest = [];
        foreach ($rows as $c) {
            if (in_array($c['slug'], $hides, true)) continue;
            if (in_array($c['slug'], $pins, true)) $pinnedRows[array_search($c['slug'], $pins, true)] = $c;
            else $rest[] = $c;
        }
        ksort($pinnedRows);
        return array_merge(array_values($pinnedRows), $rest);
    }
}
