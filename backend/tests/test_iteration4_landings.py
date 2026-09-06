"""Iteration 4 - Landing Page Builder tests.
Covers admin form prefill, save/create/edit/delete, public /l/{slug}, union semantics,
active flag, pagination and regression of existing admin sections.
"""
import re
import requests
import pytest
from _env import BASE_URL, ADMIN_AUTH as AUTH

# ---------- helpers ----------
def _get(path, **kw):
    return requests.get(BASE_URL + path, timeout=20, **kw)

def _post(path, data=None, **kw):
    return requests.post(BASE_URL + path, data=data or {}, timeout=20, allow_redirects=False, **kw)

def _admin_html():
    r = requests.get(BASE_URL + "/admin", auth=AUTH, timeout=20)
    assert r.status_code == 200
    return r.text

def _landing_rows(html, slug):
    """Admin table rows that link to exactly /l/{slug} (not /l/{slug}-2 etc.)."""
    return [r for r in re.findall(r"<tr[^>]*>(.*?)</tr>", html, re.S) if re.search(rf'/l/{re.escape(slug)}["<\s?]', r)]

def _find_landing_id_by_slug(html, slug):
    for row in _landing_rows(html, slug):
        m2 = re.search(r'/admin/landings/edit\?id=(\d+)', row)
        if m2:
            return int(m2.group(1))
    return None

def _delete_landing_by_slug(slug):
    lid = _find_landing_id_by_slug(_admin_html(), slug)
    if lid:
        _post("/admin/landings/delete", {"id": lid}, auth=AUTH)

def _recreate_landing(slug, **fields):
    """Delete any stale landing with this slug, create it fresh and return the save response."""
    _delete_landing_by_slug(slug)
    return _post("/admin/landings/save", {"slug": slug, "active": "1", **fields}, auth=AUTH)

def _landing_video_count(slug):
    r = _get(f"/l/{slug}")
    assert r.status_code == 200, f"/l/{slug} -> {r.status_code}"
    m = re.search(r"([\d,]+)\s+videos</span>", r.text)
    assert m, f"video-count badge missing on /l/{slug}"
    return int(m.group(1).replace(",", ""))


# ---------- health / admin base ----------
def test_health_ok():
    r = _get("/api/health")
    assert r.status_code == 200
    assert r.json().get("ok") == True  # noqa: E712 — strict boolean

def test_admin_requires_basic_auth():
    r = _get("/admin")
    assert r.status_code == 401

def test_admin_dashboard_has_landings_section():
    html = _admin_html()
    assert 'data-testid="landings-table"' in html
    assert 'data-testid="new-landing"' in html
    # regression: other sections still present
    assert 'data-testid="sources-table"' in html
    assert 'data-testid="ads-table"' in html
    assert 'data-testid="search-stats"' in html
    assert 'data-testid="top-searches"' in html


# ---------- landing form prefill ----------
def test_landing_new_prefill_from_keyword():
    r = requests.get(BASE_URL + "/admin/landings/new?keyword=milf", auth=AUTH, timeout=20)
    assert r.status_code == 200
    h = r.text
    assert 'data-testid="landing-form"' in h
    assert re.search(r'name="keyword" value="milf"', h)
    assert re.search(r'name="title" value="Milf videos', h)
    assert re.search(r'name="slug" value="milf"', h)
    # Category checkbox for milf auto-selected (if a milf category exists in DB)
    m = re.search(r'<input type="checkbox" name="categories\[\]" value="milf"[^>]*checked', h)
    assert m, "milf category checkbox should be auto-checked when keyword=milf"


# ---------- create / verify public / edit / delete ----------
@pytest.fixture(scope="module")
def created_landing():
    # Use a unique slug to avoid collision from prior runs
    slug = "test-milf-hot"
    # Clean up any leftover
    html = _admin_html()
    lid = _find_landing_id_by_slug(html, slug)
    if lid:
        _post("/admin/landings/delete", {"id": lid}, auth=AUTH)
    r = _post(
        "/admin/landings/save",
        {
            "title": "MILF Hot",
            "slug": slug,
            "keyword": "milf",
            "intro": "",
            "categories[]": "milf",
            "active": "1",
        },
        auth=AUTH,
    )
    assert r.status_code in (301, 302)
    loc = r.headers.get("Location", "")
    assert "/admin" in loc
    from urllib.parse import unquote_plus
    assert f"Landing saved: /l/{slug}" in unquote_plus(loc)
    # find id from admin list
    html = _admin_html()
    lid = _find_landing_id_by_slug(html, slug)
    assert lid, "created landing must appear in landings-table"
    yield {"id": lid, "slug": slug}
    # teardown
    _post("/admin/landings/delete", {"id": lid}, auth=AUTH)

def test_public_landing_renders(created_landing):
    r = _get(f"/l/{created_landing['slug']}")
    assert r.status_code == 200
    h = r.text
    assert 'data-testid="landing-hero"' in h
    assert "CURATED" in h
    assert "Search intent" in h
    assert 'data-testid="landing-grid"' in h
    assert 'data-testid="video-card"' in h  # at least one video

def test_edit_prefill_then_update_no_duplicate(created_landing):
    lid = created_landing["id"]
    r = requests.get(BASE_URL + f"/admin/landings/edit?id={lid}", auth=AUTH, timeout=20)
    assert r.status_code == 200
    h = r.text
    assert re.search(rf'name="id" value="{lid}"', h)
    assert re.search(r'name="title" value="MILF Hot"', h)
    # Update title, keep same slug
    r2 = _post(
        "/admin/landings/save",
        {
            "id": str(lid),
            "title": "MILF Hot Updated",
            "slug": created_landing["slug"],
            "keyword": "milf",
            "intro": "updated",
            "categories[]": "milf",
            "active": "1",
        },
        auth=AUTH,
    )
    assert r2.status_code in (301, 302)
    # verify no duplicate row for that slug
    rows = _landing_rows(_admin_html(), created_landing["slug"])
    assert len(rows) == 1
    assert "MILF Hot Updated" in rows[0]

def test_active_zero_returns_404(created_landing):
    # toggle active=0
    r = _post(
        "/admin/landings/save",
        {
            "id": str(created_landing["id"]),
            "title": "MILF Hot Updated",
            "slug": created_landing["slug"],
            "keyword": "milf",
            "categories[]": "milf",
            # no 'active' => 0
        },
        auth=AUTH,
    )
    assert r.status_code in (301, 302)
    r2 = _get(f"/l/{created_landing['slug']}")
    assert r2.status_code == 404
    # re-enable for subsequent tests
    _post(
        "/admin/landings/save",
        {
            "id": str(created_landing["id"]),
            "title": "MILF Hot Updated",
            "slug": created_landing["slug"],
            "keyword": "milf",
            "categories[]": "milf",
            "active": "1",
        },
        auth=AUTH,
    )


# ---------- union semantics ----------
UNION_SLUGS = {
    "union": ("test-union-alpha", {"title": "Alpha", "keyword": "alpha", "categories[]": "milf"}),
    "keyword": ("test-only-alpha", {"title": "OnlyAlpha", "keyword": "alpha"}),
    "category": ("test-only-milfcat", {"title": "OnlyMilf", "categories[]": "milf"}),
}


def _create_union_fixtures():
    counts = {}
    for key, (slug, fields) in UNION_SLUGS.items():
        r = _recreate_landing(slug, **fields)
        assert r.status_code in (301, 302), f"save {slug} -> {r.status_code}"
        counts[key] = _landing_video_count(slug)
    return counts


def _cleanup_union_fixtures():
    for slug, _ in UNION_SLUGS.values():
        _delete_landing_by_slug(slug)


def test_union_semantics_keyword_or_category():
    """Landing with keyword=alpha + categories=[milf] must return union of both sets."""
    try:
        c = _create_union_fixtures()
        assert c["union"] >= max(c["keyword"], c["category"]), \
            f"union {c['union']} < max({c['keyword']},{c['category']}) — AND semantics leaked?"
        assert c["union"] <= c["keyword"] + c["category"]
    finally:
        _cleanup_union_fixtures()


# ---------- pagination ----------
def test_pagination_when_total_gt_24():
    slug = "test-milf-page"
    html = _admin_html()
    lid = _find_landing_id_by_slug(html, slug)
    if lid:
        _post("/admin/landings/delete", {"id": lid}, auth=AUTH)
    _post("/admin/landings/save",
          {"title": "MilfPage", "slug": slug, "categories[]": "milf", "active": "1"},
          auth=AUTH)
    try:
        r = _get(f"/l/{slug}")
        m = re.search(r"([\d,]+)\s+videos</span>", r.text)
        total = int(m.group(1).replace(",", ""))
        if total <= 24:
            pytest.skip(f"milf category only has {total} videos — pagination not triggered")
        assert 'class="pager"' in r.text or 'pager' in r.text
        r2 = _get(f"/l/{slug}?page=2")
        assert r2.status_code == 200
        assert 'data-testid="landing-grid"' in r2.text
    finally:
        hh = _admin_html()
        i = _find_landing_id_by_slug(hh, slug)
        if i:
            _post("/admin/landings/delete", {"id": i}, auth=AUTH)


# ---------- delete removes public route ----------
def test_delete_landing_returns_404():
    slug = "test-todelete"
    r = _recreate_landing(slug, title="ToDel", **{"categories[]": "milf"})
    assert r.status_code in (301, 302)
    lid = _find_landing_id_by_slug(_admin_html(), slug)
    assert lid
    r = _post("/admin/landings/delete", {"id": lid}, auth=AUTH)
    assert r.status_code in (301, 302)
    r2 = _get(f"/l/{slug}")
    assert r2.status_code == 404


# ---------- + Landing button on GAP rows ----------
def test_kw_landing_button_present_when_searches_exist():
    html = _admin_html()
    # There may be no top searches. If any row exists, the +Landing link must be there.
    if 'data-testid="kw-0"' in html:
        assert 'data-testid="kw-landing-0"' in html
        assert '/admin/landings/new?keyword=' in html


# ---------- regression: cron endpoint auth ----------
def test_cron_unauth_401():
    r = requests.post(BASE_URL + "/api/cron/nightly-import", timeout=15)
    assert r.status_code == 401
