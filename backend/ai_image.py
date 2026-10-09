"""AI image sidecar: PHP calls POST /api/ai/image (shared-secret protected) and gets a base64 PNG back.

Uses the Emergent universal key through emergentintegrations (Gemini Nano Banana).
"""
import hmac
import os
import uuid

from dotenv import load_dotenv
from emergentintegrations.llm.chat import LlmChat, UserMessage
from fastapi import APIRouter, Header, HTTPException
from pydantic import BaseModel, Field

load_dotenv(os.path.join(os.path.dirname(__file__), ".env"))

DEFAULT_MODEL = "gemini-3.1-flash-image-preview"
ALLOWED_MODELS = {DEFAULT_MODEL, "gemini-3-pro-image-preview"}

router = APIRouter()


class ImageRequest(BaseModel):
    prompt: str = Field(min_length=10, max_length=4000)
    model: str = DEFAULT_MODEL


def _check_secret(provided: str) -> None:
    expected = os.environ.get("WEBHOOK_CRON_SECRET", "")
    if not expected or not hmac.compare_digest(expected, provided or ""):
        raise HTTPException(status_code=401, detail="unauthorized")


@router.post("/api/ai/image")
async def generate_image(body: ImageRequest, x_internal_secret: str = Header(default="")):
    _check_secret(x_internal_secret)
    api_key = os.environ.get("EMERGENT_LLM_KEY", "")
    if not api_key:
        raise HTTPException(status_code=503, detail="EMERGENT_LLM_KEY not configured")
    model = body.model if body.model in ALLOWED_MODELS else DEFAULT_MODEL

    chat = LlmChat(api_key=api_key, session_id=f"cover-{uuid.uuid4()}",
                   system_message="You are a graphic designer producing safe-for-work abstract cover art.")
    chat.with_model("gemini", model).with_params(modalities=["image", "text"])
    try:
        text, images = await chat.send_message_multimodal_response(UserMessage(text=body.prompt))
    except Exception as e:  # ChatError wraps provider failures
        raise HTTPException(status_code=502, detail=str(e)[:300])
    if not images:
        raise HTTPException(status_code=502, detail=f"no image returned: {(text or '')[:200]}")
    img = images[0]
    return {"ok": True, "model": model, "mime_type": img["mime_type"], "data": img["data"]}
