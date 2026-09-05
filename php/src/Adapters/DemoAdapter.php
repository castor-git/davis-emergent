<?php
namespace App\Adapters;

/**
 * Demo adapter — generates realistic looking placeholder video items so the
 * whole site (categories, tags, filters, popular, featured) is browsable
 * without needing any real feed or API key.
 */
class DemoAdapter implements SourceAdapter {
    public function slug(): string { return 'demo'; }
    public function label(): string { return 'Davis Demo Network'; }

    public function fetch(int $limit = 100): iterable {
        $categories = ['Amateur','MILF','Teen','Anal','Lesbian','POV','Big Tits','Ebony','Asian','Latina','Blonde','Brunette','Redhead','Interracial','Casting'];
        $tags = ['hd','4k','vertical','solo','couple','outdoor','pov','uniform','tattoo','stockings','glasses','bikini','shower','massage','yoga','fitness','office','kitchen','pool','vintage','submissive','dominant','feet','oil','curvy'];
        $qualities = ['HD','FullHD','4K'];
        $adjectives = ['Sultry','Wild','Naughty','Passionate','Curious','Rough','Sensual','Kinky','Innocent','Fierce','Sweet','Playful'];
        $subjects = ['Redhead','Brunette','Blonde','Coed','Wife','Neighbor','Yoga Teacher','Stepsister','Roommate','Boss','Nurse','Model'];
        $actions = ['tries first casting','enjoys backyard fun','shares intimate afternoon','plays hard in gym','discovers hidden desires','breaks the rules on camera','loses control after party','celebrates late night','tests new lingerie','records private tape'];

        $out = [];
        for ($i = 1; $i <= $limit; $i++) {
            $title = $adjectives[array_rand($adjectives)] . ' ' . $subjects[array_rand($subjects)] . ' ' . $actions[array_rand($actions)];
            $catPick = array_rand($categories, 2);
            $tagPick = array_rand($tags, 4);
            $seed = 1000 + $i;
            $out[] = [
                'source' => 'demo',
                'source_video_id' => 'demo-' . $seed,
                'title' => $title,
                'description' => $title . '. A curated preview clip from the Davis Demo Network — replace with your real feed adapter.',
                'thumbnail' => "https://picsum.photos/seed/dv{$seed}/640/360",
                'preview' => "https://picsum.photos/seed/dv{$seed}p/320/180",
                'embed_url' => '', // demo has no real embed
                'page_url' => '',
                'duration' => rand(180, 2400),
                'views' => rand(1000, 900000),
                'rating' => round(6 + (mt_rand(0, 40) / 10), 1),
                'quality' => $qualities[array_rand($qualities)],
                'is_featured' => ($i <= 8) ? 1 : 0,
                'published_at' => date('Y-m-d H:i:s', time() - rand(0, 90*86400)),
                'categories' => array_map(fn($k)=>$categories[$k], (array)$catPick),
                'tags' => array_map(fn($k)=>$tags[$k], (array)$tagPick),
            ];
        }
        return $out;
    }
}
