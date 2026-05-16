# Architecture Overview

The plugin is organised around Moodle pages and service classes.

The refactored AI layer contains:

- provider strategies;
- provider factory;
- prompt builder;
- workflow facade;
- backward-compatible service wrapper.

Existing pages can keep using `real_ai_service`, while the internal implementation follows clearer responsibilities.