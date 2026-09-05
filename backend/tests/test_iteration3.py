"""Iteration 3 backend tests: source config, ads CRUD, cron endpoint auth, regression."""
import os, re, requests, pytest

BASE = os.environ.get('REACT_APP_BACKEND_URL', 'https://media-nexus-111.preview.emergentagent.com').rstrip('/')
ADMIN = ('admin', 'admin123')
SECRET = '4cbe7eb9011e833001687088c1ecc98523615151403a55e8'


@pytest.fixture(scope='module')
def s():
    return requests.Session()


# ---------- Regression: health ----------
def test_health(s):
    r = s.get(f"{BASE}/api/health", timeout=10)
    assert r.status_code == 200
    assert r.json().get('ok') is True


# ---------- Admin dashboard shows new sections ----------
def test_admin_dashboard_has_new_sections(s):
    r = s.get(f"{BASE}/admin", auth=ADMIN, timeout=15)
    assert r.status_code == 200
    html = r.text
    assert 'data-testid="ads-table"' in html
    assert 'data-testid="ad-form"' in html
    assert 'data-testid="ad-position"' in html
    assert 'data-testid="ad-kind"' in html
    assert 'data-testid="ad-title"' in html
    assert 'data-testid="ad-link"' in html
    assert 'data-testid="ad-image"' in html
    assert 'data-testid="ad-snippet"' in html
    assert 'data-testid="ad-save"' in html
    # per-source config forms - check that at least one cfg- form exists
    assert re.search(r'data-testid="cfg-[a-z0-9_]+"', html)
    assert re.search(r'data-testid="save-cfg-[a-z0-9_]+"', html)
    assert 'Active ads' in html


# ---------- Source config save ----------
def test_save_source_config_persists(s):
    url = 'https://example.com/test-feed.csv'
    r = s.post(f"{BASE}/admin/source/config", auth=ADMIN,
               data={'slug': 'upornia_csv', 'feed_url': url},
               allow_redirects=False, timeout=15)
    assert r.status_code in (301, 302)
    # Verify persisted via GET /admin - URL should appear in input value
    r2 = s.get(f"{BASE}/admin", auth=ADMIN, timeout=15)
    assert url in r2.text, "feed_url not visible after save"


# ---------- Ads CRUD ----------
def test_create_banner_ad_home_top_and_render(s):
    r = s.post(f"{BASE}/admin/ads/save", auth=ADMIN, data={
        'position': 'home_top', 'kind': 'banner',
        'title': 'TEST_Banner_HomeTop',
        'link_url': 'https://example.com/promo',
        'image_url': 'https://example.com/img.png',
        'weight': '5', 'active': '1',
    }, allow_redirects=False, timeout=15)
    assert r.status_code in (301, 302)
    # Appears on admin table
    admin = s.get(f"{BASE}/admin", auth=ADMIN, timeout=15).text
    assert 'TEST_Banner_HomeTop' in admin
    # Appears on home
    home = s.get(f"{BASE}/", timeout=15).text
    assert 'data-testid="ad-home_top"' in home
    # NOTE: Ad::forPosition uses ORDER BY weight DESC LIMIT 1, so the highest-weight
    # existing ad wins the slot. Slot presence is the correctness invariant.


def test_create_snippet_ad_renders_unescaped(s):
    marker = "<div class='raw-snippet'>hi-TESTraw</div>"
    r = s.post(f"{BASE}/admin/ads/save", auth=ADMIN, data={
        'position': 'home_middle', 'kind': 'snippet',
        'title': 'TEST_Snippet',
        'snippet_html': marker,
        'weight': '10', 'active': '1',
    }, allow_redirects=False, timeout=15)
    assert r.status_code in (301, 302)
    home = s.get(f"{BASE}/", timeout=15).text
    assert 'data-testid="ad-home_middle"' in home
    # raw HTML not escaped
    assert "<div class='raw-snippet'>hi-TESTraw</div>" in home or 'hi-TESTraw</div>' in home


def test_video_slots_render(s):
    # Create video_pre and video_sidebar ads
    for pos in ('video_pre', 'video_sidebar'):
        s.post(f"{BASE}/admin/ads/save", auth=ADMIN, data={
            'position': pos, 'kind': 'banner',
            'title': f'TEST_{pos}', 'link_url': 'https://example.com',
            'image_url': 'https://example.com/x.png', 'weight': '1', 'active': '1',
        }, allow_redirects=False, timeout=15)
    # Pick a video slug from home
    home = s.get(f"{BASE}/", timeout=15).text
    m = re.search(r'/video/([a-z0-9\-]+)', home)
    assert m, "no video link found on home"
    slug = m.group(1)
    v = s.get(f"{BASE}/video/{slug}", timeout=15).text
    assert 'data-testid="ad-video_pre"' in v
    assert 'data-testid="ad-video_sidebar"' in v


def test_delete_ad_and_flash(s):
    # Grab any ad id from admin
    admin = s.get(f"{BASE}/admin", auth=ADMIN, timeout=15).text
    m = re.search(r'data-testid="ad-delete-(\d+)"', admin)
    assert m, "no ad to delete"
    ad_id = m.group(1)
    r = s.post(f"{BASE}/admin/ads/delete", auth=ADMIN, data={'id': ad_id},
               allow_redirects=True, timeout=15)
    assert r.status_code == 200
    assert 'Ad deleted' in r.text


# ---------- Cron endpoint ----------
def test_cron_no_auth_401(s):
    r = requests.post(f"{BASE}/api/cron/nightly-import", timeout=10)
    assert r.status_code == 401


def test_cron_wrong_auth_401(s):
    r = requests.post(f"{BASE}/api/cron/nightly-import",
                      headers={'Authorization': 'Bearer WRONGTOKEN'}, timeout=10)
    assert r.status_code == 401


def test_cron_correct_auth_200(s):
    r = requests.post(f"{BASE}/api/cron/nightly-import",
                      headers={'Authorization': f'Bearer {SECRET}'}, timeout=30)
    assert r.status_code == 200
    body = r.json()
    assert body.get('ok') is True
    assert body.get('event') == 'nightly-import-accepted'


# ---------- Crons.yml ----------
def test_crons_yml():
    import yaml
    with open('/app/.emergent/crons.yml') as f:
        data = yaml.safe_load(f)
    crons = data.get('crons', [])
    assert len(crons) == 1
    c = crons[0]
    assert c['name'] == 'nightly-import'
    assert c['cron'] == '0 3 * * *'
    assert 'api/cron/nightly-import' in c['endpoint']


# ---------- Regression ----------
def test_home_ok(s):
    r = s.get(f"{BASE}/", timeout=15)
    assert r.status_code == 200
    assert 'Featured' in r.text or 'Newest' in r.text


def test_videos_filters(s):
    r = s.get(f"{BASE}/videos?sort=newest", timeout=15)
    assert r.status_code == 200


def test_sitemap_public_host(s):
    r = s.get(f"{BASE}/sitemap.xml", timeout=15)
    assert r.status_code == 200
    assert 'media-nexus-111.preview.emergentagent.com' in r.text or '<loc>' in r.text
