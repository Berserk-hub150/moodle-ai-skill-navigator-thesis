<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Service responsible for managing skill profiles and course skill analytics.
 *
 * @package   local_aiskillnavigator
 */
class skill_service {

    /**
     * Returns a mock skill profile for a student.
     *
     * In the next versions this data will be calculated from Moodle activities,
     * quizzes, completions and AI-based analysis.
     *
     * @param int $userid The Moodle user id.
     * @return array
     */
    public function get_student_skill_profile(int $userid): array {
        return [
            'userid' => $userid,
            'skills' => [
                [
                    'name' => 'AI Fundamentals',
                    'score' => 78,
                    'status' => 'Good',
                    'description' => 'Basic understanding of artificial intelligence concepts.',
                ],
                [
                    'name' => 'IoT Basics',
                    'score' => 61,
                    'status' => 'Medium',
                    'description' => 'Understanding of sensors, connected devices and data flows.',
                ],
                [
                    'name' => 'Digital Twin',
                    'score' => 43,
                    'status' => 'Weak',
                    'description' => 'Needs improvement on virtual models and real-time synchronisation.',
                ],
                [
                    'name' => 'Virtual Worlds',
                    'score' => 69,
                    'status' => 'Medium',
                    'description' => 'Good initial understanding of immersive environments.',
                ],
            ],
            'main_gap' => 'Digital Twin',
            'recommendation' => 'Review the Digital Twin module, then complete a micro-quiz about IoT sensors and virtual model synchronisation.',
        ];
    }

    /**
     * Returns a mock overview for teachers.
     *
     * In the next versions this data will be calculated by aggregating
     * students results, activity completion and quiz attempts.
     *
     * @return array
     */
    public function get_teacher_skill_overview(): array {
        return [
            'weakestskills' => [
                [
                    'name' => 'Digital Twin',
                    'average' => 46,
                    'studentsatrisk' => 7,
                ],
                [
                    'name' => 'IoT Sensor Integration',
                    'average' => 54,
                    'studentsatrisk' => 5,
                ],
                [
                    'name' => 'AI Model Evaluation',
                    'average' => 59,
                    'studentsatrisk' => 4,
                ],
            ],
            'suggestedactions' => [
                'Generate a recovery quiz for Digital Twin concepts.',
                'Create a Virtual Worlds training scenario about IoT sensors.',
                'Recommend the Digital Twin Architecture resource to students below 50%.',
            ],
        ];
    }
}