# AI Skill Navigator

AI Skill Navigator is an academic Moodle local plugin prototype that integrates Generative AI into a university LMS to support students and teachers.

It provides AI-assisted tutoring, course-material-grounded answers, quiz generation, mind maps, simulator suggestions, learning-gap analysis and teacher analytics.

## Main features

- AI Tutor for course-aware learning support.
- Course AI Builder for creating and editing Moodle course content through natural language prompts.
- Quiz and assessment generation from teacher materials.
- Mind Map Generator for concept visualisation.
- AI Simulator Finder for practical learning activities.
- Teacher dashboard and tutor analytics.
- Course Materials / RAG area for managing extracted learning content.
- Learning-gap analysis based on student results.

## Architecture

The plugin is organised around Moodle pages, shared helpers and service classes.

```text
pages/
  Moodle page implementations

includes/
  shared helpers and rendering utilities

classes/service/
  AI providers, prompt builders, workflows and RAG services

assets/
  CSS and JavaScript resources

db/
  Moodle installation and upgrade schema
```

## Design patterns

The AI layer uses three main design patterns:

- **Strategy**: AI providers are interchangeable through a common interface.
- **Factory Method**: the provider factory creates the correct provider from Moodle settings.
- **Facade**: workflow services expose high-level AI operations to Moodle pages.

## SOLID principles

The refactored service layer applies the main SOLID principles.

| Principle | Application |
|---|---|
| Single Responsibility | Provider calls, prompt construction and workflow orchestration are separated. |
| Open/Closed | New AI providers can be added without rewriting Moodle pages. |
| Liskov Substitution | Providers share the same contract and can be used interchangeably. |
| Interface Segregation | Pages depend on small service interfaces instead of concrete API classes. |
| Dependency Inversion | High-level workflows depend on abstractions rather than vendor-specific providers. |

## Project documentation

- [Architecture overview](docs/architecture-overview.md)
- [Design patterns](docs/design-patterns.md)
- [SOLID principles](docs/solid-principles.md)
- [Quality goals](docs/quality-goals.md)
- [Manual test checklist](docs/manual-test-checklist.md)
- [Roadmap](docs/ROADMAP.md)
- [Release notes](docs/RELEASE_NOTES.md)

## Local development

Start Moodle:

```powershell
cd $env:USERPROFILE\Desktop\TESI-MOODLE
docker compose up -d
```

Run PHP lint checks:

```powershell
.\plugins\aiskillnavigator\scripts\lint-plugin.ps1
```

Deploy plugin changes:

```powershell
.\plugins\aiskillnavigator\scripts\deploy-plugin.ps1
```

## Useful local URLs

```text
http://localhost:8080/local/aiskillnavigator/pages/index.php?courseid=2
http://localhost:8080/local/aiskillnavigator/pages/course_builder.php?courseid=2
http://localhost:8080/local/aiskillnavigator/pages/tutor.php?courseid=2
http://localhost:8080/local/aiskillnavigator/pages/simulator_finder.php?courseid=2
http://localhost:8080/local/aiskillnavigator/pages/teacher_simulations.php?courseid=2
```

## Status

This repository contains an academic thesis prototype focused on feasibility, architecture, extensibility, AI integration and software quality improvements.

## License

This project is licensed under the **GNU General Public License v3.0 or later**.

See the [LICENSE](LICENSE) file for details.

SPDX-License-Identifier: `GPL-3.0-or-later`
