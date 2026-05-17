<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

foreach (glob(__DIR__ . '/material/*.php') as $file) {
    require_once($file);
}

// Routes uploaded files to the right extractor.
class material_extractor {
    public static function extract_from_upload(array $file): array {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'content' => '', 'message' => 'No valid file uploaded.', 'type' => 'unknown'];
        }

        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if ($extension === 'pptx') {
            return (new material\pptx_extractor())->extract($file['tmp_name']);
        }

        if ($extension === 'txt') {
            return (new material\txt_extractor())->extract($file['tmp_name']);
        }

        return ['success' => false, 'content' => '', 'message' => 'Unsupported file type. Upload a .pptx or .txt file.', 'type' => 'unknown'];
    }
}
