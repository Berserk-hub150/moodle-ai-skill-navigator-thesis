# SOLID principles

The project applies SOLID principles mainly in the AI and service layer.

## Single Responsibility Principle

Provider calls, prompt construction, output processing and workflow orchestration are separated into different components.

## Open/Closed Principle

New AI providers can be added by implementing the provider interface, without rewriting Moodle pages.

## Liskov Substitution Principle

Provider implementations can be used interchangeably through the same contract.

## Interface Segregation Principle

Pages depend on small AI service contracts instead of large concrete classes.

## Dependency Inversion Principle

High-level workflows depend on abstractions and provider interfaces, not directly on vendor-specific API clients.

## Note

Some Moodle page files are still large because they act as prototype integration points. The strongest SOLID application is in the refactored AI/service layer.
