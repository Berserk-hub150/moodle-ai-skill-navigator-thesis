# AI Skill Navigator - RAG, privacy and portability notes

## RAG
RAG means Retrieval Augmented Generation. The plugin extracts text from teacher/course materials, splits it into chunks, computes embeddings, retrieves the most relevant chunks for a question or generation task, and sends only that retrieved context to the LLM.

## Material privacy
Each material has an AI policy:
- local_only: usable with local/prototype providers only
- external_allowed: teacher allows sending this material to external API providers

When an external provider is configured, the material selector hides local-only materials.

## LLM provider strategy
The plugin supports:
- OpenRouter / multi-model gateway
- OpenAI-compatible APIs
- Ollama/local endpoints
- Custom HTTP JSON templates for unusual APIs

For Claude, Gemini, HuggingFace or other providers, use OpenRouter when possible or Custom HTTP JSON when the API is not OpenAI-compatible.

## Moodle portability
The plugin must be installed through Moodle's plugin system, then admin/cli/upgrade.php must run. It should not rely on Docker, Bitnami paths, localhost, or Windows local paths inside application code.