# Provider Configuration

The current demo setup uses the DeepSeek API.

DeepSeek is configured as a dedicated provider name, but internally it uses the OpenAI-compatible chat completions strategy.

## Current demo provider

| Setting | Value |
|---|---|
| Provider | `deepseek` |
| Endpoint | `https://api.deepseek.com` |
| Model | `deepseek-chat` |
| API key | Configured in Moodle/plugin settings, not stored in the repository |

## Supported provider names

- `deepseek`
- `openai`
- `openai_compatible`
- `openrouter`
- `groq`
- `ollama`
- `prototype`
- `mock`
- `demo`

## Architecture note

The plugin uses the Strategy Pattern for AI providers.

The DeepSeek provider is a named strategy built on top of the OpenAI-compatible provider implementation.

Ollama remains available as an optional local provider, but it is not the default provider of the current demo.
