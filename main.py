import io
import re
from typing import Optional

import pytesseract
from fastapi import FastAPI, File, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from PIL import Image

app = FastAPI(title="Invoice Extractor API")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:3000"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def normalize_currency(value: str) -> str:
    cleaned = value.replace(",", "").strip()
    if not cleaned:
        return ""
    return f"${float(cleaned):.2f}"


def extract_total_amount(text: str) -> Optional[str]:
    patterns = [
        r"(?i)(?:total(?:\s+(?:amount|due|invoice))?|amount\s*(?:due)?|balance\s*due)\s*[:\-]?\s*\$?\s*(\d+(?:,\d{3})*(?:\.\d{2})?)",
        r"(?i)\$\s*(\d+(?:,\d{3})*(?:\.\d{2})?)",
    ]

    for pattern in patterns:
        match = re.search(pattern, text)
        if match:
            amount = match.group(1)
            return normalize_currency(amount)

    return None


def extract_vendor_name(text: str) -> Optional[str]:
    candidate_lines = []
    for line in text.splitlines():
        cleaned = line.strip()
        if not cleaned:
            continue
        lowered = cleaned.lower()
        if re.search(r"[A-Za-z]{3,}", cleaned) and not re.search(
            r"invoice|total|amount|date|bill|receipt|payment|due|balance|tax|subtotal",
            lowered,
        ):
            candidate_lines.append(cleaned)

    for line in candidate_lines[:10]:
        if len(line.split()) <= 6:
            return line

    return None


def extract_date(text: str) -> Optional[str]:
    patterns = [
        r"\b(?:0?[1-9]|1[0-2])[/-](?:0?[1-9]|[12]\d|3[01])[/-](?:\d{2}|\d{4})\b",
        r"\b(?:\d{4})[/-](?:0?[1-9]|1[0-2])[/-](?:0?[1-9]|[12]\d|3[01])\b",
        r"\b(?:0?[1-9]|[12]\d|3[01])\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+(?:\d{2}|\d{4})\b",
        r"\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+(?:0?[1-9]|[12]\d|3[01]),?\s+(?:\d{2}|\d{4})\b",
    ]

    for pattern in patterns:
        match = re.search(pattern, text, flags=re.IGNORECASE)
        if match:
            return match.group(0)

    return None


@app.get("/health")
async def health_check():
    return {"status": "ok"}


@app.post("/api/extract-invoice")
async def extract_invoice(file: UploadFile = File(...)):
    if not file.content_type or "image" not in file.content_type:
        raise HTTPException(status_code=400, detail="Please upload an image file.")

    try:
        image_bytes = await file.read()
        image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
        text = pytesseract.image_to_string(image)
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Failed to process uploaded image: {str(exc)}") from exc

    extracted = {
        "vendor_name": extract_vendor_name(text),
        "total_amount": extract_total_amount(text),
        "date": extract_date(text),
        "raw_text": text.strip(),
    }

    return extracted
