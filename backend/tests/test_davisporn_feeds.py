"""Backend tests for DAVISPORN real-feed integration (XNXX RapidAPI + XVideos CSV).
Runs against the public preview URL (nginx -> php-fpm on 127.0.0.1:9000).
"""
import os
import re
import time
import pytest
import requests
from requests.auth import HTTPBasicAuth

BASE_URL = os.environ.get("REACT_APP_BACKEND_URL", "https://media-nexus-111.preview.emergentagent.com").rstrip("/")
LOCAL_URL = "http://127.0.0.1:9000"
ADMIN_AUTH = HTTPBasicAuth("admin", "admin123")
CRON_TOKEN = "4cbe7eb9011e833001687088c1ecc98523615151403a55e8"


@pytest.fixture(scope="module")
def s():
    ses = requests.Session()
    ses.headers.update({"User-Agent": "davisporn-tester/1.0"})
    return ses


# ---- Public site ----
class TestPublic:
    def test_home_loads(self, s):
        r = s.get(BASE_URL + "/", timeout=20)
        assert r.status_code == 200, r.text[:300]
        # Age gate returns 200 with age markup; content should still include site header
        assert "davisporn" in r.text.lower() or "DAVISPORN" in r.text

    def test_home_has_real_thumbnails(self, s):
        # Age gate is client-side; server should still render video tiles
        r = s.get(BASE_URL + "/", timeout=20)
        assert r.status_code == 200
        # Real thumbnails come from cdn77 or others-cdn
        assert ("cdn77" in r.text) or ("others-cdn" in r.text) or ("xnxx-cdn" in r.text), \
            "No real CDN thumbnails found on home page"

    def test_videos_listing(self, s):
        r = s.get(BASE_URL + "/videos", timeout=20)
        assert r.status_code == 200

    def test_videos_filter_hd(self, s):
        r = s.get(BASE_URL + "/videos?quality=HD", timeout=20)
        assert r.status_code == 200

    def test_videos_filter_fullhd(self, s):
        r = s.get(BASE_URL + "/videos?quality=FullHD", timeout=20)
        assert r.status_code == 200
        # Should contain at least one video card
        assert "video" in r.text.lower()

    def test_category_teen(self, s):
        r = s.get(BASE_URL + "/category/teen", timeout=20)
        assert r.status_code == 200

    def test_search_milf(self, s):
        r = s.get(BASE_URL + "/search?q=milf", timeout=20)
        assert r.status_code == 200

    def test_sitemap(self, s):
        r = s.get(BASE_URL + "/sitemap.xml", timeout=20)
        assert r.status_code == 200
        assert "/video/" in r.text
        assert r.headers.get("content-type", "").startswith(("application/xml", "text/xml"))

    def test_robots(self, s):
        r = s.get(BASE_URL + "/robots.txt", timeout=20)
        assert r.status_code == 200


# ---- Video detail with embed iframes ----
class TestVideoDetail:
    def _pick_slug(self, source: str) -> str:
        import subprocess
        out = subprocess.check_output(
            ["mysql", "-udav", "-pdavpass", "davisporn", "-N", "-B", "-e",
             f"SELECT slug FROM videos WHERE source='{source}' LIMIT 1"]
        ).decode().strip()
        assert out, f"No videos for source={source}"
        return out

    def test_xnxx_video_embed(self, s):
        slug = self._pick_slug("xnxx_rapidapi")
        r = s.get(f"{BASE_URL}/video/{slug}", timeout=20)
        assert r.status_code == 200, f"got {r.status_code} for {slug}"
        m = re.search(r'<iframe[^>]+src="([^"]+)"', r.text)
        assert m, "No iframe on xnxx video detail"
        assert m.group(1).startswith("https://www.xnxx.com/embedframe/"), f"bad embed: {m.group(1)}"

    def test_xvideos_video_embed(self, s):
        slug = self._pick_slug("xvideos_csv")
        r = s.get(f"{BASE_URL}/video/{slug}", timeout=20)
        assert r.status_code == 200
        m = re.search(r'<iframe[^>]+src="([^"]+)"', r.text)
        assert m, "No iframe on xvideos video detail"
        assert m.group(1).startswith("https://www.xvideos.com/embedframe/"), f"bad embed: {m.group(1)}"


# ---- Admin ----
class TestAdmin:
    def test_admin_requires_auth(self, s):
        r = requests.get(BASE_URL + "/admin", timeout=20, allow_redirects=False)
        assert r.status_code in (401, 403)

    def test_admin_dashboard(self, s):
        r = s.get(BASE_URL + "/admin", auth=ADMIN_AUTH, timeout=20)
        assert r.status_code == 200
        for tid in [
            "cfg-feed-url-xvideos_csv", "cfg-api-key-xnxx", "cfg-host-xnxx",
            "cfg-queries-xnxx", "cfg-limit-xvideos_csv", "cfg-limit-xnxx_rapidapi",
            "purge-demo", "import-xvideos_csv",
        ]:
            assert f'data-testid="{tid}"' in r.text, f"missing data-testid={tid}"
        # both real sources should show enabled row + last_status starting with OK — inserted
        assert "xvideos_csv" in r.text and "xnxx_rapidapi" in r.text
        assert "OK — inserted" in r.text or "OK &mdash; inserted" in r.text

    def test_admin_save_xnxx_config(self, s):
        # Read existing config to preserve api_key/host across test save
        import subprocess, json as _json
        existing_cfg = subprocess.check_output(
            ["mysql", "-udav", "-pdavpass", "davisporn", "-N", "-B", "-e",
             "SELECT config FROM sources WHERE slug='xnxx_rapidapi'"]
        ).decode().strip()
        cfg = _json.loads(existing_cfg or "{}")
        api_key = cfg.get("api_key", "")
        host = cfg.get("host", "porn-xnxx-api.p.rapidapi.com")

        # save with test values (queries=milf,teen ; limit=50) — include api_key/host to avoid wipe
        r = s.post(
            BASE_URL + "/admin/source/config",
            auth=ADMIN_AUTH,
            data={
                "slug": "xnxx_rapidapi",
                "api_key": api_key, "host": host,
                "queries": "milf,teen", "import_limit": "50",
            },
            timeout=20, allow_redirects=True,
        )
        assert r.status_code == 200, r.text[:300]
        assert "Source config saved" in r.text, "flash not shown after save"

        # reload admin without query string, verify persistence via values
        r2 = s.get(BASE_URL + "/admin", auth=ADMIN_AUTH, timeout=20)
        assert r2.status_code == 200
        assert 'value="milf,teen"' in r2.text
        assert 'value="50"' in r2.text

        # RESTORE production values as per instructions
        r3 = s.post(
            BASE_URL + "/admin/source/config",
            auth=ADMIN_AUTH,
            data={
                "slug": "xnxx_rapidapi",
                "api_key": api_key, "host": host,
                "queries": "milf,teen,anal,amateur,lesbian,asian,latina,ebony,big tits,blowjob,hardcore,pov",
                "import_limit": "400",
            },
            timeout=20, allow_redirects=False,
        )
        assert r3.status_code in (302, 303)

    def test_admin_purge_demo_idempotent(self, s):
        r = s.post(BASE_URL + "/admin/source/purge-demo", auth=ADMIN_AUTH, timeout=20, allow_redirects=False)
        assert r.status_code in (302, 303), r.text[:400]
        r2 = s.get(BASE_URL + "/admin", auth=ADMIN_AUTH, timeout=20)
        assert r2.status_code == 200
        assert "Removed 0 demo videos" in r2.text or "Purged" in r2.text

    def test_admin_import_xvideos(self, s):
        r = s.post(
            BASE_URL + "/admin/source/import",
            auth=ADMIN_AUTH, data={"slug": "xvideos_csv"},
            timeout=20, allow_redirects=False,
        )
        assert r.status_code in (302, 303), r.text[:400]
        r2 = s.get(BASE_URL + "/admin", auth=ADMIN_AUTH, timeout=20)
        assert r2.status_code == 200
        assert "started in background" in r2.text.lower() or "background" in r2.text.lower()

        # poll up to 60s for status transition
        ok = False
        for _ in range(30):
            time.sleep(2)
            rr = s.get(BASE_URL + "/admin", auth=ADMIN_AUTH, timeout=20)
            if "OK — inserted" in rr.text or "OK &mdash; inserted" in rr.text:
                # ensure the xvideos row shows fresh status by finding testid area
                if 'data-testid="status-xvideos_csv"' in rr.text:
                    # Extract that cell
                    m = re.search(r'data-testid="status-xvideos_csv"[^>]*>([^<]+)<', rr.text)
                    if m and ("OK" in m.group(1) or "inserted" in m.group(1) or "updated" in m.group(1)):
                        ok = True
                        break
                else:
                    ok = True
                    break
        assert ok, "xvideos_csv import did not complete within 60s"


# ---- Cron ----
class TestCron:
    def test_cron_requires_bearer(self):
        r = requests.post(BASE_URL + "/api/cron/nightly-import", timeout=10)
        assert r.status_code == 401

    def test_cron_accepts_bearer(self):
        t0 = time.time()
        r = requests.post(
            BASE_URL + "/api/cron/nightly-import",
            headers={"Authorization": f"Bearer {CRON_TOKEN}"},
            timeout=10,
        )
        elapsed = time.time() - t0
        assert r.status_code == 200, r.text[:300]
        data = r.json()
        assert data.get("ok") is True
        assert data.get("event") == "nightly-import-accepted"
        assert elapsed < 5, f"cron ack too slow: {elapsed:.1f}s"
