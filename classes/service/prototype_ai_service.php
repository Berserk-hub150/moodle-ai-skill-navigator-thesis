<?php
namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class prototype_ai_service {

    public function answer_question(string $question): array {
        $question = trim($question);

        if ($question === '') {
            $question = 'What is the relationship between IoT and Digital Twin?';
        }

        return [
            'question' => $question,
            'answer' => 'A Digital Twin is a virtual representation of a physical system. IoT devices provide real-time sensor data that keeps the virtual model aligned with the real object. In a Virtual Worlds training context, this allows students to explore realistic scenarios, detect anomalies and understand how data-driven systems behave.',
            'grounding' => [
                'The answer is based on the course skill map: AI, IoT, Digital Twin and Virtual Worlds.',
                'In the final version, this section will show citations from Moodle course materials retrieved by the RAG pipeline.',
            ],
            'nextsteps' => [
                'Review the Digital Twin Architecture module.',
                'Complete a micro-quiz about IoT data acquisition.',
                'Try a Virtual Worlds scenario about a smart factory.',
            ],
        ];
    }

    public function generate_quiz(string $topic, string $difficulty): array {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin';
        $difficulty = trim($difficulty) !== '' ? trim($difficulty) : 'medium';

        return [
            'topic' => $topic,
            'difficulty' => $difficulty,
            'questions' => [
                [
                    'type' => 'Multiple choice',
                    'question' => 'What is the main purpose of a Digital Twin?',
                    'options' => [
                        'To replace all physical sensors',
                        'To create a virtual model connected to a physical system',
                        'To store static documentation',
                        'To avoid data collection',
                    ],
                    'correct' => 'To create a virtual model connected to a physical system',
                    'explanation' => 'A Digital Twin represents a physical object or process and can be updated through data flows.',
                ],
                [
                    'type' => 'Multiple choice',
                    'question' => 'Which technology is commonly used to feed real-time data into a Digital Twin?',
                    'options' => [
                        'IoT sensors',
                        'Static PDFs only',
                        'Manual paper forms',
                        'Offline spreadsheets only',
                    ],
                    'correct' => 'IoT sensors',
                    'explanation' => 'IoT sensors collect data from the physical environment and send it to digital systems.',
                ],
                [
                    'type' => 'Open question',
                    'question' => 'Explain how a Virtual Worlds scenario can help students understand Digital Twin synchronisation.',
                    'expectedanswer' => 'A good answer should mention simulation, real-time data, interaction, sensor feedback and learning by doing.',
                ],
            ],
        ];
    }

    public function generate_xr_scenario(string $topic, string $environment): array {
        $topic = trim($topic) !== '' ? trim($topic) : 'Digital Twin and IoT';
        $environment = trim($environment) !== '' ? trim($environment) : 'Smart Factory';

        return [
            'title' => $environment . ' Training Scenario: ' . $topic,
            'learningobjective' => 'Understand how IoT data updates a Digital Twin inside an immersive training environment.',
            'environment' => $environment,
            'story' => 'The learner enters a virtual smart factory where a production machine is behaving abnormally. A dashboard shows sensor data from temperature, vibration and throughput sensors. The learner must identify the faulty data stream and restore synchronisation between the physical system and its Digital Twin.',
            'tasks' => [
                'Inspect the virtual machine and identify the available IoT sensors.',
                'Compare real-time sensor values with the Digital Twin dashboard.',
                'Detect which sensor stream is inconsistent.',
                'Apply a correction strategy and validate the updated Digital Twin.',
                'Answer a short reflective quiz about the decision made.',
            ],
            'assessment' => [
                'Correct identification of the faulty sensor.',
                'Correct explanation of the IoT-to-Digital-Twin data flow.',
                'Ability to justify the chosen correction.',
                'Completion of the final reflection quiz.',
            ],
            'extensions' => [
                'Add a second failure mode involving AI-based anomaly detection.',
                'Add collaboration between two learners in the same virtual environment.',
                'Connect the scenario with Moodle quiz results and skill tracking.',
            ],
        ];
    }
}


