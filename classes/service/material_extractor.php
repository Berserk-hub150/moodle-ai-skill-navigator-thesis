<?php

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/aiskillnavigator/includes/pdf_text_extractor.php');

class material_extractor {
    public static function extract_from_upload(array $file): array {
        $path = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? 'uploaded-file');

        if ($path === '' || !file_exists($path) || !is_readable($path)) {
            return ['success' => false, 'content' => '', 'message' => 'No valid readable file uploaded.', 'type' => 'unknown'];
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $textlike = [
            'txt', 'md', 'csv', 'json', 'xml', 'html', 'htm',
            'php', 'js', 'css', 'sql', 'cs', 'java', 'py', 'cpp', 'c', 'ts'
        ];

        if (in_array($extension, $textlike, true)) {
            $content = file_get_contents($path);

            if ($content === false || trim($content) === '') {
                return ['success' => false, 'content' => '', 'message' => 'The file is empty or unreadable.', 'type' => 'text'];
            }

            return [
                'success' => true,
                'content' => self::limit(self::clean($content)),
                'message' => strtoupper($extension) . ' file extracted successfully.',
                'type' => 'text',
            ];
        }

        if ($extension === 'pptx') {
            return self::extract_pptx($path);
        }

        if ($extension === 'docx') {
            return self::extract_docx($path);
        }

        if ($extension === 'pdf') {
            return self::extract_pdf($path, $name);
        }

        return [
            'success' => false,
            'content' => '',
            'message' => 'Unsupported file type. Supported: txt, md, csv, json, xml, html, code files, pptx, docx, pdf with pdftotext.',
            'type' => 'unknown',
        ];
    }

    private static function extract_pptx(string $path): array {
        if (!class_exists('\ZipArchive')) {
            return ['success' => false, 'content' => '', 'message' => 'ZipArchive PHP extension missing, cannot read PPTX.', 'type' => 'pptx'];
        }

        $zip = new \ZipArchive();
        $parts = [];

        if ($zip->open($path) !== true) {
            return ['success' => false, 'content' => '', 'message' => 'Cannot open PPTX file.', 'type' => 'pptx'];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            if (!preg_match('#^ppt/slides/slide[0-9]+\.xml$#', $filename)) {
                continue;
            }

            $xml = $zip->getFromName($filename);

            if ($xml === false) {
                continue;
            }

            if (preg_match_all('/<a:t>(.*?)<\/a:t>/s', $xml, $matches)) {
                foreach ($matches[1] as $match) {
                    $parts[] = html_entity_decode($match, ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
            }
        }

        $zip->close();

        $text = self::limit(self::clean(implode("\n", $parts)));

        return $text !== ''
            ? ['success' => true, 'content' => $text, 'message' => 'PPTX file extracted successfully.', 'type' => 'pptx']
            : ['success' => false, 'content' => '', 'message' => 'No readable text found in PPTX.', 'type' => 'pptx'];
    }

    private static function extract_docx(string $path): array {
        if (!class_exists('\ZipArchive')) {
            return ['success' => false, 'content' => '', 'message' => 'ZipArchive PHP extension missing, cannot read DOCX.', 'type' => 'docx'];
        }

        $zip = new \ZipArchive();
        $text = '';

        if ($zip->open($path) !== true) {
            return ['success' => false, 'content' => '', 'message' => 'Cannot open DOCX file.', 'type' => 'docx'];
        }

        $xml = $zip->getFromName('word/document.xml');

        if ($xml !== false) {
            $xml = preg_replace('/<\/w:p>/', "\n", $xml);
            $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $zip->close();

        $text = self::limit(self::clean($text));

        return $text !== ''
            ? ['success' => true, 'content' => $text, 'message' => 'DOCX file extracted successfully.', 'type' => 'docx']
            : ['success' => false, 'content' => '', 'message' => 'No readable text found in DOCX.', 'type' => 'docx'];
    }

    private static function extract_pdf(string $path, string $name): array {
        $text = \local_aiskillnavigator_extract_pdf_text_from_path($path, $name);
        $text = self::limit(self::clean($text));

        if ($text === '') {
            return [
                'success' => false,
                'content' => '',
                'message' => 'No readable text found in PDF. If it is scanned, check OCR quality or try a clearer scan.',
                'type' => 'pdf',
            ];
        }

        return [
            'success' => true,
            'content' => $text,
            'message' => 'PDF extracted successfully. Text layer and OCR fallback are supported.',
            'type' => 'pdf',
        ];
    }


    private static function clean(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim((string)$text);
    }

    private static function limit(string $text, int $limit = 30000): string {
        if (\core_text::strlen($text) > $limit) {
            return \core_text::substr($text, 0, $limit) . "\n[Content truncated for indexing]";
        }

        return $text;
    }
}