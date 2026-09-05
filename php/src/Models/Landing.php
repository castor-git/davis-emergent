<?php
namespace App\Models;

use App\Core\App;
use App\Support\SourceManager;

class Landing {
    public static function all(): array {
        return App::$db->query("SELECT * FROM landings ORDER BY created_at DESC")->fetchAll();
    }

    public static function bySlug(string $slug): ?array {
        $st = App::$db->prepare("SELECT * FROM landings WHERE slug=? AND active=1");
        $st->execute([$slug]);
        return $st->fetch() ?: null;
    }

    public static function find(int $id): ?array {
        $st = App::$db->prepare("SELECT * FROM landings WHERE id=?"); $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function save(array $d, ?int $id = null): int {
        $db = App::$db;
        $slug = trim($d['slug'] ?? '');
        if ($slug === '') $slug = SourceManager::slugify($d['title'] ?? ($d['keyword'] ?? 'landing'));
        $slug = substr($slug, 0, 90);
        // Ensure unique slug on create
        if (!$id) {
            $base = $slug; $n = 2;
            while ((bool)$db->prepare("SELECT 1 FROM landings WHERE slug=?")->execute([$slug]) && $db->query("SELECT COUNT(*) FROM landings WHERE slug=".$db->quote($slug))->fetchColumn()) {
                $slug = $base . '-' . $n++; if ($n > 50) break;
            }
        }
        $payload = [
            $slug,
            $d['title'] ?: ucfirst($d['keyword'] ?? 'Landing'),
            $d['keyword'] ?? null,
            $d['intro'] ?? null,
            json_encode(array_values(array_filter(array_map('trim', (array)($d['categories'] ?? []))))),
            json_encode(array_values(array_filter(array_map('trim', (array)($d['tags'] ?? []))))),
            trim((string)($d['meta_title'] ?? '')) ?: null,
            trim((string)($d['meta_description'] ?? '')) ?: null,
            trim((string)($d['og_image'] ?? '')) ?: null,
            in_array(($d['template'] ?? 'grid'), ['grid','editorial','top10'], true) ? $d['template'] : 'grid',
            trim((string)($d['title_variant_b'] ?? '')) ?: null,
            (int)!empty($d['active']),
        ];
        if ($id) {
            $st = $db->prepare("UPDATE landings SET slug=?, title=?, keyword=?, intro=?, categories_json=?, tags_json=?, meta_title=?, meta_description=?, og_image=?, template=?, title_variant_b=?, active=? WHERE id=?");
            $st->execute([...$payload, $id]);
            return $id;
        }
        $st = $db->prepare("INSERT INTO landings (slug,title,keyword,intro,categories_json,tags_json,meta_title,meta_description,og_image,template,title_variant_b,active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $st->execute($payload);
        return (int)$db->lastInsertId();
    }

    /** Record an impression and return which variant to show. */
    public static function pickVariant(array $landing): string {
        $id = (int)$landing['id'];
        $b = trim((string)($landing['title_variant_b'] ?? ''));
        if ($b === '') return 'A';
        $cookie = 'l_ab_' . $id;
        $v = $_COOKIE[$cookie] ?? '';
        if ($v !== 'A' && $v !== 'B') {
            $v = (random_int(0, 1) === 0) ? 'A' : 'B';
            setcookie($cookie, $v, [
                'expires' => time() + 30 * 86400,
                'path' => '/',
                'samesite' => 'Lax',
            ]);
        }
        return $v;
    }

    public static function bumpImpression(int $id, string $variant): void {
        App::$db->prepare("INSERT INTO landing_ab_stats (landing_id, variant, impressions) VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE impressions = impressions + 1")->execute([$id, $variant]);
    }

    public static function bumpClick(int $id, string $variant): void {
        App::$db->prepare("INSERT INTO landing_ab_stats (landing_id, variant, clicks) VALUES (?, ?, 1)
            ON DUPLICATE KEY UPDATE clicks = clicks + 1")->execute([$id, $variant]);
    }

    public static function abStats(int $id): array {
        $st = App::$db->prepare("SELECT variant, impressions, clicks FROM landing_ab_stats WHERE landing_id=?");
        $st->execute([$id]);
        $rows = ['A'=>['impressions'=>0,'clicks'=>0], 'B'=>['impressions'=>0,'clicks'=>0]];
        foreach ($st->fetchAll() as $r) $rows[$r['variant']] = ['impressions'=>(int)$r['impressions'], 'clicks'=>(int)$r['clicks']];
        return $rows;
    }

    public static function delete(int $id): void {
        App::$db->prepare("DELETE FROM landings WHERE id=?")->execute([$id]);
    }

    public static function videos(array $landing, int $page = 1, int $per = 24): array {
        $cats = json_decode($landing['categories_json'] ?: '[]', true) ?: [];
        $tags = json_decode($landing['tags_json'] ?: '[]', true) ?: [];
        $keyword = trim((string)($landing['keyword'] ?? ''));
        $conds = []; $params = [];
        if ($cats) {
            $ph = implode(',', array_fill(0, count($cats), '?'));
            $conds[] = "v.id IN (SELECT vc.video_id FROM video_categories vc JOIN categories c ON c.id=vc.category_id WHERE c.slug IN ($ph))";
            $params = array_merge($params, $cats);
        }
        if ($tags) {
            $ph = implode(',', array_fill(0, count($tags), '?'));
            $conds[] = "v.id IN (SELECT vt.video_id FROM video_tags vt JOIN tags t ON t.id=vt.tag_id WHERE t.slug IN ($ph))";
            $params = array_merge($params, $tags);
        }
        if ($keyword !== '') {
            $conds[] = "(v.title LIKE ? OR v.description LIKE ?)";
            $params[] = "%$keyword%"; $params[] = "%$keyword%";
        }
        // Union semantics: a video is included if it matches ANY chosen bucket.
        $where = $conds ? ('WHERE ' . implode(' OR ', $conds)) : 'WHERE 1=0';
        $sql = "SELECT v.* FROM videos v $where ORDER BY v.views DESC LIMIT ? OFFSET ?";
        $countSql = "SELECT COUNT(*) FROM videos v $where";
        $db = App::$db;
        $cst = $db->prepare($countSql); foreach ($params as $i=>$p) $cst->bindValue($i+1, $p); $cst->execute();
        $total = (int)$cst->fetchColumn();
        $st = $db->prepare($sql);
        $i = 1; foreach ($params as $p) { $st->bindValue($i++, $p); }
        $st->bindValue($i++, $per, \PDO::PARAM_INT);
        $st->bindValue($i, ($page-1)*$per, \PDO::PARAM_INT);
        $st->execute();
        return ['items'=>$st->fetchAll(), 'total'=>$total, 'page'=>$page, 'per'=>$per, 'pages'=>max(1,(int)ceil($total/$per))];
    }

    public static function incrementViews(int $id): void {
        App::$db->prepare("UPDATE landings SET views = views + 1 WHERE id=?")->execute([$id]);
    }

    /** Rank active landings by combined CTR (falls back to views when data is thin). */
    public static function trending(int $limit = 6, ?string $boostCategory = null): array {
        // Compute a "boost" score: 1 if the landing includes the visitor's top category, 0 otherwise.
        // We JSON_CONTAINS check on categories_json.
        $boostExpr = "0";
        $params = [];
        if ($boostCategory) {
            $boostExpr = "IF(JSON_CONTAINS(l.categories_json, JSON_QUOTE(?), '$'), 1, 0)";
            $params[] = $boostCategory;
        }
        $sql = "SELECT l.id, l.slug, l.title, l.title_variant_b, l.keyword, l.template, l.views, l.og_image, l.categories_json,
                       COALESCE(SUM(s.impressions),0) AS imps,
                       COALESCE(SUM(s.clicks),0) AS clks,
                       CASE WHEN COALESCE(SUM(s.impressions),0) >= 5 THEN (SUM(s.clicks)*1.0 / SUM(s.impressions))
                            ELSE 0 END AS ctr,
                       {$boostExpr} AS boost
                FROM landings l
                LEFT JOIN landing_ab_stats s ON s.landing_id = l.id
                WHERE l.active = 1
                GROUP BY l.id
                ORDER BY boost DESC, ctr DESC, l.views DESC, l.id DESC
                LIMIT ?";
        $params[] = $limit;
        $st = App::$db->prepare($sql);
        foreach ($params as $i => $v) {
            $st->bindValue($i + 1, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        return $st->fetchAll();
    }
}
