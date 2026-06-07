<?php
defined('MOODLE_INTERNAL') || die();

class block_aiskillnavigator extends block_base {
    public function init(): void {
        $this->title = 'AI Skill Navigator';
    }

    public function applicable_formats(): array {
        return ['all' => true];
    }

    public function instance_allow_multiple(): bool {
        return false;
    }

    public function has_config(): bool {
        return false;
    }

    private function link_button(string $label, string $path, int $courseid, string $class): string {
        return html_writer::link(
            new moodle_url($path, ['courseid' => $courseid]),
            s($label),
            ['class' => $class, 'style' => 'display:block;margin-bottom:8px;width:100%;']
        );
    }

    private function section_title(string $title): string {
        return html_writer::tag(
            'div',
            s($title),
            ['style' => 'font-weight:800;font-size:12px;margin:14px 0 8px;text-transform:uppercase;color:#111827;']
        );
    }

    private function can_manage_course(context_course $context): bool {
        return has_capability('moodle/course:update', $context)
            || has_capability('moodle/course:manageactivities', $context)
            || has_capability('local/aiskillnavigator:managematerials', $context)
            || has_capability('local/aiskillnavigator:manageassessments', $context);
    }

    public function get_content() {
        global $COURSE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $courseid = !empty($COURSE->id) ? (int)$COURSE->id : SITEID;
        $context = context_course::instance($courseid);

        $isadmin = is_siteadmin();
        // AISN_BLOCK_ADMIN_ONLY_SEES_BOTH_SIDES_V2
        // Admin vede sia strumenti docente sia strumenti studente nel blocco laterale.
        // Docente vede solo strumenti docente.
        // Studente vede solo strumenti studente.
        $isteacher = $this->can_manage_course($context) || $isadmin;
        $isstudent = $isadmin || (is_enrolled($context, $USER, '', true) && !$this->can_manage_course($context));

        $html = html_writer::start_div('aisn-block-role-based');
        $html .= html_writer::tag('p', 'Course-aware AI tools for students and teachers.', ['style' => 'font-size:13px;color:#475569;']);
        $html .= $this->link_button('Open AI Skill Navigator', '/local/aiskillnavigator/pages/index.php', $courseid, 'btn btn-primary');

        if ($isstudent) {
            $html .= $this->section_title('Student tools');
            $html .= $this->link_button('AI Assessments', '/local/aiskillnavigator/pages/assessment.php', $courseid, 'btn btn-outline-success');
            $html .= $this->link_button('AI Tutor', '/local/aiskillnavigator/pages/tutor.php', $courseid, 'btn btn-outline-primary');
            $html .= $this->link_button('Adaptive review', '/local/aiskillnavigator/pages/adaptive_review.php', $courseid, 'btn btn-outline-warning');
            $html .= $this->link_button('AI Quiz', '/local/aiskillnavigator/pages/quizgenerator.php', $courseid, 'btn btn-outline-primary');
            $html .= $this->link_button('AI Mind Map', '/local/aiskillnavigator/pages/mindmapgenerator.php', $courseid, 'btn btn-outline-primary');
        }

        if ($isteacher) {
            $html .= $this->section_title('Teacher tools');
            $html .= $this->link_button('Teacher dashboard', '/local/aiskillnavigator/pages/teacher.php', $courseid, 'btn btn-outline-secondary');
            $html .= $this->link_button('Tutor analyst', '/local/aiskillnavigator/pages/tutor_analytics.php', $courseid, 'btn btn-outline-info');
            $html .= $this->link_button('Initial/final tests', '/local/aiskillnavigator/pages/teacher_assessments.php', $courseid, 'btn btn-outline-success');
            $html .= $this->link_button('Learning-gap analysis', '/local/aiskillnavigator/pages/gap_analysis.php', $courseid, 'btn btn-outline-warning');
            $html .= $this->link_button('AI Course Builder', '/local/aiskillnavigator/pages/course_builder.php', $courseid, 'btn btn-outline-info');
            $html .= $this->link_button('AI Simulator Finder', '/local/aiskillnavigator/pages/simulator_finder.php', $courseid, 'btn btn-outline-info');

            $html .= $this->section_title('Knowledge base');
            $html .= $this->link_button('Course materials / RAG', '/local/aiskillnavigator/pages/teacher_materials.php', $courseid, 'btn btn-outline-success');
            $html .= html_writer::tag('p', 'RAG-ready AI support for Moodle courses.', ['style' => 'font-size:12px;color:#64748b;margin-top:10px;']);
        } else {
            $html .= html_writer::div('You are not enrolled in this course.', 'alert alert-info');
        }

        $html .= html_writer::end_div();

        $this->content = new stdClass();
        $this->content->text = $html;
        $this->content->footer = '';

        return $this->content;
    }
}