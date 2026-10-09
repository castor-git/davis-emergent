"""Tests for AI cover generation feature (Gemini Nano Banana via sidecar)."""
import os
import re
import subprocess
import time

import pytest
import requests

from _env import ADMIN_AUTH, BASE_URL, CRON_SECRET, PUBLIC_HOST


# -------------------- AI sidecar --------------------
class TestAISidecar:
    def test_no_secret_returns_401(self):
        r = requests.post(f"{BASE_URL}/api/ai/image",
                          json={"prompt": "Abstract dark poster TEST xx"}, timeout=15)
        assert r.status_code == 401
        assert r.json().get("detail") == "unauthorized"

    def test_wrong_secret_returns_401(self):
        r = requests.post(f"{BASE_URL}/api/ai/image",
                          headers={"X-Internal-Secret": "nope"},
                          json={"prompt": "Abstract dark poster TEST xx"}, timeout=15)
        assert r.status_code == 401

    def test_short_prompt_returns_422(self):
        r = requests.post(f"{BASE_URL}/api/ai/image",
                          headers={"X-Internal-Secret": CRON_SECRET},
                          json={"prompt": "short"}, timeout=15)
        assert r.status_code == 422

    def test_valid_generates_image(self):
        prompt = "Abstract dark poster with glowing red geometric shapes and the word TEST"
        r = requests.post(f"{BASE_URL}/api/ai/image",
                          headers={"X-Internal-Secret": CRON_SECRET},
                          json={"prompt": prompt}, timeout=120)
        assert r.status_code == 200, r.text[:300]
        data = r.json()
        assert data["ok"] is True
        assert data["model"] == "gemini-3.1-flash-image-preview"
        assert data["mime_type"].startswith("image/")
        b64 = data["data"]
        # Never print full base64
        print(f"image data len={len(b64)} head={b64[:10]}")
        assert len(b64) > 1000


# -------------------- Admin landing form --------------------
class TestAdminLandingForm:
    def test_existing_landing_shows_button_and_preview(self):
        r = requests.get(f"{BASE_URL}/admin/landings/edit?id=1",
                         auth=ADMIN_AUTH, timeout=15)
        assert r.status_code == 200
        html = r.text
        assert 'data-testid="l-generate-cover"' in html
        assert "Generate AI cover (Gemini)" in html
        assert "cover-form" in html
        assert 'data-testid="l-og-preview"' in html
        assert re.search(r'src="/generated/landing-1-[^"]+"', html)

    def test_new_landing_hides_button(self):
        r = requests.get(f"{BASE_URL}/admin/landings/edit",
                         auth=ADMIN_AUTH, timeout=15)
        assert r.status_code == 200
        html = r.text
        assert 'data-testid="l-generate-cover"' not in html
        assert "Save the landing first to unlock AI cover generation." in html


# -------------------- Cover generation via admin --------------------
class TestGenerateCoverAdmin:
    def test_generate_landing_2(self):
        r = requests.post(f"{BASE_URL}/admin/landings/cover",
                          auth=ADMIN_AUTH, data={"id": "2"},
                          allow_redirects=False, timeout=120)
        assert r.status_code == 302, r.text[:300]
        loc = r.headers.get("Location", "")
        assert "/admin/landings/edit?id=2" in loc
        assert "Cover+generated" in loc or "Cover%20generated" in loc or "Cover generated" in loc
        m = re.search(r"/generated/landing-2-[A-Za-z0-9]+\.(jpg|jpeg|png)", loc.replace("+", " ").replace("%2F", "/"))
        assert m, f"no generated path in redirect: {loc}"
        gen_path = m.group(0)
        # Follow redirect
        r2 = requests.get(f"{BASE_URL}{loc}", auth=ADMIN_AUTH, timeout=15)
        assert r2.status_code == 200
        assert 'data-testid="landing-flash"' in r2.text
        assert "Cover generated: /generated/landing-2-" in r2.text
        assert 'data-testid="l-og-preview"' in r2.text
        assert re.search(r'name="og_image"[^>]*value="/generated/landing-2-', r2.text) \
            or re.search(r'value="/generated/landing-2-[^"]*"[^>]*name="og_image"', r2.text)
        # File exists & >10KB
        fs_path = f"/app/php/public{gen_path}"
        assert os.path.exists(fs_path), fs_path
        assert os.path.getsize(fs_path) > 10 * 1024
        # DB persistence
        out = subprocess.check_output(
            ["mysql", "-udav", "-pdavpass", "davisporn", "-N", "-B",
             "-e", "SELECT og_image FROM landings WHERE id=2"],
            stderr=subprocess.STDOUT).decode().strip()
        assert out == gen_path, f"db={out!r} expected={gen_path!r}"


# -------------------- Public landing page --------------------
class TestPublicLanding:
    def test_og_meta_absolute(self):
        r = requests.get(f"{BASE_URL}/l/milf-hot", timeout=15)
        assert r.status_code == 200
        html = r.text
        m_og = re.search(r'<meta\s+property="og:image"\s+content="(https://[^"]+/generated/landing-1-[^"]+)"', html)
        assert m_og, "og:image absolute URL not found"
        m_tw = re.search(r'<meta\s+name="twitter:image"\s+content="(https://[^"]+/generated/landing-1-[^"]+)"', html)
        assert m_tw, "twitter:image absolute URL not found"
        assert m_og.group(1) == m_tw.group(1)
        assert 'name="twitter:card" content="summary_large_image"' in html \
            or "twitter:card" in html and "summary_large_image" in html


# -------------------- Static serving --------------------
class TestStaticServing:
    def test_generated_file_served(self):
        # Fetch landing 1 og_image from DB
        out = subprocess.check_output(
            ["mysql", "-udav", "-pdavpass", "davisporn", "-N", "-B",
             "-e", "SELECT og_image FROM landings WHERE id=1"]).decode().strip()
        assert out.startswith("/generated/")
        r = requests.get(f"{BASE_URL}{out}", timeout=20)
        assert r.status_code == 200
        assert r.headers.get("content-type", "").startswith("image/")
        assert len(r.content) > 10 * 1024

    def test_missing_file_404(self):
        r = requests.get(f"{BASE_URL}/generated/does-not-exist.jpg", timeout=15)
        assert r.status_code == 404


# -------------------- Error path --------------------
class TestErrorPath:
    def test_missing_landing(self):
        r = requests.post(f"{BASE_URL}/admin/landings/cover",
                          auth=ADMIN_AUTH, data={"id": "999999"},
                          allow_redirects=False, timeout=60)
        assert r.status_code == 302
        loc = r.headers.get("Location", "")
        assert "/admin/landings/edit?id=999999" in loc
        decoded = loc.replace("+", " ").replace("%3A", ":")
        assert "Cover generation failed" in decoded
        assert "Landing not found" in decoded


# -------------------- Code-review check (regen replaces) --------------------
class TestCoverGeneratorCode:
    def test_delete_old_and_update_db(self):
        src = open("/app/php/src/Support/CoverGenerator.php").read()
        assert "deleteOld(" in src
        assert "/generated/" in src
        assert "UPDATE landings" in src or "og_image" in src


# -------------------- Cron hook --------------------
class TestCronHook:
    def test_no_auth(self):
        r = requests.post(f"{BASE_URL}/api/cron/daily-suggest", timeout=15)
        assert r.status_code == 401

    def test_with_auth(self):
        r = requests.post(f"{BASE_URL}/api/cron/daily-suggest",
                          headers={"Authorization": f"Bearer {CRON_SECRET}"},
                          timeout=30)
        assert r.status_code == 200
        data = r.json()
        assert data.get("ok") is True
        assert data.get("event") == "daily-suggest-accepted"


# -------------------- Regression smoke --------------------
class TestRegression:
    def test_home(self):
        r = requests.get(f"{BASE_URL}/", timeout=15)
        assert r.status_code == 200
        assert 'data-testid="featured-grid"' in r.text

    def test_health(self):
        r = requests.get(f"{BASE_URL}/api/health", timeout=15)
        assert r.status_code == 200
        assert r.json().get("ok") is True

    def test_admin_tables(self):
        r = requests.get(f"{BASE_URL}/admin", auth=ADMIN_AUTH, timeout=15)
        assert r.status_code == 200
        assert 'data-testid="sources-table"' in r.text
        assert 'data-testid="landings-table"' in r.text
