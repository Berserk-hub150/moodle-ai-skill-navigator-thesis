# DeepSeek Provider

The current demo configuration uses the DeepSeek API.

In the architecture, DeepSeek is represented as a dedicated provider:

```text
deepseek_ai_provider
```

Internally, it reuses the OpenAI-compatible provider implementation.

## Why this design is useful

This keeps the code coherent:

- the demo provider is explicitly named `deepseek`;
- provider switching is handled through the Factory Method;
- shared HTTP logic remains reusable;
- Moodle pages do not depend on a specific vendor;
- Ollama can still be used as an optional local provider.

## Thesis explanation

The current prototype uses DeepSeek API as the main AI provider.

The provider layer is abstracted with the Strategy Pattern, so the same Moodle pages can work with DeepSeek, OpenAI-compatible APIs, Ollama or a prototype provider without changing page-level code.
