<?php

namespace local_aiskillnavigator\service\embedding;

defined('MOODLE_INTERNAL') || die();

// Turns search results into a compact prompt context.
class rag_context_builder {
    public function build(array $results, int $maxchars = 6000): string {
        $context = '';
        $total = 0;
        $source = 1;

        foreach ($results as $result) {
            $text = trim((string) ($result->chunktext ?? ''));

            if ($text === '') {
                continue;
            }

            $title = trim((string) ($result->title ?? 'Materiale'));
            $score = (string) ($result->similarity ?? 'n/a');
            $block = "FONTE {$source} (materiale: {$title}, rilevanza: {$score})\n{$text}\n\n";
            $length = \core_text::strlen($block);

            if ($total + $length > $maxchars) {
                $remaining = $maxchars - $total;
                if ($remaining > 250) {
                    $context .= \core_text::substr($block, 0, $remaining) . "...\n\n";
                }
                break;
            }

            $context .= $block;
            $total += $length;
            $source++;
        }

        return trim($context);
    }
}
