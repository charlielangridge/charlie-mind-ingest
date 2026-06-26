# CharlieMind Ingest

CharlieMind Ingest is a small Laravel API for receiving captures from an iPhone Shortcut and writing them into an Obsidian-compatible inbox.

It stores every capture as its own Markdown file, with optional uploaded media saved beside it using stable vault-relative paths. It does not run AI processing, transcription, GitHub sync, an admin panel, or a frontend.

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

- AI processing
- transcription
- GitHub syncing
- Codex processing commands
- Cherri Shortcut generation
