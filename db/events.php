<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\course_created',
        'callback' => '\local_aiskillnavigator\observer::course_created',
    ],
];