# AI Skill Navigator

AI Skill Navigator is a Moodle local plugin developed as a thesis prototype for AI-supported learning inside a university LMS.

The plugin integrates AI-based learning support tools into Moodle, including tutoring, course-material-grounded assistance, quiz generation, mind map generation, XR scenario generation and teacher-oriented learning analytics.

## Main features

- General AI Tutor for open learning questions.
- Course AI Tutor grounded on teacher materials.
- AI Quiz Generator for formative micro-tests.
- AI Mind Map Generator for concept visualisation.
- AI XR Scenario Generator for structured Virtual Worlds learning scenarios.
- Teacher materials management.
- Student dashboard with learning progress indicators.
- Teacher dashboard with class overview and weak-topic analysis.
- RAG-oriented support for selected course materials.

## Thesis context

The project explores how generative AI can support digital learning in Moodle, with a focus on:

- personalised learning support;
- AI-assisted formative assessment;
- course-material-grounded tutoring;
- digital skills training;
- extensibility toward Virtual Worlds and XR learning environments.

The plugin is intended as an academic thesis prototype, not as a production-ready commercial Moodle extension.

## Architecture

The plugin is organised around Moodle pages and service classes.

The refactored AI layer contains:

- AI provider strategies;
- a provider factory;
- a prompt builder;
- a workflow facade;
- backward-compatible Moodle page integrations.

This keeps Moodle pages simpler while moving AI-related responsibilities into dedicated service classes.

## Design patterns

The refactoring introduces three design patterns.

### Strategy

The AI provider logic is abstracted through a common interface.

Implemented strategies include:

- Ollama provider;
- OpenAI-compatible provider;
- prototype/demo provider.

This makes it possible to switch AI providers without changing Moodle pages.

### Factory Method

The provider factory creates the correct AI provider based on Moodle plugin settings.

This centralises provider selection and avoids spreading configuration logic across the plugin.

### Facade

The AI workflow facade exposes high-level operations such as:

- asking the tutor;
- generating quizzes;
- generating mind maps;
- generating XR scenarios;
- summarising materials.

The facade hides provider selection, prompt construction and workflow orchestration from page controllers.

## SOLID principles

| Principle | Application |
|---|---|
| Single Responsibility | Provider calls, prompt construction and workflow orchestration are separated. |
| Open/Closed | New AI providers can be added by implementing the provider interface. |
| Liskov Substitution | Provider implementations can be used interchangeably through the same contract. |
| Interface Segregation | Pages depend on a small text-generation interface instead of concrete API classes. |
| Dependency Inversion | High-level workflows depend on abstractions rather than concrete provider classes. |

## Software quality attributes

| Quality attribute | Improvement |
|---|---|
| Maintainability | Smaller service classes and clearer responsibilities. |
| Reusability | AI services are reused by tutor, quiz, mind map and XR modules. |
| Portability | Provider, endpoint and model are configurable. |
| Robustness | Prototype mode keeps the demo usable without an external AI service. |
| Verifiability | PHP files can be linted and tested independently. |
| Comprehensibility | Documentation explains the role of each component. |
| Interoperability | The plugin remains integrated with Moodle courses and pages. |

## Local development

The recommended local setup uses Docker and Moodle.

Start Moodle:

```powershell
cd $env:USERPROFILE\Desktop\TESI-MOODLE
docker compose up -d
```

Deploy plugin changes into the Moodle container:

```powershell
.\plugins\aiskillnavigator\scripts\deploy-plugin.ps1
```

Run PHP lint checks:

```powershell
.\plugins\aiskillnavigator\scripts\lint-plugin.ps1
```

## Main plugin pages

After starting Moodle, the plugin can be tested from these local URLs:

```text
http://localhost:8080/local/aiskillnavigator/index.php
http://localhost:8080/local/aiskillnavigator/tutor.php?courseid=1
http://localhost:8080/local/aiskillnavigator/quizgenerator.php?courseid=1
http://localhost:8080/local/aiskillnavigator/mindmapgenerator.php?courseid=1
http://localhost:8080/local/aiskillnavigator/scenariogenerator.php?courseid=1
```

## AI provider configuration

The AI provider is selected through Moodle plugin settings.

Supported provider names include:

- `ollama`;
- `openai`;
- `openai_compatible`;
- `openrouter`;
- `prototype`;
- `mock`;
- `demo`.

Default development configuration:

| Setting | Value |
|---|---|
| Provider | `ollama` |
| Endpoint | `http://host.docker.internal:11434` |
| Model | `qwen2.5:3b` |

## Repository structure

```text
classes/service/
  AI services, provider strategies, prompt builder and workflow facade

db/
  Moodle access rules and database installation files

docs/
  Architecture, SOLID, design pattern and quality documentation

lang/en/
  Moodle language strings

scripts/
  Local helper scripts for deploy, lint and manual checks

*.php
  Moodle plugin pages
```

## Manual test checklist

Before presenting the project:

1. Start Docker.
2. Deploy the plugin.
3. Purge Moodle caches.
4. Open the plugin dashboard.
5. Test the General AI Tutor.
6. Test the Course AI Tutor.
7. Test the Quiz Generator.
8. Test the Mind Map Generator.
9. Test the XR Scenario Generator.
10. Check that no Moodle error page is displayed.

## Status

This repository contains an academic thesis prototype.

The current implementation focuses on demonstrating feasibility, architecture, extensibility and software quality improvements rather than production deployment.
