<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/moodle_api.php';

class TestMoodleAPI extends MoodleAPI {
    public function getCoursesByField() {
        return $this->call('core_course_get_courses_by_field', []);
    }
}

try {
    $moodle = new TestMoodleAPI($pdo);
    $res = $moodle->getCoursesByField();
    echo "SUCCESS calling core_course_get_courses_by_field:\n";
    print_r($res);
} catch (Exception $e) {
    echo "ERROR calling core_course_get_courses_by_field: " . $e->getMessage() . "\n";
}
