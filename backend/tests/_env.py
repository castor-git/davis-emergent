"""Shared test configuration — all URLs and credentials come from .env files, never from source."""
import os
from dotenv import load_dotenv

load_dotenv("/app/backend/.env")
load_dotenv("/app/frontend/.env")


def _required(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise RuntimeError(f"{name} must be set in backend/.env or frontend/.env before running tests")
    return value


BASE_URL = _required("REACT_APP_BACKEND_URL").rstrip("/")
PUBLIC_HOST = BASE_URL.split("://", 1)[-1]
ADMIN_AUTH = (_required("ADMIN_USER"), _required("ADMIN_PASS"))
CRON_SECRET = _required("WEBHOOK_CRON_SECRET")
