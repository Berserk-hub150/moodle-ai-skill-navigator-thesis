<?php
// This file is part of Moodle - https://moodle.org/
//
// AI Skill Navigator - Embedding service for the RAG pipeline.
// Responsibilities: chunking, embedding generation, semantic retrieval and context building.

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

class embedding_service {

    private string $provider;
    private string $endpoint;
    private string $embeddingmodel;
    private string $apikey;

    /** Target chunk size in characters. */
    private const CHUNK_SIZE = 2000;

    /** Overlap between consecutive chunks in characters. */
    private const CHUNK_OVERLAP = 300;

    /** Default number of chunks retrieved for a prompt. */
    private const TOP_K = 5;

    public function __construct() {
        $provider = trim((string) get_config('local_aiskillnavigator', 'provider'));
        $endpoint = trim((string) get_config('local_aiskillnavigator', 'endpoint'));
        $embeddingmodel = trim((string) get_config('local_aiskillnavigator', 'embeddingmodel'));
        $apikey = trim((string) get_config('local_aiskillnavigator', 'apikey'));

        $this->provider = $provider !== '' ? $provider : 'ollama';
        $this->endpoint = $endpoint !== '' ? $endpoint : 'http://host.docker.internal:11434';
        $this->embeddingmodel = $embeddingmodel !== '' ? $embeddingmodel : 'nomic-embed-text';
        $this->apikey = $apikey;
    }

    /**
     * Index a teacher material into RAG chunks.
     *
     * @param int $materialid Material record id.
     * @param int $courseid Moodle course id.
     * @param string $title Material title.
     * @param string $content Extracted material content.
     * @return array{success: bool, chunks: int, message: string}
     */
    public function index_material(int $materialid, int $courseid, string $title, string $content): array {
        global $DB;

        $content = trim($content);

        if ($content === '') {
            return [
                'success' => false,
                'chunks' => 0,
                'message' => 'Empty content, nothing to index.',
            ];
        }

        $DB->delete_records('local_aiskillnav_chunk', ['materialid' => $materialid]);

        $chunks = $this->chunk_text($content);

        if (empty($chunks)) {
            return [
                'success' => false,
                'chunks' => 0,
                'message' => 'No chunks generated from this material.',
            ];
        }

        $indexed = 0;
        $embeddingfailures = 0;
        $now = time();

        foreach ($chunks as $index => $chunktext) {
            $embedding = $this->generate_embedding($chunktext);

            if ($embedding === null) {
                // Store the chunk anyway. Search will fall back to keyword similarity for this chunk.
                $embedding = [];
                $embeddingfailures++;
            }

            $record = new \stdClass();
            $record->materialid = $materialid;
            $record->courseid = $courseid;
            $record->title = $title;
            $record->chunkindex = $index;
            $record->chunktext = $chunktext;
            $record->embedding = json_encode($embedding, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $record->embeddingmodel = $this->embeddingmodel;
            $record->timecreated = $now;

            $DB->insert_record('local_aiskillnav_chunk', $record);
            $indexed++;
        }

        $message = "Indexed {$indexed} chunks from \"{$title}\".";

        if ($embeddingfailures > 0) {
            $message .= " {$embeddingfailures} chunks were saved without embeddings and will use keyword fallback.";
        }

        return [
            'success' => true,
            'chunks' => $indexed,
            'message' => $message,
        ];
    }

    /**
     * Delete all RAG chunks for one material.
     *
     * @param int $materialid
     */
    public function delete_material_chunks(int $materialid): void {
        global $DB;
        $DB->delete_records('local_aiskillnav_chunk', ['materialid' => $materialid]);
    }

    /**
     * Count indexed chunks for a course or a specific material.
     *
     * @param int $courseid
     * @param int $materialid 0 means all course materials.
     * @return int
     */
    public function count_indexed_chunks(int $courseid, int $materialid = 0): int {
        global $DB;

        $conditions = ['courseid' => $courseid];

        if ($materialid > 0) {
            $conditions['materialid'] = $materialid;
        }

        return $DB->count_records('local_aiskillnav_chunk', $conditions);
    }

    /**
     * Semantic search over indexed chunks.
     *
     * Unlike the first draft, this method does not drop all low-similarity results by default:
     * for demos and thesis evaluation it is better to return the best available evidence and
     * display the score, instead of returning nothing too aggressively.
     *
     * @param string $query Student question, quiz focus, mind map focus, or scenario focus.
     * @param int $courseid Moodle course id.
     * @param int $topk Number of chunks to return.
     * @param int $materialid 0 means all materials, >0 restricts to one material.
     * @return array<int, \stdClass>
     */
    public function search(string $query, int $courseid, int $topk = 0, int $materialid = 0): array {
        global $DB;

        $query = trim($query);

        if ($query === '') {
            $query = 'course material concepts learning objectives';
        }

        if ($topk <= 0) {
            $topk = self::TOP_K;
        }

        $conditions = ['courseid' => $courseid];

        if ($materialid > 0) {
            $conditions['materialid'] = $materialid;
        }

        $chunks = $DB->get_records('local_aiskillnav_chunk', $conditions);

        if (empty($chunks)) {
            return [];
        }

        $queryembedding = $this->generate_embedding($query);

        if ($queryembedding === null || empty($queryembedding)) {
            return $this->keyword_fallback_search($query, $chunks, $topk);
        }

        $scored = [];

        foreach ($chunks as $chunk) {
            $chunkembedding = json_decode((string) $chunk->embedding, true);

            if (!is_array($chunkembedding) || empty($chunkembedding)) {
                $similarity = $this->keyword_similarity($query, (string) $chunk->chunktext) * 0.5;
            } else {
                $similarity = $this->cosine_similarity($queryembedding, $chunkembedding);
            }

            $result = new \stdClass();
            $result->id = (int) $chunk->id;
            $result->chunktext = (string) $chunk->chunktext;
            $result->title = (string) $chunk->title;
            $result->materialid = (int) $chunk->materialid;
            $result->chunkindex = (int) $chunk->chunkindex;
            $result->similarity = round($similarity, 4);
            $scored[] = $result;
        }

        usort($scored, function ($a, $b) {
            return $b->similarity <=> $a->similarity;
        });

        return array_slice($scored, 0, $topk);
    }

    /**
     * Build a compact context block for the LLM prompt.
     *
     * @param array<int, \stdClass> $results Search results.
     * @param int $maxchars Maximum context length.
     * @return string
     */
    public function build_context(array $results, int $maxchars = 6000): string {
        if (empty($results)) {
            return '';
        }

        $context = '';
        $totalchars = 0;
        $sourceindex = 1;

        foreach ($results as $result) {
            $title = trim((string) ($result->title ?? 'Materiale'));
            $similarity = (string) ($result->similarity ?? 'n/a');
            $chunktext = trim((string) ($result->chunktext ?? ''));

            if ($chunktext === '') {
                continue;
            }

            $block = "FONTE {$sourceindex} (materiale: {$title}, rilevanza: {$similarity})\n"
                . $chunktext . "\n\n";

            $blocklength = core_text::strlen($block);

            if ($totalchars + $blocklength > $maxchars) {
                $remaining = $maxchars - $totalchars;

                if ($remaining > 250) {
                    $context .= core_text::substr($block, 0, $remaining) . "...\n\n";
                }

                break;
            }

            $context .= $block;
            $totalchars += $blocklength;
            $sourceindex++;
        }

        return trim($context);
    }

    /**
     * Split text into overlapping chunks.
     *
     * @param string $text
     * @return array<int, string>
     */
    private function chunk_text(string $text): array {
        $text = trim($text);
        $text = preg_replace("/\r\n|\r/", "\n", $text);

        if ($text === '') {
            return [];
        }

        if (core_text::strlen($text) <= self::CHUNK_SIZE) {
            return [$text];
        }

        $paragraphs = preg_split('/\n\s*\n/', $text);
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs)));

        if (empty($paragraphs)) {
            return $this->split_long_text($text);
        }

        $chunks = [];
        $currentchunk = '';

        foreach ($paragraphs as $paragraph) {
            if ($currentchunk !== ''
                && core_text::strlen($currentchunk) + core_text::strlen($paragraph) + 2 > self::CHUNK_SIZE) {
                $chunks[] = trim($currentchunk);
                $overlap = core_text::substr($currentchunk, -self::CHUNK_OVERLAP);
                $currentchunk = trim($overlap) . "\n\n" . $paragraph;
            } else {
                $currentchunk .= ($currentchunk !== '' ? "\n\n" : '') . $paragraph;
            }

            if (core_text::strlen($currentchunk) > self::CHUNK_SIZE * 1.5) {
                $subchunks = $this->split_long_text($currentchunk);

                for ($i = 0; $i < count($subchunks) - 1; $i++) {
                    $chunks[] = trim($subchunks[$i]);
                }

                $currentchunk = $subchunks[count($subchunks) - 1] ?? '';
            }
        }

        if (trim($currentchunk) !== '') {
            $chunks[] = trim($currentchunk);
        }

        return $chunks;
    }

    /**
     * Split long paragraph-like text by sentence when paragraph chunking is not enough.
     *
     * @param string $text
     * @return array<int, string>
     */
    private function split_long_text(string $text): array {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text));

        if (!$sentences || count($sentences) <= 1) {
            return $this->split_by_length($text);
        }

        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if ($current !== '' && core_text::strlen($current) + core_text::strlen($sentence) + 1 > self::CHUNK_SIZE) {
                $chunks[] = trim($current);
                $overlap = core_text::substr($current, -self::CHUNK_OVERLAP);
                $current = trim($overlap . ' ' . $sentence);
            } else {
                $current .= ($current !== '' ? ' ' : '') . $sentence;
            }
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return $chunks;
    }

    /**
     * Last-resort length-based split for text with no punctuation.
     *
     * @param string $text
     * @return array<int, string>
     */
    private function split_by_length(string $text): array {
        $chunks = [];
        $length = core_text::strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunks[] = trim(core_text::substr($text, $start, self::CHUNK_SIZE));
            $start += self::CHUNK_SIZE - self::CHUNK_OVERLAP;
        }

        return array_filter($chunks);
    }

    /**
     * @param string $text
     * @return array<int, float>|null
     */
    private function generate_embedding(string $text): ?array {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if ($this->provider === 'ollama') {
            return $this->embedding_ollama($text);
        }

        return $this->embedding_openai_compatible($text);
    }

    /**
     * @param string $text
     * @return array<int, float>|null
     */
    private function embedding_ollama(string $text): ?array {
        $url = rtrim($this->endpoint, '/') . '/api/embeddings';

        $payload = [
            'model' => $this->embeddingmodel,
            'prompt' => $text,
        ];

        $result = $this->post_json($url, $payload, []);

        if (isset($result['embedding']) && is_array($result['embedding'])) {
            return $result['embedding'];
        }

        return null;
    }

    /**
     * @param string $text
     * @return array<int, float>|null
     */
    private function embedding_openai_compatible(string $text): ?array {
        $endpoint = rtrim($this->endpoint, '/');
        $endpoint = preg_replace('#/chat/completions$#', '', $endpoint);
        $endpoint = preg_replace('#/v1$#', '', $endpoint);
        $url = $endpoint . '/v1/embeddings';

        $headers = [];

        if ($this->apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->apikey;
        }

        $payload = [
            'model' => $this->embeddingmodel,
            'input' => $text,
        ];

        $result = $this->post_json($url, $payload, $headers);

        if (isset($result['data'][0]['embedding']) && is_array($result['data'][0]['embedding'])) {
            return $result['data'][0]['embedding'];
        }

        return null;
    }

    /**
     * @param array<int, float> $a
     * @param array<int, float> $b
     * @return float
     */
    private function cosine_similarity(array $a, array $b): float {
        $dimensions = min(count($a), count($b));

        if ($dimensions === 0) {
            return 0.0;
        }

        $dotproduct = 0.0;
        $norma = 0.0;
        $normb = 0.0;

        for ($i = 0; $i < $dimensions; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dotproduct += $va * $vb;
            $norma += $va * $va;
            $normb += $vb * $vb;
        }

        $denominator = sqrt($norma) * sqrt($normb);

        if ($denominator == 0.0) {
            return 0.0;
        }

        return $dotproduct / $denominator;
    }

    private function keyword_similarity(string $query, string $text): float {
        $querywords = $this->extract_words($query);
        $textwords = $this->extract_words($text);

        if (empty($querywords) || empty($textwords)) {
            return 0.0;
        }

        $intersection = count(array_intersect($querywords, $textwords));
        $union = count(array_unique(array_merge($querywords, $textwords)));

        return $union > 0 ? ($intersection / $union) : 0.0;
    }

    /**
     * @param string $query
     * @param array<int, \stdClass> $chunks
     * @param int $topk
     * @return array<int, \stdClass>
     */
    private function keyword_fallback_search(string $query, array $chunks, int $topk): array {
        $scored = [];

        foreach ($chunks as $chunk) {
            $similarity = $this->keyword_similarity($query, (string) $chunk->chunktext);

            $result = new \stdClass();
            $result->id = (int) $chunk->id;
            $result->chunktext = (string) $chunk->chunktext;
            $result->title = (string) $chunk->title;
            $result->materialid = (int) $chunk->materialid;
            $result->chunkindex = (int) $chunk->chunkindex;
            $result->similarity = round($similarity, 4);
            $scored[] = $result;
        }

        usort($scored, function ($a, $b) {
            return $b->similarity <=> $a->similarity;
        });

        return array_slice($scored, 0, $topk);
    }

    /**
     * @param string $text
     * @return array<int, string>
     */
    private function extract_words(string $text): array {
        $text = core_text::strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', (string) $text);

        return array_values(array_filter($words, function ($word) {
            return core_text::strlen($word) > 2;
        }));
    }

    /**
     * @param string $url
     * @param array $payload
     * @param array<int, string> $extraheaders
     * @return array|null
     */
    private function post_json(string $url, array $payload, array $extraheaders): ?array {
        $curl = curl_init($url);

        if ($curl === false) {
            return null;
        }

        $jsonpayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonpayload === false) {
            curl_close($curl);
            return null;
        }

        $headers = array_merge(['Content-Type: application/json'], $extraheaders);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonpayload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_USERAGENT => 'Moodle local_aiskillnavigator RAG',
        ]);

        $raw = curl_exec($curl);

        if ($raw === false) {
            curl_close($curl);
            return null;
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($status >= 400) {
            return null;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
