<?php

namespace local_aiskillnavigator\service\prompt;

defined('MOODLE_INTERNAL') || die();
// Formats uploaded Moodle materials for prompt text.
class material_context_builder {
    private text_tools $text;

    public function __construct(text_tools $text) {
        $this->text = $text;
    }

    public function build(array $materials, int $limit): string {
        $context = '';

        foreach ($materials as $index => $material) {
            $content = $this->text->clean($this->read($material, 'content', ''));
            if ($content === '') {
                continue;
            }

            $number = $index + 1;
            $context .= "Fonte {$number}\n"
                . 'Titolo: ' . $this->read($material, 'title', 'Materiale senza titolo') . "\n"
                . 'Tipo: ' . $this->read($material, 'materialtype', 'text') . "\n"
                . 'Testo: ' . $this->text->cut($content, $limit) . "\n\n";
        }

        return $context;
    }

    private function read($material, string $field, string $default): string {
        if (is_array($material) && array_key_exists($field, $material)) {
            return trim((string) $material[$field]);
        }

        if (is_object($material) && isset($material->{$field})) {
            return trim((string) $material->{$field});
        }

        return $default;
    }
}
