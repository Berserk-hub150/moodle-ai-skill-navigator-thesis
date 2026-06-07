

<p align="center">
  <img src="assets/readme/hero-banner.png" alt="AI Skill Navigator banner" width="100%">
</p>
<p align="center">
  <img src="https://img.shields.io/badge/Moodle-Plugin-orange" alt="Moodle Plugin">
  <img src="https://img.shields.io/badge/License-GPL--3.0--or--later-blue" alt="License: GPL-3.0-or-later">
  <img src="https://img.shields.io/badge/PHP-8%2B-blue" alt="PHP 8+">
  <img src="https://img.shields.io/badge/AI-DeepSeek%20API%20%7C%20OpenAI--compatible-green" alt="DeepSeek API | OpenAI-compatible">
</p>

AI Skill Navigator is a Moodle local plugin that adds AI-powered learning and teaching tools directly inside Moodle courses.

It supports course-material-based tutoring, quiz generation, mind maps, assessments, learning-gap analysis, teacher dashboards and AI-assisted course building.

## Features

| Area | Tool | Description |
|---|---|---|
| Student | AI Tutor | Answers course-related questions using available learning materials. |
| Student | AI Quiz | Generates practice quizzes from topics or course materials. |
| Student | Mind Map Generator | Creates concept maps to help students organise and review topics. |
| Student | AI Assessments | Provides initial and final tests for course evaluation. |
| Student | Adaptive Review | Supports review of weak areas and learning gaps. |
| Teacher | Teacher Dashboard | Shows course progress, student activity and teaching signals. |
| Teacher | Tutor Analytics | Analyses questions asked by students to the AI Tutor. |
| Teacher | Initial/Final Tests | Allows teachers to create and manage assessment tests. |
| Teacher | Learning-Gap Analysis | Highlights weak topics and areas that may need reinforcement. |
| Teacher | Course Materials / RAG | Manages course materials, extracted content and RAG support. |
| Teacher | AI Course Builder | Helps create Moodle sections and resources from natural language prompts. |
| Teacher | AI Simulator Finder | Suggests simulation-based learning activities and external tools. |

## Architecture

The plugin is organised into Moodle pages, shared helpers and service classes.

Main components:

- AI provider strategies
- AI provider factory
- Prompt builders
- Workflow facade
- Material extraction and RAG support
- Teacher and student role-based views

## Local development

Start Moodle:

```powershell
cd $env:USERPROFILE\Desktop\TESI-MOODLE
docker compose up -d
```

Deploy plugin changes:

```powershell
.\plugins\aiskillnavigator\scripts\deploy-plugin.ps1
```

Run PHP lint:

```powershell
.\plugins\aiskillnavigator\scripts\lint-plugin.ps1
```

## Main pages

```text
/local/aiskillnavigator/pages/index.php
/local/aiskillnavigator/pages/tutor.php
/local/aiskillnavigator/pages/quizgenerator.php
/local/aiskillnavigator/pages/mindmapgenerator.php
/local/aiskillnavigator/pages/assessment.php
/local/aiskillnavigator/pages/teacher.php
/local/aiskillnavigator/pages/teacher_assessments.php
/local/aiskillnavigator/pages/teacher_materials.php
/local/aiskillnavigator/pages/course_builder.php
/local/aiskillnavigator/pages/simulator_finder.php
```

## AI provider

The plugin supports configurable AI providers, including DeepSeek and OpenAI-compatible endpoints.

API keys are not stored in the repository and must be configured locally through Moodle or environment settings.

## License

This project is licensed under the GNU General Public License v3.0 or later.

SPDX-License-Identifier: `GPL-3.0-or-later`

