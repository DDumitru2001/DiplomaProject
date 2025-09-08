<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Prints an instance of mod_diplomaproject.
 *
 * @package     mod_diplomaproject
 * @copyright   2025 Danica Dumitru danicadumitru15@gmail.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__ . '/classes/form/csv_upload_form.php');
// Course module id.
$id = optional_param('id', 0, PARAM_INT);

// Activity instance id.
//$d = optional_param('d', 0, PARAM_INT);

if ($id) {
    $cm = get_coursemodule_from_id('diplomaproject', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $moduleinstance = $DB->get_record('diplomaproject', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
//    $moduleinstance = $DB->get_record('diplomaproject', ['id' => $d], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $moduleinstance->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('diplomaproject', $moduleinstance->id, $course->id, false, MUST_EXIST);
}
require_login($course, true, $cm);

$modulecontext = context_module::instance($cm->id);

$event = \mod_diplomaproject\event\course_module_viewed::create([
    'objectid' => $moduleinstance->id,
    'context' => $modulecontext,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('diplomaproject', $moduleinstance);
$event->trigger();

$PAGE->set_url('/mod/diplomaproject/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);

echo $OUTPUT->header();
if (optional_param('csv', '', PARAM_ALPHA) === 'imported') {
    echo $OUTPUT->notification(get_string('csvimported', 'mod_diplomaproject'), 'notifysuccess');
}

//Context here
//TODO solve this issue for editing-teacher POV https://github.com/DDumitru2001/DiplomaProject/issues/5
var_dump($cm->instance);
$rec = $DB->get_record('diplomaproject', ['id' => $cm->instance]);

// Instanțierea formularului cu id-ul modulului, pentru a fi păstrat după submit.
$form = new \mod_diplomaproject\form\csv_upload_form(null, ['id' => $cm->id]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/diplomaproject/view.php', ['id' => $cm->id]));
} else if ($data = $form->get_data()) {
    // Recuperează course module și instanța după submit (dacă e nevoie)
    $cm = get_coursemodule_from_id('diplomaproject', $data->id, 0, false, MUST_EXIST);
    $rec = $DB->get_record('diplomaproject', ['id' => $cm->instance]);

    $fs = get_file_storage();
    $context = context_module::instance($cm->id);

    // Salvează fișierul din zona draft în zona modulului
    $draftid = file_get_submitted_draft_itemid('csvfile');
    file_save_draft_area_files(
        $draftid,
        $context->id,
        'mod_diplomaproject',
        'csvfiles',  // zona ta custom
        0,
        [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['.csv'],
        ]
    );

    // Recuperează fișierele din zona modulului
    $files = $fs->get_area_files($context->id, 'mod_diplomaproject', 'csvfiles', 0, '', false);

    if (empty($files)) {
        echo $OUTPUT->notification("⚠️ Nu s-a găsit niciun fișier în zona modulului.", 'notifyproblem');
    } else {
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }

            $content = $file->get_content();
            $rows = explode(PHP_EOL, $content);
            $headers = str_getcsv(array_shift($rows));

            echo html_writer::tag('h3', 'Date CSV importate:');

            echo html_writer::start_tag('table', ['border' => 1, 'class' => 'generaltable']);
            echo html_writer::start_tag('tr');
            foreach ($headers as $header) {
                echo html_writer::tag('th', s($header));
            }
            echo html_writer::end_tag('tr');

            foreach ($rows as $row) {
                if (trim($row) === '') {
                    continue;
                }
                $values = str_getcsv($row);
                if (count($headers) === count($values)) {
                    echo html_writer::start_tag('tr');
                    foreach ($values as $value) {
                        echo html_writer::tag('td', s($value));
                    }
                    echo html_writer::end_tag('tr');
                }
            }
            echo html_writer::end_tag('table');
        }
    }

    echo $OUTPUT->notification(get_string('csvimported', 'mod_diplomaproject'), 'notifysuccess');
}

// Afișează formularul mereu
$form->display();





echo $OUTPUT->footer();
