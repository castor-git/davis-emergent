<?php
namespace App\Support;

use App\Core\App;

/**
 * Builds the weekly digest HTML for the site owner. Never takes any caller
 * input — recipients + content come from DB and env only (G4).
 */
class DigestBuilder {
    public static function baseUrl(): string {
        $host = getenv('PUBLIC_HOST') ?: ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = getenv('PUBLIC_SCHEME') ?: ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https');
        return $scheme . '://' . $host;
    }

    public static function build(): array {
        $db = App::$db;
        $since = date('Y-m-d H:i:s', time() - 7*86400);

        $draftLandings = $db->prepare("SELECT id, slug, title, keyword FROM landings WHERE suggested=1 AND active=0 AND created_at >= ? ORDER BY id DESC LIMIT 10");
        $draftLandings->execute([$since]); $drafts = $draftLandings->fetchAll();

        $topSearches = $db->prepare("SELECT q, count, results_last FROM search_queries WHERE last_seen >= ? ORDER BY count DESC LIMIT 10");
        $topSearches->execute([$since]); $searches = $topSearches->fetchAll();

        $bestLandings = $db->query("
            SELECT l.slug, l.title, l.views,
                   COALESCE(SUM(s.impressions),0) imps, COALESCE(SUM(s.clicks),0) clks,
                   CASE WHEN COALESCE(SUM(s.impressions),0)>=5 THEN ROUND(SUM(s.clicks)*100.0/SUM(s.impressions),1) ELSE 0 END ctr
            FROM landings l LEFT JOIN landing_ab_stats s ON s.landing_id=l.id
            WHERE l.active=1 GROUP BY l.id
            ORDER BY ctr DESC, l.views DESC LIMIT 8")->fetchAll();

        $searches7d = 0;
        try {
            $s = $db->prepare("SELECT COALESCE(SUM(count),0) FROM search_queries WHERE last_seen >= ?");
            $s->execute([$since]);
            $searches7d = (int)$s->fetchColumn();
        } catch (\Throwable $e) {}

        $stats = [
            'total_videos' => (int)$db->query("SELECT COUNT(*) FROM videos")->fetchColumn(),
            'active_landings' => (int)$db->query("SELECT COUNT(*) FROM landings WHERE active=1")->fetchColumn(),
            'searches_7d' => $searches7d,
            'zero_gaps' => (int)$db->query("SELECT COUNT(*) FROM search_queries WHERE results_last=0")->fetchColumn(),
        ];

        return [
            'subject' => 'DAVISPORN weekly digest — ' . date('M j, Y'),
            'html' => self::renderHtml($stats, $drafts, $searches, $bestLandings),
            'text_summary' => "Drafts: " . count($drafts) . " · Top searches: " . count($searches) . " · Best landings: " . count($bestLandings),
        ];
    }

    private static function renderHtml(array $stats, array $drafts, array $searches, array $best): string {
        $base = self::baseUrl();
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $row = fn($cells) => '<tr>' . implode('', array_map(fn($c)=>'<td style="padding:8px 12px;border-bottom:1px solid #e5e7eb;font-family:Arial,sans-serif;font-size:14px">'.$c.'</td>', $cells)) . '</tr>';

        $draftsTable = $drafts
            ? '<table role="presentation" width="100%" style="border-collapse:collapse"><tr><th style="text-align:left;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">Slug</th><th style="text-align:left;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">Keyword</th></tr>' .
              implode('', array_map(fn($d)=>$row([
                  '<a href="'.$e($base).'/admin/landings/edit?id='.(int)$d['id'].'" style="color:#e10600;text-decoration:none">'.$e($d['slug']).'</a>',
                  $e($d['keyword'] ?: '—'),
              ]), $drafts)) . '</table>'
            : '<p style="color:#6b7280;font-family:Arial,sans-serif;font-size:14px">No new drafts this week.</p>';

        $searchTable = $searches
            ? '<table role="presentation" width="100%" style="border-collapse:collapse"><tr><th style="text-align:left;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">Keyword</th><th style="text-align:right;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">Searches</th><th style="text-align:right;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">Results</th></tr>' .
              implode('', array_map(fn($s)=>$row([$e($s['q']), (int)$s['count'], (int)$s['results_last']]), $searches)) . '</table>'
            : '<p style="color:#6b7280;font-family:Arial,sans-serif;font-size:14px">No searches yet this week.</p>';

        $bestTable = $best
            ? '<table role="presentation" width="100%" style="border-collapse:collapse"><tr><th style="text-align:left;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">Landing</th><th style="text-align:right;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">CTR</th><th style="text-align:right;padding:8px 12px;background:#f9fafb;font-family:Arial,sans-serif;font-size:12px">Views</th></tr>' .
              implode('', array_map(fn($l)=>$row([
                  '<a href="'.$e($base).'/l/'.$e($l['slug']).'" style="color:#e10600;text-decoration:none">'.$e($l['title']).'</a>',
                  number_format((float)$l['ctr'],1).'%',
                  number_format((int)$l['views']),
              ]), $best)) . '</table>'
            : '<p style="color:#6b7280;font-family:Arial,sans-serif;font-size:14px">Publish landings to see performance here.</p>';

        return '<table role="presentation" width="100%" style="background:#f4f5f7;padding:24px 0"><tr><td align="center">
<table role="presentation" width="640" style="background:#ffffff;border-radius:10px;overflow:hidden">
<tr><td style="padding:24px 28px;background:#0b0c0f;color:#fff;font-family:Arial,sans-serif">
<div style="font-size:22px;font-weight:800;letter-spacing:-.02em">DAVIS<span style="color:#e10600">PORN</span> <span style="color:#8f96a3;font-weight:400;font-size:14px;margin-left:6px">weekly digest</span></div>
<div style="color:#8f96a3;font-size:13px;margin-top:6px">'.date('l, F j, Y').'</div>
</td></tr>
<tr><td style="padding:20px 28px;font-family:Arial,sans-serif;color:#1f2937">
  <table role="presentation" width="100%"><tr>
    <td style="width:25%;font-size:12px;color:#6b7280;text-transform:uppercase">Videos<br><strong style="color:#111;font-size:22px">'.$stats['total_videos'].'</strong></td>
    <td style="width:25%;font-size:12px;color:#6b7280;text-transform:uppercase">Active landings<br><strong style="color:#111;font-size:22px">'.$stats['active_landings'].'</strong></td>
    <td style="width:25%;font-size:12px;color:#6b7280;text-transform:uppercase">Searches 7d<br><strong style="color:#111;font-size:22px">'.$stats['searches_7d'].'</strong></td>
    <td style="width:25%;font-size:12px;color:#6b7280;text-transform:uppercase">Zero-result<br><strong style="color:#e10600;font-size:22px">'.$stats['zero_gaps'].'</strong></td>
  </tr></table>
</td></tr>
<tr><td style="padding:20px 28px;font-family:Arial,sans-serif"><h3 style="margin:0 0 10px;font-size:16px">New draft landings ('.count($drafts).')</h3>'.$draftsTable.'</td></tr>
<tr><td style="padding:20px 28px;font-family:Arial,sans-serif"><h3 style="margin:0 0 10px;font-size:16px">Top searches this week</h3>'.$searchTable.'</td></tr>
<tr><td style="padding:20px 28px;font-family:Arial,sans-serif"><h3 style="margin:0 0 10px;font-size:16px">Best-performing landings</h3>'.$bestTable.'</td></tr>
<tr><td style="padding:20px 28px;background:#f9fafb;font-family:Arial,sans-serif;text-align:center">
<a href="'.$e($base).'/admin" style="display:inline-block;background:#e10600;color:#fff;text-decoration:none;padding:10px 22px;border-radius:6px;font-weight:700;font-size:14px">Open admin dashboard</a>
</td></tr>
<tr><td style="padding:16px 28px;font-family:Arial,sans-serif;font-size:11px;color:#9ca3af;text-align:center">
Sent by DAVISPORN. We never ask for your password or card details by email.
</td></tr>
</table></td></tr></table>';
    }
}
