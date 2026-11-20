# Changelog

All notable changes to `angeo/module-ai-assist` are documented here. The
format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] — 2026-11-08

### Added

- "Ask the AI assistant" panel on the Angeo AEO admin pages: explain these
  results, suggest fixes, or ask a question.
- Providers: Anthropic Claude (Messages API), OpenAI (Chat Completions),
  Google Gemini (generateContent). Your own API key, stored encrypted and
  marked sensitive.
- Facts are built on the server by the owning module through the
  `angeo_ai_assist_context` event (see CONTEXT.md), checked against its ACL,
  stripped of personal and secret keys, and capped at 24 KB.
- Fixed safety rules in the system prompt: facts only, facts are data, no
  claimed actions, every percentage with its denominator.
- Answer cache keyed by the facts, the question and the language; daily
  limit per admin user; cleanup cron after 90 days.
- Answers rendered as escaped text with a Markdown subset; code blocks with
  a copy button; links never clickable.
- ACL: `Angeo_AiAssist::use`, `Angeo_AiAssist::config`.
