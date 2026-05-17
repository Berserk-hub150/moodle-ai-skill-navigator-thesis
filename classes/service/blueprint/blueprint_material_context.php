<?php

namespace local_aiskillnavigator\service\blueprint;

defined('MOODLE_INTERNAL') || die();

// Formats course materials for XR blueprint prompts.
class blueprint_material_context {
    public function build(array $materials, int $limit): string {
        $context = '';

        foreach ($materials as $index => $material) {
            $content = trim((string) ($material->content ?? ''));
            $content = trim((string) preg_replace('/\s+/u', ' ', $content));
            if ($content === '') { continue; }

            if (function_exists('mb_strlen') && mb_strlen($content) > $limit) {
                $content = mb_substr($content, 0, $limit) . '...';
            } else if (strlen($content) > $limit) {
                $content = substr($content, 0, $limit) . '...';
            }

            $context .= 'FONTE ' . ($index + 1) . "\n"
                . 'Titolo: ' . trim((string) ($material->title ?? 'Materiale senza titolo')) . "\n"
                . 'Tipo: ' . trim((string) ($material->materialtype ?? 'text')) . "\n"
                . "Contenuto: {$content}\n\n";
        }

        return $context;
    }
}
