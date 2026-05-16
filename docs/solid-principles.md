# SOLID Principles Applied to AI Skill Navigator

## Single Responsibility

AI provider calls, prompt construction and workflow orchestration are separated.

## Open/Closed

New AI providers can be added by implementing the provider interface.

## Liskov Substitution

All providers implement the same contract and can be used interchangeably.

## Interface Segregation

The plugin exposes a small text generation interface instead of forcing pages to depend on concrete APIs.

## Dependency Inversion

High-level workflow services depend on abstractions, not on concrete provider classes.