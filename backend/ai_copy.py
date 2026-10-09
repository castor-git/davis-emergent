"""AI copy sidecar for landing-page SEO copy generated with Gemini."""
import hmac
import json
import os
import re
import uuid

from dotenv import load_dotenv
from emergentintegrations.llm.chat import LlmChat, StreamDone, TextDelta, UserMessage
from fastapi import APIRouter, Header, HTTPException
from pydantic import BaseModel, Field

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

DEFAULT_MODEL = "gemini-3.1-pro-preview"

router = APIRouter()


class LandingCopyRequest(BaseModel):
    title: str = Field(min_length=2, max_length=191)
    keyword: str = Field(default="", max_length=191)
    categories: list[str] = Field(default_factory=list, max_length=12)
    tags: list[str] = Field(default_factory=list, max_length=20)


def _check_secret(provided: str) -> None:
    expected = os.environ.get("WEBHOOK_CRON_SECRET", "")
    if not expected or not hmac.compare_digest(expected, provided or ""):
        raise HTTPException(status_code=401, detail="unauthorized")


def _parse_copy(raw: str) -> dict[str, str]:
    match = re.search(r"\{.*\}", raw, re.DOTALL)
    if not match:
        raise ValueError("model returned no JSON object")
    data = json.loads(match.group(0))
    intro = re.sub(r"\s+", " ", str(data.get("intro", "")).strip())
    meta = re.sub(r"\s+", " ", str(data.get("meta_description", "")).strip())
    if not intro or not meta:
        raise ValueError("model returned incomplete SEO copy")
    return {"intro": intro[:1000], "meta_description": meta[:300]}


@router.post("/api/ai/landing-copy")
async def generate_landing_copy(
    body: LandingCopyRequest,
    x_internal_secret: str = Header(default=""),
):
    _check_secret(x_internal_secret)
    api_key = os.environ.get("EMERGENT_LLM_KEY", "")
    if not api_key:
        raise HTTPException(status_code=503, detail="EMERGENT_LLM_KEY not configured")

    context = {
        "title": body.title,
        "keyword": body.keyword,
        "categories": body.categories,
        "tags": body.tags,
    }
    prompt = (
        "Write safe, neutral, search-friendly SEO copy for an adult-video collection. "
        "Do not mention minors, coercion, graphic sexual acts, guarantees, or competitor brands. "
        "Return JSON only with exactly two string keys: intro and meta_description. "
        "intro must be 65-105 words in English, editorial and natural. "
        "meta_description must be 140-160 characters in English and distinct from intro. "
        f"Collection context: {json.dumps(context, ensure_ascii=False)}"
    )
    chat = LlmChat(
        api_key=api_key,
        session_id=f"landing-copy-{uuid.uuid4()}",
        system_message="You create concise, policy-aware SEO copy and obey the requested JSON schema.",
    ).with_model("gemini", DEFAULT_MODEL)
    parts: list[str] = []
    try:
        async for event in chat.stream_message(UserMessage(text=prompt)):
            if isinstance(event, TextDelta):
                parts.append(event.content)
            elif isinstance(event, StreamDone):
                break
        copy = _parse_copy("".join(parts))
    except Exception as exc:
        raise HTTPException(status_code=502, detail=str(exc)[:300])
    return {"ok": True, "model": DEFAULT_MODEL, "copy": copy}