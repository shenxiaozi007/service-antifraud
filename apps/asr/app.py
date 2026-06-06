import os
import tempfile
from functools import lru_cache
from pathlib import Path

from fastapi import FastAPI, File, HTTPException, UploadFile
from faster_whisper import WhisperModel


app = FastAPI(title="Guardian Max ASR", version="1.0.0")


@lru_cache(maxsize=1)
def whisper_model() -> WhisperModel:
    """Load the lightest local ASR model once per process."""
    return WhisperModel(
        os.getenv("ASR_MODEL_SIZE", "tiny"),
        device=os.getenv("ASR_DEVICE", "cpu"),
        compute_type=os.getenv("ASR_COMPUTE_TYPE", "int8"),
        cpu_threads=int(os.getenv("ASR_CPU_THREADS", "1")),
        num_workers=1,
    )


@app.get("/health")
def health() -> dict:
    """Return service health status."""
    return {"status": "ok"}


@app.post("/transcribe")
async def transcribe(file: UploadFile = File(...), language: str = "zh") -> dict:
    """Transcribe an uploaded audio file."""
    suffix = Path(file.filename or "audio.webm").suffix or ".webm"

    with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
        tmp.write(await file.read())
        tmp_path = tmp.name

    try:
        segments, info = whisper_model().transcribe(
            tmp_path,
            language=language or "zh",
            vad_filter=True,
            beam_size=1,
        )
        text = "".join(segment.text for segment in segments).strip()
    except Exception as exc:
        raise HTTPException(status_code=500, detail=str(exc)) from exc
    finally:
        try:
            os.unlink(tmp_path)
        except OSError:
            pass

    return {
        "text": text,
        "language": getattr(info, "language", language),
        "duration": getattr(info, "duration", 0),
        "model": os.getenv("ASR_MODEL_SIZE", "tiny"),
    }
