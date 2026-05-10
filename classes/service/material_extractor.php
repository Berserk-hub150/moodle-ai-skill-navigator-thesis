<?php
namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class material_extractor {

    public static function extract_from_upload(array $file): array {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return [
                'success' => false,
                'content' => '',
                'message' => 'No valid file uploaded.',
                'type' => 'unknown',
            ];
        }

        $filename = $file['name'] ?? '';
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'pptx') {
            return self::extract_pptx($file['tmp_name']);
        }

        if ($extension === 'txt') {
            $content = file_get_contents($file['tmp_name']);

            if ($content === false || trim($content) === '') {
                return [
                    'success' => false,
                    'content' => '',
                    'message' => 'The TXT file is empty or unreadable.',
                    'type' => 'text',
                ];
            }

            return [
                'success' => true,
                'content' => self::normalise_text($content),
                'message' => 'TXT file extracted successfully.',
                'type' => 'text',
            ];
        }

        return [
            'success' => false,
            'content' => '',
            'message' => 'Unsupported file type. Upload a .pptx or .txt file.',
            'type' => 'unknown',
        ];
    }

    private static function extract_pptx(string $filepath): array {
        if (!class_exists('\ZipArchive')) {
            return [
                'success' => false,
                'content' => '',
                'message' => 'PHP ZipArchive is not available. PPTX extraction cannot run.',
                'type' => 'slide',
            ];
        }

        $zip = new \ZipArchive();
        $opened = $zip->open($filepath);

        if ($opened !== true) {
            return [
                'success' => false,
                'content' => '',
                'message' => 'Unable to open PPTX file.',
                'type' => 'slide',
            ];
        }

        $slides = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            if (!preg_match('#^ppt/slides/slide([0-9]+)\.xml$#', $entry, $matches)) {
                continue;
            }

            $slideNumber = (int) $matches[1];
            $xml = $zip->getFromName($entry);

            if ($xml === false || trim($xml) === '') {
                continue;
            }

            $text = self::extract_text_from_slide_xml($xml);

            if ($text !== '') {
                $slides[$slideNumber] = "Slide {$slideNumber}:\n" . $text;
            }
        }

        $zip->close();

        if (empty($slides)) {
            return [
                'success' => false,
                'content' => '',
                'message' => 'No readable text found in the PPTX slides.',
                'type' => 'slide',
            ];
        }

        ksort($slides);

        return [
            'success' => true,
            'content' => self::normalise_text(implode("\n\n", $slides)),
            'message' => 'PPTX slides extracted successfully.',
            'type' => 'slide',
        ];
    }

    private static function extract_text_from_slide_xml(string $xml): string {
        $previous = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();

        if (!$dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return '';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

        $nodes = $xpath->query('//a:t');
        $parts = [];

        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $value = trim($node->textContent);

                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return self::normalise_text(implode("\n", $parts));
    }

    private static function normalise_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace("/[ \t]+/", " ", $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}