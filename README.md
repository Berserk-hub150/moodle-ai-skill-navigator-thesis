# AI Skill Navigator

AI Skill Navigator is a Moodle plugin suite that adds course-aware AI learning tools for students and teachers.

The package contains:

- `local_aiskillnavigator`: the main local plugin with AI tutor, quiz generation, mind maps, assessments, material/RAG tools, learning-gap analysis, simulator suggestions and course-building helpers.
- `block_aiskillnavigator`: an optional course block that links users to the tools available for their role.

## Production defaults

The plugin is designed to install safely with conservative defaults:

- The default AI provider is `prototype`, which performs no external AI calls.
- External AI use for course materials is disabled until an administrator enables it.
- Per-material approval is required before teacher materials can be sent to external providers.
- Destructive AI Course Builder actions are disabled by default.
- Automatic course-resource synchronisation on Moodle events is disabled by default.
- Automatic block insertion into courses is disabled by default.
- External MathJax CDN loading is disabled by default.

Administrators can enable optional external services from the plugin settings.

## Main features

- Course-aware AI Tutor.
- AI Quiz Generator.
- AI Mind Map Generator.
- Initial and final assessments.
- Adaptive review for weak skills.
- Teacher dashboard and tutor analytics.
- Course Materials / RAG management.
- Learning-gap analysis.
- AI Course Builder with production safety gates.
- Simulator Finder and saved simulation activities.

## Technical documentation

Additional technical documentation is available in the [`docs/`](docs/) directory.

The documentation includes architectural notes, design patterns, quality goals, manual verification steps and future development directions:

- [`architecture-overview.md`](docs/architecture-overview.md): overview of the main architecture and component organization.
- [`design-patterns.md`](docs/design-patterns.md): description of the design patterns used in the AI service layer.
- [`solid-principles.md`](docs/solid-principles.md): relation between the project structure and SOLID principles.
- [`quality-goals.md`](docs/quality-goals.md): summary of the main quality goals, including maintainability, extensibility and robustness.
- [`manual-test-checklist.md`](docs/manual-test-checklist.md): manual checklist for verifying the main prototype workflows.
- [`ROADMAP.md`](docs/ROADMAP.md): possible future improvements and development directions.
- [`RELEASE_NOTES.md`](docs/RELEASE_NOTES.md): notes about releases and relevant project updates.

## Installation

Install the local plugin in:

```text
local/aiskillnavigator
```

Install the optional block in:

```text
blocks/aiskillnavigator
```

Then visit:

```text
Site administration > Notifications
```

## Configuration

Open:

```text
Site administration > Plugins > Local plugins > AI Skill Navigator
```

Important production settings:

- `Provider`: keep `prototype` for first installation checks.
- `Approve external AI for teacher materials`: disabled by default.
- `Allow destructive AI Course Builder actions`: disabled by default.
- `Automatically sync course resources on Moodle events`: disabled by default.
- `Automatically add the AI Skill Navigator block to courses`: disabled by default.
- `Enable external MathJax CDN`: disabled by default.

## Privacy

The plugin stores course materials, quiz attempts, assessment attempts, saved simulations and tutor interaction signals. It implements Moodle's Privacy API for metadata, export and deletion of user data. External AI providers are optional and disabled for course materials unless explicitly approved.

## Requirements

- Moodle 4.4 or later.
- PHP version supported by the target Moodle version.
- Optional cURL support for external AI/search providers.

## License

GPL v3 or later.
