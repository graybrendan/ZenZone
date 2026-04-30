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
  - Intended for psychology, sports psychology, mindfulness, positive psychology material.
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
- `ZENZONE_COACH_KNOWLEDGE_MODE` (`auto`, `evidence`, `reflection`)
- `ZENZONE_COACH_REQUIRE_CITATIONS` (`true` by default for evidence)
- `ZENZONE_COACH_REQUIRE_REFLECTION_CITATIONS` (`false` by default)
- `ZENZONE_COACH_RETRIEVAL_PROVIDER` (default: `openai_file_search`)
- `ZENZONE_COACH_RETRIEVAL_MAX_RESULTS` (default: `6`, clamped `1-50`)
- `ZENZONE_COACH_RETRIEVAL_MIN_SCORE` (default: `0.0`, clamped `0-1`)
- `ZENZONE_COACH_VECTOR_STORE_IDS` (comma-separated fallback store IDs)
- `ZENZONE_COACH_VECTOR_STORE_IDS_EVIDENCE` (comma-separated evidence store IDs)
- `ZENZONE_COACH_VECTOR_STORE_IDS_REFLECTION` (comma-separated reflection store IDs)

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
