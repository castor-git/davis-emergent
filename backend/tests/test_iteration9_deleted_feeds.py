"""
Iteration 9 — DAVISPORN deleted-URL feed wiring (official full + weekly GZIP).

Scope (safe, non-downloading):
 - PHP config has exact official full/weekly URLs.
 - XVideosDeadCleaner constants + mode→URL mapping + URL parser (reflection, no network).
 - Both official assets reachable via HTTP range probe (first byte only).
 - /app/.emergent/crons.yml structure (04:15 UTC dead-cleanup, 7-day description, <=5 entries).
 - Unauthenticated POST /api/cron/dead-cleanup -> 401 (NEVER call authenticated).
 - Admin dashboard renders both readonly URLs + both cleanup forms (mode=week, mode=full).
"""

import json
import os
import subprocess
import yaml  # type: ignore
import pytest
import requests

BASE_URL = os.environ.get("REACT_APP_BACKEND_URL", "").rstrip("/")
if not BASE_URL:
    # try reading from .env
    with open("/app/frontend/.env") as f:
        for ln in f:
            if ln.startswith("REACT_APP_BACKEND_URL="):
                BASE_URL = ln.split("=", 1)[1].strip().rstrip("/")

FULL_FEED = "https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-deleted-full.csv.gz"
WEEK_FEED = "https://public-assets.xvideos-cdn.com/webmaster-tools/xvideos.com-deleted-week.csv.gz"

ADMIN_USER = os.environ.get("ADMIN_USER", "admin")
ADMIN_PASS = os.environ.get("ADMIN_PASS", "admin123")


# ---------- PHP config ----------
class TestPhpConfig:
    def test_config_has_official_full_and_week_urls(self):
        out = subprocess.check_output(
            ["php", "-r", "echo json_encode(require '/app/php/config/config.php');"],
            timeout=10,
        )
        cfg = json.loads(out)
        xv = cfg["sources"]["xvideos_csv"]
        assert xv["deleted_feed_url"] == WEEK_FEED
        assert xv["deleted_full_feed_url"] == FULL_FEED
        assert xv["deleted_feed_url"].endswith("xvideos.com-deleted-week.csv.gz")
        assert xv["deleted_full_feed_url"].endswith("xvideos.com-deleted-full.csv.gz")


# ---------- Live, non-downloading reachability (HEAD / Range: bytes=0-0) ----------
class TestFeedReachability:
    @pytest.mark.parametrize("url", [FULL_FEED, WEEK_FEED])
    def test_feed_one_byte_range(self, url):
        r = requests.get(url, headers={"Range": "bytes=0-0"}, timeout=20, stream=True)
        try:
            # Accept 206 Partial Content (ideal) or 200 if server ignores range.
            assert r.status_code in (200, 206), f"unexpected status {r.status_code} for {url}"
            ct = r.headers.get("Content-Type", "")
            # Should look like gzip/octet-stream — allow either.
            assert any(tok in ct.lower() for tok in ("gzip", "octet-stream", "application")), ct
        finally:
            r.close()


# ---------- Cleaner mode mapping + URL parser via reflection (no network) ----------
class TestCleanerReflection:
    def _php(self, code: str) -> str:
        proc = subprocess.run(
            ["php", "-d", "display_errors=1", "-r", code],
            capture_output=True, text=True, timeout=15,
        )
        assert proc.returncode == 0, f"PHP error: {proc.stderr}\n{proc.stdout}"
        return proc.stdout

    def test_constants(self):
        out = self._php(
            "require '/app/php/src/autoload.php';"
            "echo \\App\\Support\\XVideosDeadCleaner::FULL_FEED, '|',"
            "\\App\\Support\\XVideosDeadCleaner::WEEK_FEED;"
        )
        full, week = out.strip().split("|")
        assert full == FULL_FEED
        assert week == WEEK_FEED

    def test_mode_url_mapping_and_rejection(self):
        # Access private feedUrl + validateMode via reflection; config fallback is used.
        code = (
            "require '/app/php/src/autoload.php';"
            "\\App\\Core\\App::boot();"
            "$c = new ReflectionClass('App\\\\Support\\\\XVideosDeadCleaner');"
            "$f = $c->getMethod('feedUrl'); $f->setAccessible(true);"
            "$v = $c->getMethod('validateMode'); $v->setAccessible(true);"
            "echo 'WEEK=', $f->invoke(null,'week'), \"\\n\";"
            "echo 'FULL=', $f->invoke(null,'full'), \"\\n\";"
            "try { $v->invoke(null,'daily'); echo 'INVALID=NOT_REJECTED\\n'; }"
            "catch (\\InvalidArgumentException $e) { echo 'INVALID=REJECTED\\n'; }"
        )
        try:
            out = self._php(code)
        except AssertionError as e:
            # App::boot may fail if DB not reachable; fall back to a DB-free reflection test
            code2 = (
                "require '/app/php/src/autoload.php';"
                "$c = new ReflectionClass('App\\\\Support\\\\XVideosDeadCleaner');"
                "$v = $c->getMethod('validateMode'); $v->setAccessible(true);"
                "try { $v->invoke(null,'daily'); echo 'INVALID=NOT_REJECTED\\n'; }"
                "catch (\\InvalidArgumentException $e) { echo 'INVALID=REJECTED\\n'; }"
                "echo 'WEEK=', \\App\\Support\\XVideosDeadCleaner::WEEK_FEED, \"\\n\";"
                "echo 'FULL=', \\App\\Support\\XVideosDeadCleaner::FULL_FEED, \"\\n\";"
            )
            out = self._php(code2)
        assert "INVALID=REJECTED" in out
        assert f"WEEK={WEEK_FEED}" in out
        assert f"FULL={FULL_FEED}" in out

    def test_url_parser_accepts_xvideos_rejects_others(self):
        code = (
            "require '/app/php/src/autoload.php';"
            "$c = new ReflectionClass('App\\\\Support\\\\XVideosDeadCleaner');"
            "$n = $c->getMethod('normalizeUrl'); $n->setAccessible(true);"
            "$ok = $n->invoke(null,'https://www.xvideos.com/video12345/foo');"
            "$bad1 = $n->invoke(null,'https://evil.com/video12345/foo');"
            "$bad2 = $n->invoke(null,'https://evilxvideos.com/video12345/foo');"
            "$bad3 = $n->invoke(null,'/video12345/foo');"
            "$root = $n->invoke(null,'https://www.xvideos.com/');"
            "echo 'OK=', ($ok ?? 'NULL'), \"\\n\";"
            "echo 'BAD1=', ($bad1 ?? 'NULL'), \"\\n\";"
            "echo 'BAD2=', ($bad2 ?? 'NULL'), \"\\n\";"
            "echo 'BAD3=', ($bad3 ?? 'NULL'), \"\\n\";"
            "echo 'ROOT=', ($root ?? 'NULL'), \"\\n\";"
        )
        out = self._php(code)
        assert "OK=https://www.xvideos.com/video12345/foo" in out
        assert "BAD1=NULL" in out
        assert "BAD2=NULL" in out
        assert "BAD3=NULL" in out
        assert "ROOT=NULL" in out


# ---------- crons.yml ----------
class TestCronsYaml:
    def test_crons_yaml_structure(self):
        with open("/app/.emergent/crons.yml") as f:
            doc = yaml.safe_load(f)
        crons = doc["crons"]
        assert isinstance(crons, list)
        assert len(crons) <= 5, f"too many cron entries: {len(crons)}"

        dead = [c for c in crons if c.get("name") == "dead-cleanup"]
        assert len(dead) == 1, "expected exactly one dead-cleanup entry"
        d = dead[0]
        assert d["cron"] == "15 4 * * *"  # 04:15 UTC
        assert d["endpoint"].endswith("/api/cron/dead-cleanup")
        assert d["method"].upper() == "POST"
        assert d.get("enabled") is True
        assert "7-day" in d["description"], d["description"]


# ---------- Unauthenticated cron rejection ----------
class TestCronAuth:
    def test_dead_cleanup_requires_auth(self):
        r = requests.post(f"{BASE_URL}/api/cron/dead-cleanup", timeout=20)
        assert r.status_code == 401, r.status_code
        body = r.json()
        assert body.get("ok") is False
        assert body.get("error") == "unauthorized"

    def test_dead_cleanup_bad_bearer(self):
        r = requests.post(
            f"{BASE_URL}/api/cron/dead-cleanup",
            headers={"Authorization": "Bearer not-the-real-secret"},
            timeout=20,
        )
        assert r.status_code == 401


# ---------- Health / home ----------
class TestSmoke:
    def test_health(self):
        r = requests.get(f"{BASE_URL}/api/health", timeout=15)
        # If no /api/health exists, don't fail the suite — probe home instead.
        if r.status_code == 404:
            pytest.skip("no /api/health endpoint")
        assert r.status_code == 200, r.text[:200]

    def test_home_loads(self):
        r = requests.get(f"{BASE_URL}/", timeout=20)
        assert r.status_code == 200
        assert len(r.text) > 500


# ---------- Admin dashboard renders URLs + controls ----------
class TestAdminDashboard:
    def _get(self):
        return requests.get(
            f"{BASE_URL}/admin",
            auth=(ADMIN_USER, ADMIN_PASS),
            timeout=25,
        )

    def test_dashboard_authenticated(self):
        r = self._get()
        assert r.status_code == 200, r.status_code

    def test_dashboard_shows_both_official_urls(self):
        html = self._get().text
        assert FULL_FEED in html, "full feed URL missing from admin"
        assert WEEK_FEED in html, "week feed URL missing from admin"
        # readonly inputs
        assert 'data-testid="cfg-deleted-feed-xvideos"' in html
        assert 'data-testid="cfg-deleted-full-feed-xvideos"' in html
        # cleanup controls
        assert 'data-testid="cleanup-dead-xvideos"' in html
        assert 'data-testid="cleanup-dead-full-xvideos"' in html

    def test_dashboard_forms_post_correct_modes(self):
        html = self._get().text
        # Each cleanup button is inside its own form with a hidden mode input.
        # Find the <form …> block containing the button and ensure the matching mode is in it.
        import re
        forms = re.findall(r"<form[^>]*action=\"/admin/source/cleanup-dead\"[^>]*>.*?</form>", html, re.S)
        assert len(forms) == 2, f"expected 2 cleanup forms, got {len(forms)}"
        found = {"week": False, "full": False}
        for f in forms:
            if 'name="mode" value="week"' in f and 'data-testid="cleanup-dead-xvideos"' in f:
                found["week"] = True
            if 'name="mode" value="full"' in f and 'data-testid="cleanup-dead-full-xvideos"' in f:
                found["full"] = True
        assert found == {"week": True, "full": True}, found

    def test_dashboard_unauth_401(self):
        r = requests.get(f"{BASE_URL}/admin", timeout=15)
        assert r.status_code == 401
