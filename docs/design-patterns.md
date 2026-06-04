# Design patterns

The plugin uses a small set of design patterns in the AI service layer.

## Strategy

AI providers are represented through a common interface. This allows different providers to be used with the same workflow logic.

Examples:

- DeepSeek provider
- OpenAI-compatible provider
- Ollama provider
- Prototype/demo provider

## Factory Method

The provider factory creates the correct provider from Moodle plugin settings, such as provider name, endpoint, model and API key.

This avoids hardcoding a specific provider inside Moodle pages.

## Facade

Workflow/facade services expose high-level AI operations to Moodle pages.

Examples:

- ask the tutor
- generate a quiz
- generate a mind map
- generate a simulator suggestion
- summarise or process course materials

The pages call high-level operations instead of directly managing provider-specific details.
