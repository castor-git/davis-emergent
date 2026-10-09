# FastAPI reverse-proxy to the PHP application running on 127.0.0.1:9000.
# The Kubernetes ingress routes /api/* to this service (port 8001). We forward
# every request (path/method/headers/body) to PHP so that /api/* endpoints
# implemented in PHP (e.g. /api/suggest, /api/health) are reachable from the
# public preview URL.
import os
import httpx
from fastapi import FastAPI, Request, Response

from ai_image import router as ai_router

PHP_UPSTREAM = os.environ.get("PHP_UPSTREAM", "http://127.0.0.1:9000")

app = FastAPI(title="DAVISPORN proxy")
app.include_router(ai_router)  # must be registered before the catch-all proxy route

HOP_BY_HOP = {"connection", "keep-alive", "proxy-authenticate", "proxy-authorization",
              "te", "trailer", "transfer-encoding", "upgrade", "content-encoding",
              "content-length", "host"}

client = httpx.AsyncClient(timeout=30.0, follow_redirects=False)

@app.on_event("shutdown")
async def _close():
    await client.aclose()

async def _proxy(request: Request, path: str) -> Response:
    url = f"{PHP_UPSTREAM}/{path}"
    if request.url.query:
        url += f"?{request.url.query}"
    headers = {k: v for k, v in request.headers.items() if k.lower() not in HOP_BY_HOP}
    # Preserve original client host/proto so PHP can build absolute URLs (sitemap, canonical).
    fwd_host = request.headers.get("x-forwarded-host") or request.headers.get("host")
    if fwd_host:
        headers["X-Forwarded-Host"] = fwd_host
    fwd_proto = request.headers.get("x-forwarded-proto") or ("https" if request.url.scheme == "https" else "http")
    headers["X-Forwarded-Proto"] = fwd_proto
    body = await request.body()
    try:
        upstream = await client.request(request.method, url, headers=headers, content=body)
        resp_headers = {k: v for k, v in upstream.headers.items() if k.lower() not in HOP_BY_HOP}
        return Response(content=upstream.content, status_code=upstream.status_code, headers=resp_headers)
    except httpx.RequestError as e:
        return Response(f"Upstream error: {e}", status_code=502)

@app.api_route("/{full_path:path}", methods=["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS", "HEAD"])
async def catch_all(full_path: str, request: Request):
    return await _proxy(request, full_path)
