<?php

defined('MOODLE_INTERNAL') || die();

function local_aiskillnavigator_print_inline_styles(): void {
    static $printed = false;

    if ($printed) {
        return;
    }

    $printed = true;

    echo html_writer::tag('style', <<<'CSS'
body.path-local-aiskillnavigator #page,
body[id^="page-local-aiskillnavigator"] #page {
    background: #f6f8fb !important;
}

body.path-local-aiskillnavigator #page-header,
body[id^="page-local-aiskillnavigator"] #page-header,
body.path-local-aiskillnavigator .secondary-navigation,
body[id^="page-local-aiskillnavigator"] .secondary-navigation {
    display: none !important;
}

body.path-local-aiskillnavigator .container-fluid,
body[id^="page-local-aiskillnavigator"] .container-fluid {
    max-width: 1180px !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

body.path-local-aiskillnavigator #region-main h2:first-of-type,
body[id^="page-local-aiskillnavigator"] #region-main h2:first-of-type {
    background: linear-gradient(135deg, #0f6cbf 0%, #2b82d9 55%, #68b3ff 100%);
    color: white !important;
    border-radius: 24px;
    padding: 28px 32px;
    margin-bottom: 18px;
    box-shadow: 0 18px 40px rgba(15, 108, 191, 0.22);
    font-size: 34px;
    font-weight: 850;
    letter-spacing: -0.04em;
}

body.path-local-aiskillnavigator .lead,
body[id^="page-local-aiskillnavigator"] .lead {
    color: #64748b !important;
    font-size: 16px !important;
    line-height: 1.55 !important;
}

body.path-local-aiskillnavigator .card,
body[id^="page-local-aiskillnavigator"] .card {
    border: 1px solid #e5e7eb !important;
    border-radius: 22px !important;
    box-shadow: 0 14px 34px rgba(15, 23, 42, 0.07) !important;
    overflow: hidden;
    background: #ffffff !important;
}

body.path-local-aiskillnavigator .card-body,
body[id^="page-local-aiskillnavigator"] .card-body {
    padding: 24px !important;
}

body.path-local-aiskillnavigator .btn,
body[id^="page-local-aiskillnavigator"] .btn {
    border-radius: 12px !important;
    font-weight: 750 !important;
}

body.path-local-aiskillnavigator .btn-primary,
body[id^="page-local-aiskillnavigator"] .btn-primary {
    background: #0f6cbf !important;
    border-color: #0f6cbf !important;
    box-shadow: 0 10px 20px rgba(15, 108, 191, 0.20);
}

body.path-local-aiskillnavigator input.form-control,
body.path-local-aiskillnavigator textarea.form-control,
body[id^="page-local-aiskillnavigator"] input.form-control,
body[id^="page-local-aiskillnavigator"] textarea.form-control {
    border-radius: 14px !important;
    border: 1px solid #cbd5e1 !important;
    padding: 11px 13px !important;
}

body.path-local-aiskillnavigator select,
body.path-local-aiskillnavigator select.form-control,
body.path-local-aiskillnavigator .custom-select,
body[id^="page-local-aiskillnavigator"] select,
body[id^="page-local-aiskillnavigator"] select.form-control,
body[id^="page-local-aiskillnavigator"] .custom-select {
    min-height: 44px !important;
    height: 44px !important;
    line-height: 1.35 !important;
    padding-top: 8px !important;
    padding-bottom: 8px !important;
    padding-left: 12px !important;
    padding-right: 36px !important;
    border-radius: 14px !important;
    border: 1px solid #cbd5e1 !important;
    background-position: right 12px center !important;
}

body.path-local-aiskillnavigator .alert,
body[id^="page-local-aiskillnavigator"] .alert {
    border-radius: 16px !important;
    border-width: 1px !important;
}

.aisn-source-box {
    margin-top: 12px;
}

.aisn-choice-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
    margin-top: 12px;
    margin-bottom: 16px;
}

.aisn-choice {
    display: block;
    border: 2px solid #e5e7eb;
    background: #f8fafc;
    border-radius: 18px;
    padding: 18px;
    cursor: pointer;
    transition: .15s ease-in-out;
}

.aisn-choice:hover {
    border-color: #0f6cbf;
    background: #eff6ff;
}

.aisn-choice:has(input:checked) {
    border-color: #0f6cbf;
    background: #eff6ff;
    box-shadow: 0 10px 24px rgba(15, 108, 191, .14);
}

.aisn-choice input {
    margin-right: 8px;
}

.aisn-choice-title {
    font-weight: 850;
}

.aisn-choice-text {
    display: block;
    margin-top: 8px;
    color: #64748b;
    font-size: 14px;
}

.aisn-material-panel {
    margin-top: 14px;
}

.aisn-material-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 14px;
    margin-top: 12px;
}

.aisn-material {
    display: block;
    min-height: 145px;
    border: 2px solid #e5e7eb;
    border-radius: 18px;
    padding: 16px;
    background: #fff;
    cursor: pointer;
    transition: .15s ease-in-out;
}

.aisn-material:hover {
    border-color: #0f6cbf;
    transform: translateY(-1px);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
}

.aisn-material:has(input:checked) {
    border-color: #16a34a;
    background: #f0fdf4;
    box-shadow: 0 10px 24px rgba(22, 163, 74, .12);
}

.aisn-material input {
    margin-right: 8px;
}

.aisn-material-title {
    font-weight: 850;
}

.aisn-badge {
    display: inline-block;
    margin-top: 10px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #e0f2fe;
    color: #075985;
    font-size: 12px;
    font-weight: 800;
}

.aisn-excerpt {
    margin-top: 10px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.45;
}

.aisn-empty {
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    padding: 14px 16px;
    border-radius: 16px;
}

.aisn-hidden {
    display: none !important;
}

.aisn-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 14px;
    margin-bottom: 20px;
}

.aisn-stat {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 10px 24px rgba(15, 23, 42, .06);
}

.aisn-stat-value {
    font-size: 30px;
    font-weight: 850;
    color: #0f172a;
}

.aisn-stat-label {
    color: #64748b;
    font-size: 14px;
}

@media (max-width: 760px) {
    .aisn-choice-row {
        grid-template-columns: 1fr;
    }
}
CSS
    );
}