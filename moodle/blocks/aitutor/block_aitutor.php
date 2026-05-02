<?php
defined('MOODLE_INTERNAL') || die();

class block_aitutor extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_aitutor');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = html_writer::tag(
            'p',
            get_string('intro', 'block_aitutor')
        );
        $this->content->footer = '';

        return $this->content;
    }

    public function applicable_formats() {
        return ['all' => true];
    }
}
