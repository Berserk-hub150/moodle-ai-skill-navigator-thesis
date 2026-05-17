<?php

namespace local_aiskillnavigator\service\provider;

defined('MOODLE_INTERNAL') || die();

// Removes simple Markdown fences from model output.
class model_output_cleaner {
    public function clean(string $text): string {
        $text = trim($text);

        if (str_starts_with($text, '```json')) {
            $text = trim(substr($text, 7));
        } else if (str_starts_with($text, '```')) {
            $text = trim(substr($text, 3));
        }

        if (str_ends_with($text, '```')) {
            $text = trim(substr($text, 0, -3));
        }

        return trim($text);
    }
}
