# Smart Sourcing Academy — Course Player Content Protection

This document describes Layer 1 (client-side hardening) and Layer 2 (signed media streaming) for the course player.

## What is protected

| Area | Protection |
|------|------------|
| **Course player pages** | Copy, cut, paste, text selection, right-click, and common shortcuts (Ctrl/Cmd+C, S, U, P, A) |
| **Uploaded lesson videos** | On-demand 30-minute signed stream tokens + per-session playback tokens (`X-Playback-Token`); optional `blob:` delivery for files ≤ 50 MB; HTTP Range streaming; raw MP4 path never sent in player payload; `controlsList=nodownload`; right-click / DevTools shortcut blocking |
| **Legal access gate** | Combined Terms & Conditions + NDA must be accepted before dashboard, enrollment, or lesson playback (registration checkbox + `/legal/agreement` for OAuth/legacy users) |
| **Uploaded lesson documents & images** | Served via the same signed lesson media route |
| **View-only lesson resources** | Inline view endpoint only; download blocked server-side |
| **YouTube / Vimeo / embed lessons** | Unchanged — third-party players |

Applies to **all learners** (internal employees and external learners) when using the course player.

## What is NOT protected

| Area | Reason |
|------|--------|
| **Assignment PDF take-offs** | Students need offline work; assignment tabs and submission flows are outside the player protection wrapper |
| **Downloadable lesson resources** | Trainer opt-in via **Allow students to download** (default: on) |
| **Student portal resource downloads** | Course resources tab uses normal download routes with enrollment checks |
| **Dashboard / course editor** | Trainers and admins manage content without restrictions |
| **External embeds** | YouTube, Vimeo, Office Online viewer, etc. |

## Layer 2 — Signed video / media URLs

1. On player load, **video** lessons expose `stream_protected: true` with no `lesson_src` in the page payload.
2. The player requests `GET /play-course/video/{lesson}/stream-url` (session auth, rate-limited), receives a **30-minute signed URL** plus a **playback token**, and loads via `blob:` (≤ 50 MB) or signed stream.
3. Stream endpoint: `GET /play-course/video/{lesson}/stream` (signed, supports HTTP Range for seeking). Requires valid `X-Playback-Token` header matching the authenticated user and lesson.
4. Documents/images still use `GET /play-course/media/{lesson}` (2-hour signed URL).
3. Server verifies:
   - Signature not expired (default: **2 hours**)
   - User is authenticated
   - User is enrolled in the course, or is admin / owning instructor
4. File is streamed inline with `Cache-Control: private, no-store`.

Lesson resources:

- **Downloadable** (`is_downloadable = true`): `GET /lesson/resources/download/{id}` — enrollment required.
- **View-only** (`is_downloadable = false`): `GET /lesson/resources/view/{resource}` — inline only; download returns 403.

## Key files

- `resources/js/hooks/use-content-protection.ts` — client hardening
- `resources/js/pages/course-player/index.tsx` — protection wrapper
- `resources/js/components/video-player.tsx` — Plyr + secure stream + download guards
- `resources/js/hooks/use-secure-video-stream.ts` — blob/signed playback loader
- `resources/js/hooks/use-video-player-guards.ts` — player-level shortcut blocking
- `app/Services/Course/ProtectedMediaService.php` — signing, range streaming & authorization
- `app/Http/Controllers/Course/VideoStreamController.php` — secure video token + stream
- `app/Services/Course/VideoPlaybackTokenService.php` — per-session playback authorization
- `app/Services/LegalAgreementService.php` — T&C + NDA acceptance gate
- `app/Http/Middleware/EnsureLegalAgreementAccepted.php` — blocks dashboard/lesson routes until accepted
- `resources/js/pages/legal/agreement.tsx` — post-login legal acceptance page
- `app/Http/Controllers/Course/ProtectedMediaController.php` — document/image streaming
- `app/Http/Controllers/Course/LessonResourceController.php` — view/download gates

## Known limitations

Client-side protection is **deterrence**, not DRM:

- Browser DevTools can still inspect network traffic and signed URLs while valid
- Screen recording and screenshots cannot be blocked in the browser
- A signed URL can be shared until it expires (within the TTL window)
- Determined users can bypass JavaScript event handlers
- External video hosts (YouTube/Vimeo) have their own download/share controls

For stronger protection, consider commercial DRM (Widevine/FairPlay), watermarking, or a dedicated video CDN with tokenized playback.
