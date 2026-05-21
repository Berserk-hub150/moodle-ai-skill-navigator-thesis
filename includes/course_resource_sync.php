<?php

defined('MOODLE_INTERNAL') || die();

if (!function_exists('local_aiskillnavigator_sync_course_resources')) {
    function local_aiskillnavigator_sync_course_resources(int $courseid, int $userid = 0, bool $force = false): array {
        global $DB, $USER;

        if ($courseid <= 1) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $dbman = $DB->get_manager();

        if (!$dbman->table_exists(new xmldb_table('local_aiskillnav_material'))) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        if ($userid <= 0 && !empty($USER) && !empty($USER->id)) {
            $userid = (int) $USER->id;
        }

        $documents = local_aiskillnavigator_collect_course_resource_documents($courseid);
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $changedids = [];

        foreach ($documents as $doc) {
            $content = trim((string) ($doc['content'] ?? ''));

            if ($content === '') {
                $skipped++;
                continue;
            }

            $title = trim((string) ($doc['title'] ?? 'Course material'));
            $title = core_text::substr($title, 0, 230);

            $sourcetitle = '[Course #' . $courseid . ' / cm #' . (int) $doc['cmid'] . '] ' . $title;
            $sourcetitle = core_text::substr($sourcetitle, 0, 255);

            $existing = $DB->get_record('local_aiskillnav_material', [
                'courseid' => $courseid,
                'title' => $sourcetitle,
                'materialtype' => 'course_resource',
            ]);

            if ($existing) {
                if ((string) $existing->content !== $content || $force) {
                    $existing->userid = $userid;
                    $existing->content = $content;
                    $existing->timemodified = time();

                    $DB->update_record('local_aiskillnav_material', $existing);
                    $updated++;
                    $changedids[] = (int) $existing->id;
                } else {
                    $skipped++;
                }

                continue;
            }

            $record = new stdClass();
            $record->courseid = $courseid;
            $record->userid = $userid;
            $record->title = $sourcetitle;
            $record->materialtype = 'course_resource';
            $record->content = $content;
            $record->timecreated = time();
            $record->timemodified = time();

            $newid = $DB->insert_record('local_aiskillnav_material', $record);
            $created++;
            $changedids[] = (int) $newid;
        }

        local_aiskillnavigator_try_index_synced_materials($courseid, $changedids);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }
}

if (!function_exists('local_aiskillnavigator_collect_course_resource_documents')) {
    function local_aiskillnavigator_collect_course_resource_documents(int $courseid): array {
        global $DB;

        $documents = [];
        $modinfo = get_fast_modinfo($courseid);

        foreach ($modinfo->cms as $cm) {
            if (empty($cm->id) || empty($cm->modname)) {
                continue;
            }

            if (!$cm->visible) {
                continue;
            }

            $cmcontext = context_module::instance($cm->id, IGNORE_MISSING);

            if (!$cmcontext) {
                continue;
            }

            $title = trim((string) $cm->name);
            $pieces = [];

            if ($cm->modname === 'resource') {
                $pieces[] = local_aiskillnavigator_extract_files_from_area($cmcontext->id, 'mod_resource', 'content');
            }

            if ($cm->modname === 'folder') {
                $pieces[] = local_aiskillnavigator_extract_files_from_area($cmcontext->id, 'mod_folder', 'content');
            }

            if ($cm->modname === 'page') {
                $page = $DB->get_record('page', ['id' => $cm->instance]);

                if ($page) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string) $page->intro);
                    $pieces[] = local_aiskillnavigator_clean_html_text((string) $page->content);
                }
            }

            if ($cm->modname === 'label') {
                $label = $DB->get_record('label', ['id' => $cm->instance]);

                if ($label) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string) $label->intro);
                }
            }

            if ($cm->modname === 'url') {
                $url = $DB->get_record('url', ['id' => $cm->instance]);

                if ($url) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string) $url->intro);
                    $pieces[] = 'External URL: ' . (string) $url->externalurl;
                }
            }

            if ($cm->modname === 'book') {
                $book = $DB->get_record('book', ['id' => $cm->instance]);

                if ($book) {
                    $pieces[] = local_aiskillnavigator_clean_html_text((string) $book->intro);
                }

                $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance, 'hidden' => 0], 'pagenum ASC');

                foreach ($chapters as $chapter) {
                    $pieces[] = 'Chapter: ' . (string) $chapter->title . "\n" . local_aiskillnavigator_clean_html_text((string) $chapter->content);
                }
            }

            $content = trim(implode("\n\n", array_filter(array_map('trim', $pieces))));

            if ($content === '') {
                continue;
            }

            $documents[] = [
                'cmid' => (int) $cm->id,
                'modname' => (string) $cm->modname,
                'title' => $title !== '' ? $title : ('Course module ' . $cm->id),
                'content' => $content,
            ];
        }

        return $documents;
    }
}

if (!function_exists('local_aiskillnavigator_extract_files_from_area')) {
    function local_aiskillnavigator_extract_files_from_area(int $contextid, string $component, string $filearea): string {
        $fs = get_file_storage();
        $files = $fs->get_area_files($contextid, $component, $filearea, false, 'filename', false);

        $pieces = [];

        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $filename = $file->get_filename();
            $text = local_aiskillnavigator_extract_stored_file_text($file);

            if (trim($text) === '') {
                continue;
            }

            $pieces[] = "File: " . $filename . "\n" . $text;
        }

        return trim(implode("\n\n", $pieces));
    }
}

if (!function_exists('local_aiskillnavigator_extract_stored_file_text')) {
    function local_aiskillnavigator_extract_stored_file_text(stored_file $file): string {
        $filename = strtolower($file->get_filename());
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $rawtextExtensions = [
            'txt', 'md', 'csv', 'json', 'xml', 'html', 'htm',
            'php', 'js', 'css', 'sql', 'cs', 'java', 'py', 'cpp', 'c',
        ];

        if (in_array($extension, $rawtextExtensions, true)) {
            return local_aiskillnavigator_limit_material_text((string) $file->get_content());
        }

        if ($extension === 'docx') {
            return local_aiskillnavigator_limit_material_text(local_aiskillnavigator_extract_docx_text($file));
        }

        if ($extension === 'pptx') {
            return local_aiskillnavigator_limit_material_text(local_aiskillnavigator_extract_pptx_text($file));
        }

        if ($extension === 'pdf') {
            return local_aiskillnavigator_limit_material_text(local_aiskillnavigator_extract_pdf_text_if_possible($file));
        }

        return '';
    }
}

if (!function_exists('local_aiskillnavigator_extract_docx_text')) {
    function local_aiskillnavigator_extract_docx_text(stored_file $file): string {
        if (!class_exists('ZipArchive')) {
            return '';
        }

        $tmpdir = make_temp_directory('local_aiskillnavigator/course_import');
        $tmppath = $tmpdir . '/' . uniqid('docx_', true) . '.docx';
        $file->copy_content_to($tmppath);

        $zip = new ZipArchive();
        $text = '';

        if ($zip->open($tmppath) === true) {
            $xml = $zip->getFromName('word/document.xml');

            if ($xml !== false) {
                $xml = preg_replace('/<\/w:p>/', "\n", $xml);
                $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }

            $zip->close();
        }

        @unlink($tmppath);

        return trim($text);
    }
}

if (!function_exists('local_aiskillnavigator_extract_pptx_text')) {
    function local_aiskillnavigator_extract_pptx_text(stored_file $file): string {
        if (!class_exists('ZipArchive')) {
            return '';
        }

        $tmpdir = make_temp_directory('local_aiskillnavigator/course_import');
        $tmppath = $tmpdir . '/' . uniqid('pptx_', true) . '.pptx';
        $file->copy_content_to($tmppath);

        $zip = new ZipArchive();
        $text = [];

        if ($zip->open($tmppath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);

                if (!preg_match('#^ppt/slides/slide[0-9]+\.xml$#', $name)) {
                    continue;
                }

                $xml = $zip->getFromName($name);

                if ($xml === false) {
                    continue;
                }

                if (preg_match_all('/<a:t>(.*?)<\/a:t>/s', $xml, $matches)) {
                    foreach ($matches[1] as $match) {
                        $text[] = html_entity_decode($match, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }
                }
            }

            $zip->close();
        }

        @unlink($tmppath);

        return trim(implode("\n", $text));
    }
}

if (!function_exists('local_aiskillnavigator_extract_pdf_text_if_possible')) {
    function local_aiskillnavigator_extract_pdf_text_if_possible(stored_file $file): string {
        $pdftotext = trim((string) @shell_exec('command -v pdftotext 2>/dev/null'));

        if ($pdftotext === '') {
            return '[PDF file detected: automatic text extraction requires pdftotext on the server. Filename: ' . $file->get_filename() . ']';
        }

        $tmpdir = make_temp_directory('local_aiskillnavigator/course_import');
        $tmppath = $tmpdir . '/' . uniqid('pdf_', true) . '.pdf';
        $file->copy_content_to($tmppath);

        $command = escapeshellcmd($pdftotext) . ' -layout ' . escapeshellarg($tmppath) . ' - 2>/dev/null';
        $text = (string) @shell_exec($command);

        @unlink($tmppath);

        return trim($text);
    }
}

if (!function_exists('local_aiskillnavigator_clean_html_text')) {
    function local_aiskillnavigator_clean_html_text(string $html): string {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }
}

if (!function_exists('local_aiskillnavigator_limit_material_text')) {
    function local_aiskillnavigator_limit_material_text(string $text): string {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (core_text::strlen($text) > 30000) {
            $text = core_text::substr($text, 0, 30000) . "\n[Content truncated for indexing]";
        }

        return trim($text);
    }
}

if (!function_exists('local_aiskillnavigator_try_index_synced_materials')) {
    function local_aiskillnavigator_try_index_synced_materials(int $courseid, array $materialids): void {
        if (empty($materialids)) {
            return;
        }

        if (!class_exists('\local_aiskillnavigator\service\embedding_service')) {
            return;
        }

        try {
            $service = new \local_aiskillnavigator\service\embedding_service();

            foreach ($materialids as $materialid) {
                if (method_exists($service, 'index_material')) {
                    $service->index_material((int) $materialid);
                } else if (method_exists($service, 'index_material_by_id')) {
                    $service->index_material_by_id((int) $materialid);
                } else if (method_exists($service, 'index_teacher_material')) {
                    $service->index_teacher_material((int) $materialid);
                }
            }
        } catch (Throwable $e) {
            debugging('AI Skill Navigator course material auto-index skipped: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}