<?php

defined('MOODLE_INTERNAL') || die();

function local_aiskillnavigator_tool_registry(): array {
    return [
        [
            'section' => 'student',
            'label' => 'AI Assessments',
            'title' => 'AI assessments',
            'description' => 'Open the initial diagnostic quiz and final test published by the teacher.',
            'button' => 'Open AI assessments',
            'path' => '/local/aiskillnavigator/pages/assessment.php',
            'cardclass' => 'btn btn-success',
            'blockclass' => 'btn btn-outline-success btn-block mb-2',
        ],
        [
            'section' => 'student',
            'label' => 'AI Tutor',
            'title' => 'AI Tutor',
            'description' => 'Ask questions using course materials or a free topic.',
            'button' => 'Open AI Tutor',
            'path' => '/local/aiskillnavigator/pages/tutor.php',
            'cardclass' => 'btn btn-primary',
            'blockclass' => 'btn btn-outline-primary btn-block mb-2',
        ],
        [
            'section' => 'student',
            'label' => 'Adaptive review',
            'title' => 'Adaptive review',
            'description' => 'Review weak skills detected from previous quiz and test answers.',
            'button' => 'Open adaptive review',
            'path' => '/local/aiskillnavigator/pages/adaptive_review.php',
            'cardclass' => 'btn btn-warning',
            'blockclass' => 'btn btn-outline-warning btn-block mb-2',
        ],
        [
            'section' => 'student',
            'label' => 'AI Quiz',
            'title' => 'AI Quiz',
            'description' => 'Generate an AI micro-quiz from a topic or selected course materials.',
            'button' => 'Open AI Quiz',
            'path' => '/local/aiskillnavigator/pages/quizgenerator.php',
            'cardclass' => 'btn btn-primary',
            'blockclass' => 'btn btn-outline-primary btn-block mb-2',
        ],
        [
            'section' => 'student',
            'label' => 'AI Mind Map',
            'title' => 'AI Mind Map',
            'description' => 'Generate an interactive mind map from a topic.',
            'button' => 'Open AI Mind Map',
            'path' => '/local/aiskillnavigator/pages/mindmapgenerator.php',
            'cardclass' => 'btn btn-primary',
            'blockclass' => 'btn btn-outline-primary btn-block mb-3',
        ],
        [
            'section' => 'teacher',
            'label' => 'Teacher dashboard',
            'title' => 'Teacher dashboard',
            'description' => 'View class performance, student progress and course analytics.',
            'button' => 'Open teacher dashboard',
            'path' => '/local/aiskillnavigator/pages/teacher.php',
            'cardclass' => 'btn btn-info',
            'blockclass' => 'btn btn-outline-secondary btn-block mb-2',
        ],
        [
            'section' => 'teacher',
            'label' => 'Initial/final tests',
            'title' => 'Initial/final tests',
            'description' => 'Create an initial diagnostic quiz and a final test.',
            'button' => 'Open initial/final tests',
            'path' => '/local/aiskillnavigator/pages/teacher_assessments.php',
            'cardclass' => 'btn btn-success',
            'blockclass' => 'btn btn-outline-success btn-block mb-2',
        ],
        [
            'section' => 'teacher',
            'label' => 'Learning-gap analysis',
            'title' => 'Learning-gap analysis',
            'description' => 'Analyze pre-test and final-test attempts.',
            'button' => 'Open learning-gap analysis',
            'path' => '/local/aiskillnavigator/pages/gap_analysis.php',
            'cardclass' => 'btn btn-warning',
            'blockclass' => 'btn btn-outline-warning btn-block mb-2',
        ],
        [
            'section' => 'teacher',
            'label' => 'AI Course Builder',
            'title' => 'AI Course Builder',
            'description' => 'Modify the Moodle course structure using a prompt.',
            'button' => 'Open AI Course Builder',
            'path' => '/local/aiskillnavigator/pages/course_builder.php',
            'cardclass' => 'btn btn-info',
            'blockclass' => 'btn btn-outline-info btn-block mb-2',
        ],
        [
            'section' => 'teacher',
            'label' => 'AI Simulator Finder',
            'title' => 'AI Simulator Finder',
            'description' => 'Suggest a simulator and generate a practical exercise.',
            'button' => 'Open Simulator Finder',
            'path' => '/local/aiskillnavigator/pages/simulator_finder.php',
            'cardclass' => 'btn btn-info',
            'blockclass' => 'btn btn-outline-info btn-block mb-3',
        ],
        [
            'section' => 'knowledge',
            'label' => 'Course materials / RAG',
            'title' => 'Course materials / RAG',
            'description' => 'Manage course materials used by the AI.',
            'button' => 'Open course materials / RAG',
            'path' => '/local/aiskillnavigator/pages/teacher_materials.php',
            'cardclass' => 'btn btn-success',
            'blockclass' => 'btn btn-outline-success btn-block mb-2',
        ],
    ];
}

function local_aiskillnavigator_tool_exists(array $tool): bool {
    global $CFG;
    return !empty($tool['path']) && file_exists($CFG->dirroot . $tool['path']);
}

function local_aiskillnavigator_can_view_tool_section(string $section, context $context, int $courseid): bool {
    if (is_siteadmin()) {
        return true;
    }

    if ($courseid <= SITEID) {
        return false;
    }

    if ($section === 'student') {
        return has_capability('local/aiskillnavigator:viewstudent', $context) || is_enrolled($context);
    }

    if ($section === 'teacher') {
        return has_capability('local/aiskillnavigator:viewteacher', $context) ||
            has_capability('moodle/course:update', $context);
    }

    if ($section === 'knowledge') {
        return has_capability('local/aiskillnavigator:managematerials', $context) ||
            has_capability('moodle/course:update', $context);
    }

    return false;
}

function local_aiskillnavigator_visible_tools(context $context, int $courseid): array {
    $visible = [];

    foreach (local_aiskillnavigator_tool_registry() as $tool) {
        $section = (string)($tool['section'] ?? '');

        if (!local_aiskillnavigator_can_view_tool_section($section, $context, $courseid)) {
            continue;
        }

        if (!local_aiskillnavigator_tool_exists($tool)) {
            continue;
        }

        $visible[] = $tool;
    }

    return $visible;
}

function local_aiskillnavigator_grouped_tools(context $context, int $courseid): array {
    $groups = [
        'student' => [],
        'teacher' => [],
        'knowledge' => [],
    ];

    foreach (local_aiskillnavigator_visible_tools($context, $courseid) as $tool) {
        $section = (string)$tool['section'];
        $groups[$section][] = $tool;
    }

    return $groups;
}
