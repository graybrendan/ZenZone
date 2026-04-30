# ZenZone Coach Retrieval (Phase 1)

## Purpose
This document defines the Phase 1 retrieval-augmented AI flow for Coach.
Goal: keep the existing rule-based safety net, while allowing external AI responses grounded in trusted source retrieval.

## Current flow
1. `generateCoachResponse()` normalizes input and runs crisis detection.
2. If crisis language is detected, ZenZone returns an immediate escalation response.
3. If external AI is enabled, `generateCoachResponseFromAdapter()` calls the configured adapter endpoint with:
   - system prompt
   - strict JSON response request
   - normalized user input
   - lesson catalog
   - knowledge mode + retrieval/citation contract
4. If adapter output is invalid, missing required fields, or missing required citations, ZenZone falls back to rule-based recommendations.
5. Coach response metadata is stored in `coach_messages.metadata_json`.
6. `public/coach/view.php` renders recommendation + sources (when external AI citations are present).

## Knowledge modes
- `evidence`:
  - Intended for psychology, sport/performance psychology, mindfulness, and positive psychology material.
  - Citation requirement defaults to enabled.
- `reflection`:
  - Intended for optional philosophical reflection contexts.
  - Citation requirement defaults to disabled.

Mode is resolved by:
1. `input.knowledge_mode` if supplied (`evidence` or `reflection`).
2. `ZENZONE_COACH_KNOWLEDGE_MODE` if set (`evidence`, `reflection`, `auto`).
3. Auto detection (reflection keywords -> `reflection`, else `evidence`).

## Environment variables
- `ZENZONE_COACH_AI_ENABLED`
- `ZENZONE_COACH_AI_ENDPOINT`
- `ZENZONE_COACH_AI_TOKEN`
- `ZENZONE_COACH_AI_TIMEOUT_SECONDS` (default: `30`, clamped `10-120`)
- `ZENZONE_COACH_OLLAMA_BASE_URL` (default: `http://localhost:11434`)
- `ZENZONE_COACH_OLLAMA_MODEL` (default: `qwen3:0.6b`)
- `ZENZONE_COACH_OLLAMA_FAST_MODE` (default: `true`)
- `ZENZONE_COACH_OLLAMA_TIMEOUT_SECONDS` (default: `90`, clamped `15-300`)
- `ZENZONE_COACH_OLLAMA_NUM_PREDICT` (default: `280`, clamped `180-700`)
- `ZENZONE_COACH_LOCAL_KNOWLEDGE_MANIFEST` (default: `tmp/knowledge-manifests/combined-manifest.json`)
- `ZENZONE_COACH_LOCAL_KNOWLEDGE_DOWNLOAD_DIR` (default: `tmp/knowledge-downloads`)
- `ZENZONE_COACH_KNOWLEDGE_MODE` (`auto`, `evidence`, `reflection`)
- `ZENZONE_COACH_REQUIRE_CITATIONS` (`true` by default for evidence)
- `ZENZONE_COACH_REQUIRE_REFLECTION_CITATIONS` (`false` by default)
- `ZENZONE_COACH_RETRIEVAL_PROVIDER` (default: `openai_file_search`; use `ollama_local` for local Ollama)
- `ZENZONE_COACH_RETRIEVAL_MAX_RESULTS` (default: `6`, clamped `1-50`)
- `ZENZONE_COACH_RETRIEVAL_MIN_SCORE` (default: `0.0`, clamped `0-1`)
- `ZENZONE_COACH_VECTOR_STORE_IDS` (comma-separated fallback store IDs)
- `ZENZONE_COACH_VECTOR_STORE_IDS_EVIDENCE` (comma-separated evidence store IDs)
- `ZENZONE_COACH_VECTOR_STORE_IDS_REFLECTION` (comma-separated reflection store IDs)

## Source registry + manifest prep
- Source registry template: `docs/knowledge-sources.json`
- Validation/manifest script: `scripts/prepare_knowledge_manifest.php`

Run validation only:
- `C:\xampp\php\php.exe scripts/prepare_knowledge_manifest.php --check-only`

Build manifests for uploader workflows:
- `C:\xampp\php\php.exe scripts/prepare_knowledge_manifest.php`

Output files are written to:
- `tmp/knowledge-manifests/combined-manifest.json`
- `tmp/knowledge-manifests/evidence-manifest.json`
- `tmp/knowledge-manifests/reflection-manifest.json`

## Knowledge-base upload
Uploader script:
- `scripts/upload_knowledge_to_openai.php`

Dry run first:
- `C:\xampp\php\php.exe scripts/upload_knowledge_to_openai.php --dry-run`

Upload to OpenAI:
- Set `OPENAI_API_KEY` in the shell or environment.
- Run `C:\xampp\php\php.exe scripts/upload_knowledge_to_openai.php`

What the uploader does:
1. Reads `tmp/knowledge-manifests/combined-manifest.json`.
2. Creates separate OpenAI vector stores for `evidence` and `reflection` if store IDs are not supplied.
3. Downloads URL sources to `tmp/knowledge-downloads/`.
4. Uploads each source file to OpenAI Files.
5. Attaches each file to the correct vector store with source metadata attributes.
6. Polls vector store file indexing until each file is completed, failed, cancelled, or timed out.
7. Writes `tmp/knowledge-manifests/openai-upload-map.json`.

To reuse existing vector stores:
- `C:\xampp\php\php.exe scripts/upload_knowledge_to_openai.php --store-id-evidence=vs_... --store-id-reflection=vs_...`

After a successful upload, copy the vector store IDs from `tmp/knowledge-manifests/openai-upload-map.json` into:
- `ZENZONE_COACH_VECTOR_STORE_IDS_EVIDENCE`
- `ZENZONE_COACH_VECTOR_STORE_IDS_REFLECTION`

Notes:
- `tmp/` is ignored by Git, so upload maps and downloaded source copies stay local.
- The script skips completed records already present in `openai-upload-map.json` unless `--force` is supplied.
- No database migration is required.

## Local Ollama adapter setup
Adapter endpoint:
- `api/coach/ollama_adapter.php`

Install Ollama, then pull the recommended small local model:
- `ollama pull qwen3:0.6b`

If your machine has a dedicated GPU and `qwen3:0.6b` is too weak, try:
- `ollama pull qwen3:4b`
- then change `COACH_OLLAMA_MODEL` to `qwen3:4b`.

For XAMPP, the simplest local setup is to create this gitignored file:
- `includes/config.local.php`

Example local config:
```php
<?php

define('COACH_AI_ENABLED', '1');
define('COACH_AI_ENDPOINT', 'http://127.0.0.1/ZenZone/api/coach/ollama_adapter.php');
define('COACH_AI_TOKEN', 'make-a-long-random-local-token');
define('COACH_AI_TIMEOUT_SECONDS', '120');
define('COACH_RETRIEVAL_PROVIDER', 'ollama_local');

define('COACH_OLLAMA_BASE_URL', 'http://127.0.0.1:11434');
define('COACH_OLLAMA_MODEL', 'qwen3:0.6b');
define('COACH_OLLAMA_FAST_MODE', '1');
define('COACH_OLLAMA_TIMEOUT_SECONDS', '120');
define('COACH_OLLAMA_NUM_PREDICT', '280');

define('COACH_LOCAL_KNOWLEDGE_MANIFEST', 'tmp/knowledge-manifests/combined-manifest.json');
define('COACH_LOCAL_KNOWLEDGE_DOWNLOAD_DIR', 'tmp/knowledge-downloads');
```

`COACH_OLLAMA_FAST_MODE=1` avoids slow per-request local generation and builds the response from ZenZone's lesson catalog plus local retrieval citations. Set it to `0` only when you want the local model to draft wording and can tolerate slower responses.

After editing `includes/config.local.php`:
1. Restart Apache in XAMPP.
2. Make sure Ollama is running at `http://127.0.0.1:11434`.
3. Create a Coach situation in the app.
4. Open the Coach result page and check:
   - Source mode: `External AI`
   - Knowledge mode: `Evidence` or `Reflection`
   - Sources panel contains citations.

If the adapter fails, ZenZone falls back to the rule-based Coach response.

## Adapter request contract
ZenZone now sends these retrieval-related fields in the adapter payload:
- `knowledge_mode`
- `knowledge_contract.require_citations`
- `knowledge_contract.citation_minimum`
- `knowledge_contract.retrieval`
  - `provider`
  - `knowledge_mode`
  - `vector_store_ids`
  - `max_num_results`
  - `min_score`
  - `include_search_results`
- `knowledge_contract.disallow_fabricated_sources`

## Adapter response contract (preferred)
Existing fields remain required:
- `crisis_detected`
- `crisis_message`
- `summary`
- `top_recommendation`
- `alternatives`
- `coach_message`
- `source_mode`

New retrieval fields ZenZone accepts:
- `knowledge_mode`
- `citations` (array)
- `retrieval_metadata` (object)

Citation item fields accepted:
- `title`
- `url`
- `file_id`
- `filename`
- `score` (`0-1`)
- `evidence_tier`
- `excerpt`

ZenZone also accepts alternate keys and nested envelopes, then normalizes to the structure above.

## UI behavior
- Coach view now shows:
  - source mode (external AI vs rule-based)
  - knowledge mode (evidence vs reflection)
  - sources panel when `source_mode` is external AI
  - normalized citation list, relevance %, and source links when available

## Safety behavior
- Crisis flow does not depend on retrieval.
- If evidence-mode citations are required but missing, external response is rejected and rule-based fallback is used.
- No schema migration was required for Phase 1 because metadata is persisted in `coach_messages.metadata_json`.
