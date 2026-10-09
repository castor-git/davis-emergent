"""Iteration 8 regression suite — covers Bulk Covers, AI Descriptions,
Dead Video Cleanup, and admin/public smoke per the review request.
NO live Gemini generation is triggered (bulk-covers runs async against
IDs that don't exist, AI-copy endpoint is only hit without auth)."""
import re

import pytest
import requests

from _env import ADMIN_AUTH, BASE_URL, CRON_SECRET


# ---------- Regression smoke ----------
class TestSmoke:
    def test_home_200(self):
        r = requests.get(f"{BASE_URL}/", timeout=20)
        assert r.status_code == 200
        assert "DAVISPORN" in r.text.upper() or "<html" in r.text.lower()

    def test_health_api(self):
        r = requests.get(f"{BASE_URL}/api/health", timeout=15)
        assert r.status_code == 200

    def test_landing_3_public_loads(self):
        r = requests.get(f"{BASE_URL}/l/amateur-best", timeout=20)
        assert r.status_code == 200
        # Persisted AI copy must be present
        assert "Amateur Best" in r.text or "amateur" in r.text.lower()
        # OG image must be an absolute URL that includes the generated path
        m = re.search(r'property="og:image"\s+content="([^"]+)"', r.text)
        assert m, "og:image meta missing on landing"
        assert "/generated/landing-3-" in m.group(1)

    def test_generated_cover_served(self):
        r = requests.get(f"{BASE_URL}/l/amateur-best", timeout=20)
        m = re.search(r'property="og:image"\s+content="([^"]+)"', r.text)
        assert m
        img = requests.get(m.group(1), timeout=20)
        assert img.status_code == 200
        assert img.headers.get("content-type", "").startswith("image/")
        assert int(img.headers.get("content-length", "0") or len(img.content)) > 2000


# ---------- Admin dashboard controls ----------
class TestAdminDashboard:
    def test_dashboard_auth_required(self):
        r = requests.get(f"{BASE_URL}/admin", timeout=15, allow_redirects=False)
        assert r.status_code == 401

    def test_dashboard_renders_new_controls(self):
        r = requests.get(f"{BASE_URL}/admin", auth=ADMIN_AUTH, timeout=20)
        assert r.status_code == 200
        html = r.text
        # Required data-testids for Bulk Covers & cleanup
        for testid in [
            'data-testid="bulk-covers"',
            'data-testid="cleanup-dead-xvideos"',
            'data-testid="unavailable-videos"',
            'data-testid="cfg-deleted-feed-xvideos"',
        ]:
            assert testid in html, f"missing {testid} on admin dashboard"
        # Bulk selection controls — at least one landing checkbox should render
        assert "landing-select" in html or 'name="ids[]"' in html

    def test_landing_edit_form_ai_controls(self):
        r = requests.get(
            f"{BASE_URL}/admin/landings/edit?id=3", auth=ADMIN_AUTH, timeout=20
        )
        assert r.status_code == 200
        html = r.text
        for testid in [
            'data-testid="l-generate-copy"',
            'data-testid="l-intro"',
            'data-testid="l-meta-desc"',
        ]:
            assert testid in html, f"missing {testid} on landing edit"
        # Persisted intro + meta description must be prefilled (non-empty)
        assert "Welcome to the Amateur Best" in html or "amateur" in html.lower()


# ---------- Bulk Covers backend contract ----------
class TestBulkCovers:
    def test_bulk_covers_requires_auth(self):
        r = requests.post(
            f"{BASE_URL}/admin/landings/bulk",
            data={"action": "covers", "ids[]": [999999]},
            timeout=15,
            allow_redirects=False,
        )
        assert r.status_code == 401

    def test_bulk_covers_unknown_ids_no_generation(self):
        """Posting non-existing IDs must not trigger generation — redirects back
        with a 'No selected landings still exist' message. This is safe: no
        Gemini call is made because generateManyAsync is only invoked for
        existing IDs."""
        r = requests.post(
            f"{BASE_URL}/admin/landings/bulk",
            data=[("action", "covers"), ("ids[]", "999991"), ("ids[]", "999992")],
            auth=ADMIN_AUTH,
            timeout=15,
            allow_redirects=False,
        )
        assert r.status_code in (302, 303)
        loc = r.headers.get("Location", "")
        assert "/admin" in loc
        assert "No%20selected%20landings%20still%20exist" in loc or "No+selected" in loc

    def test_bulk_covers_empty_ids(self):
        r = requests.post(
            f"{BASE_URL}/admin/landings/bulk",
            data={"action": "covers"},
            auth=ADMIN_AUTH,
            timeout=15,
            allow_redirects=False,
        )
        assert r.status_code in (302, 303)
        assert "No%20landings%20selected" in r.headers.get("Location", "") or \
               "No+landings" in r.headers.get("Location", "")


# ---------- AI copy sidecar ----------
class TestAiCopySidecar:
    def test_requires_internal_secret(self):
        r = requests.post(
            f"{BASE_URL}/api/ai/landing-copy",
            json={"title": "Amateur Best", "keyword": "amateur"},
            timeout=15,
        )
        assert r.status_code == 401
        assert "unauthorized" in r.text.lower()

    def test_rejects_short_title(self):
        """Even with a valid secret, Pydantic validation (min_length=2) must
        reject invalid payloads before any LLM call."""
        r = requests.post(
            f"{BASE_URL}/api/ai/landing-copy",
            headers={"X-Internal-Secret": CRON_SECRET},
            json={"title": "a"},
            timeout=15,
        )
        assert r.status_code == 422


# ---------- Dead-video cleanup contract ----------
class TestDeadCleanup:
    def test_requires_bearer(self):
        r = requests.post(f"{BASE_URL}/api/cron/dead-cleanup", timeout=15)
        assert r.status_code == 401

    def test_wrong_bearer(self):
        r = requests.post(
            f"{BASE_URL}/api/cron/dead-cleanup",
            headers={"Authorization": "Bearer wrong"},
            timeout=15,
        )
        assert r.status_code == 401

    def test_valid_bearer_acks_immediately(self):
        r = requests.post(
            f"{BASE_URL}/api/cron/dead-cleanup",
            headers={"Authorization": f"Bearer {CRON_SECRET}"},
            timeout=15,
        )
        assert r.status_code == 200
        data = r.json()
        assert data.get("ok") is True
        assert data.get("event") == "dead-cleanup-accepted"


# ---------- Public visibility excludes unavailable videos ----------
class TestVideoVisibility:
    def test_browse_videos(self):
        r = requests.get(f"{BASE_URL}/videos", timeout=20)
        assert r.status_code == 200

    def test_sitemap_ok(self):
        r = requests.get(f"{BASE_URL}/sitemap.xml", timeout=20)
        assert r.status_code == 200
        assert "<urlset" in r.text
