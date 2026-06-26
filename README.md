# CharlieMind Ingest

CharlieMind Ingest is a small Laravel API for receiving captures from an iPhone Shortcut and writing them into an Obsidian-compatible inbox.

It stores every capture as its own Markdown file, with optional uploaded media saved beside it using stable vault-relative paths. It can also process pending captures into cleaned Obsidian notes. It does not run GitHub sync, an admin panel, a scheduler, or a frontend.

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan captures:health
```

For local development with Laravel's built-in server:

```bash
php artisan serve
```

## Environment

Set these values in `.env`:

```env
CAPTURE_API_TOKEN=change-me
CHARLIEMIND_STORAGE_ROOT=charliemind
CAPTURE_PROCESSOR_ENABLED=true
OPENAI_API_KEY=
OPENAI_TEXT_MODEL=gpt-4.1-mini
OPENAI_TRANSCRIPTION_MODEL=gpt-4o-mini-transcribe
CAPTURE_PROCESSOR_DRY_RUN=false
CAPTURE_PROCESSOR_MAX_PER_RUN=20
CAPTURE_PROCESSOR_ARCHIVE_RAW=false
```

`CHARLIEMIND_STORAGE_ROOT=charliemind` writes files under:

```text
storage/app/charliemind
```

## Authentication

All capture endpoints require bearer-token auth:

```http
Authorization: Bearer change-me
Accept: application/json
```

Missing or invalid tokens return:

```json
{
  "message": "Unauthenticated."
}
```

## Endpoints

```http
POST /api/captures
GET /api/captures
GET /api/captures/{capture_id}
```

`GET /api/captures` supports:

```text
?status=pending
?type=voice
?limit=50
```

## Text Capture

```bash
curl -X POST http://127.0.0.1:8000/api/captures \
  -H "Authorization: Bearer change-me" \
  -H "Accept: application/json" \
  -d "type=idea" \
  -d "body=Idea for DoorScan: estimate queue length from recent scans."
```

## Task Capture

```bash
curl -X POST http://127.0.0.1:8000/api/captures \
  -H "Authorization: Bearer change-me" \
  -H "Accept: application/json" \
  -d "type=task" \
  -d "body=Call Dylan about Martin Audio pricing."
```

## Link Capture

```bash
curl -X POST http://127.0.0.1:8000/api/captures \
  -H "Authorization: Bearer change-me" \
  -H "Accept: application/json" \
  -d "type=link" \
  -d "title=Interesting Laravel package" \
  -d "url=https://example.com" \
  -d "body=Worth reviewing later."
```

## Voice Capture

```bash
curl -X POST http://127.0.0.1:8000/api/captures \
  -H "Authorization: Bearer change-me" \
  -H "Accept: application/json" \
  -F "type=voice" \
  -F "source=iphone" \
  -F "file=@recording.m4a"
```

## Capture Processing

Process pending captures with:

```bash
php artisan captures:process
```

Useful options:

```bash
php artisan captures:process --limit=5
php artisan captures:process --type=voice
php artisan captures:process --id=2026-06-26-161905
php artisan captures:process --dry-run
php artisan captures:process --retry-failed
```

By default, the command processes `pending` captures only. `--retry-failed` includes failed captures. `--id=` accepts either the public `capture_id` or the database ID. `--type=` filters by capture type. `--dry-run` previews the destination path without writing processed notes or updating capture records.

If `CAPTURE_PROCESSOR_ENABLED=false`, the command exits successfully without changing anything.

### Text Processing

Text-like captures are cleaned into Obsidian Markdown notes. If `OPENAI_API_KEY` is configured, the processor uses the Laravel AI SDK with `OPENAI_TEXT_MODEL` to classify, summarize, extract clear tasks, and add obvious Obsidian links.

Without an OpenAI key, text captures still process locally using deterministic fallback formatting:

- folder from original type
- title from the first sentence or first 8 words
- checklist extraction for task captures
- sparse tags
- `confidence: low`

### Voice Processing

Voice captures require `OPENAI_API_KEY`. Audio is transcribed through the Laravel AI SDK using `OPENAI_TRANSCRIPTION_MODEL`, then the transcript is processed into a cleaned note.

If no OpenAI key is available, voice captures fail with:

```text
OpenAI API key required for voice transcription
```

The failure is stored on the capture record in `processing_error`.

### Processed Notes

Processed notes are written as vault-relative Markdown paths such as:

```text
Tasks/2026-06-26 - call dylan about martin audio pricing.md
Ideas/2026-06-26 - doorscan queue length estimation.md
Voice/2026-06-26 - voice note.md
```

The database stores these paths in `processed_markdown_path` without the configured storage root.

Raw captures are preserved in place. V1 does not delete, move, or archive raw inbox files, even when `CAPTURE_PROCESSOR_ARCHIVE_RAW` is set.

### Processing Log

Each non-dry-run processing pass appends a summary to:

```text
inbox/processing-log.md
```

The log is written through `CharlieMindStorage`, so it uses the same configured disk and root as capture files.

### Current Limitations

- Only text-like captures and voice captures are actively processed in v1.
- Photo and document captures remain raw inbox captures unless they contain useful text bodies.
- Scheduling is not implemented yet.
- GitHub sync and Obsidian Sync integration are not implemented.
- Voice processing requires an OpenAI API key.

## Photo Capture

```bash
curl -X POST http://127.0.0.1:8000/api/captures \
  -H "Authorization: Bearer change-me" \
  -H "Accept: application/json" \
  -F "type=photo" \
  -F "body=Rack wiring photo from site visit" \
  -F "file=@image.jpg"
```

## Storage Layout

Markdown files and media are saved beneath the CharlieMind storage root:

```text
storage/app/charliemind/
  inbox/
    captures/
      quick/
      tasks/
      ideas/
      dev/
      scouts/
      wine/
      links/
      general/
    voice/
    audio/
    photos/
    media/
      photos/
      documents/
    processed/
    failed/
```

Paths returned by the API are relative to `storage/app/charliemind`, which means Obsidian embeds can use vault-relative links such as:

```markdown
![[inbox/audio/2026-06-26-141233.m4a]]
```

## CharlieMind and Obsidian

This service is the capture surface for CharlieMind. It creates atomic inbox notes that can be moved, processed, transcribed, or enriched later by Codex or other CharlieMind tooling.

Each capture gets a stable `capture_id` in this format:

```text
YYYY-MM-DD-HHMMSS
```

If two captures arrive in the same second, the later capture receives a short suffix:

```text
2026-06-26-141233-a1b2
```

## Cherri Shortcut Next Steps

The API is designed so a future Cherri-generated Shortcut can send JSON or multipart form-data with:

```text
type
title
body
url
source
captured_at
file
metadata
```

Future extension points are intentionally left outside v1:

- GitHub syncing
- Cherri Shortcut generation
