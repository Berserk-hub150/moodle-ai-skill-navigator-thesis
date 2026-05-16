<?php
// This file is part of Moodle - https://moodle.org/

namespace local_aiskillnavigator\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Strategy interface for AI text generation providers.
 */
interface ai_provider_interface {

    public function generate(string $prompt, int $maxtokens = 1200, string $systemprompt = ''): string;

    public function get_name(): string;
}