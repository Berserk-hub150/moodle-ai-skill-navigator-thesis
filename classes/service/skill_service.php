<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class skill_service {

    public function get_student_skill_profile(int $userid): array {
        return [
            'userid' => $userid,
            'skills' => [
                [
                    'name' => 'AI Fundamentals',
                    'score' => 78,
                    'status' => 'Good',
                    'description' => 'The student has a good understanding of basic AI concepts.',
                    'nextaction' => 'Try an applied exercise about AI model evaluation.',
                ],
                [
                    'name' => 'IoT Basics',
                    'score' => 61,
                    'status' => 'Medium',
                    'description' => 'The student understands sensors and connected devices, but should reinforce data flow concepts.',
                    'nextaction' => 'Review the IoT data acquisition module.',
                ],
                [
                    'name' => 'Digital Twin',
                    'score' => 43,
                    'status' => 'Weak',
                    'description' => 'The student needs improvement on virtual models and real-time synchronisation.',
                    'nextaction' => 'Complete a micro-quiz about sensor data and digital model synchronisation.',
                ],
                [
                    'name' => 'Virtual Worlds',
                    'score' => 69,
                    'status' => 'Medium',
                    'description' => 'The student has a good initial understanding of immersive learning environments.',
                    'nextaction' => 'Analyse a training scenario in a virtual smart factory.',
                ],
            ],
            'main_gap' => 'Digital Twin',
        ];
    }

    public function get_teacher_skill_overview(): array {
        return [
            'weakestskills' => [
                [
                    'name' => 'Digital Twin',
                    'average' => 46,
                    'studentsatrisk' => 7,
                    'suggestion' => 'Generate a recovery quiz about real-time synchronisation.',
                ],
                [
                    'name' => 'IoT Sensor Integration',
                    'average' => 54,
                    'studentsatrisk' => 5,
                    'suggestion' => 'Create an exercise on sensor data acquisition.',
                ],
                [
                    'name' => 'AI Model Evaluation',
                    'average' => 59,
                    'studentsatrisk' => 4,
                    'suggestion' => 'Assign a practical activity about accuracy, precision and recall.',
                ],
            ],
            'suggestedactions' => [
                'Generate a recovery quiz for Digital Twin concepts.',
                'Create a Virtual Worlds training scenario about IoT sensors.',
                'Recommend the Digital Twin Architecture resource to students below 50%.',
            ],
        ];
    }

    public function get_score_badge_class(int $score): string {
        if ($score >= 75) {
            return 'badge bg-success';
        }

        if ($score >= 50) {
            return 'badge bg-warning text-dark';
        }

        return 'badge bg-danger';
    }
}