# Design Patterns

The refactoring introduces three GoF / Refactoring Guru design patterns.

## Strategy

Used for interchangeable AI providers:

- Ollama;
- OpenAI-compatible APIs;
- prototype/demo provider.

## Factory Method

Used to centralise the creation of the correct AI provider from Moodle settings.

## Facade

Used to expose simple workflow methods to Moodle pages while hiding provider selection and prompt construction.