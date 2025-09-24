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
//var_dump($cm->instance);
$rec = $DB->get_record('diplomaproject', ['id' => $cm->instance]);

$context = context_module::instance($cm->id);

$existing = $DB->get_records('diplomaproject_number_of_papers', [
    'diplomaprojectid' => $cm->instance
]);

if (has_capability('mod/diplomaproject:setpapers', $context)) {

    if ($existing && !optional_param('reimport', 0, PARAM_BOOL)) {
        echo html_writer::tag('h3', get_string('importeddata', 'mod_diplomaproject'));
        echo html_writer::start_tag('table', ['class' => 'generaltable boxaligncenter']);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('teachername', 'mod_diplomaproject'));
        echo html_writer::tag('th', get_string('numberofpapers', 'mod_diplomaproject'));
        echo html_writer::end_tag('tr');

        foreach ($existing as $rec) {
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', s($rec->teacher_name));
            echo html_writer::tag('td', s($rec->number_of_papers));
            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('table');

        echo html_writer::start_tag('form', [
            'method' => 'POST',
            'action' => '',
        ]);

        echo html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => 'reimport',
            'value' => 1
        ]);

        echo $OUTPUT->single_button('#', get_string('reimport', 'mod_diplomaproject'), 'get', [
            'data-confirmation' => 'modal',
            'data-confirmation-title-str' => json_encode(['reimport', 'mod_diplomaproject']),
            'data-confirmation-content-str' => json_encode(['confirmreimport', 'mod_diplomaproject']),
            'data-confirmation-yes-button-str' => json_encode(['reimport', 'mod_diplomaproject']),
            'data-submit' => 'form',
        ]);

        echo html_writer::end_tag('form');

    } else {
        if (optional_param('reimport', 0, PARAM_BOOL)) {
            $DB->delete_records('diplomaproject_number_of_papers', [
                'diplomaprojectid' => $cm->instance
            ]);
            echo $OUTPUT->notification(get_string('olddataremoved', 'mod_diplomaproject'), 'notifysuccess');
        }

        $form = new \mod_diplomaproject\form\csv_upload_form(null, ['id' => $cm->id]);

        if ($form->is_cancelled()) {
            redirect(new moodle_url('/mod/diplomaproject/view.php', ['id' => $cm->id]));
        } else if ($data = $form->get_data()) {
            $fs = get_file_storage();
            $draftid = file_get_submitted_draft_itemid('csvfile');

            file_save_draft_area_files(
                $draftid,
                $context->id,
                'mod_diplomaproject',
                'csvfiles',
                0,
                [
                    'subdirs' => 0,
                    'maxfiles' => 1,
                    'accepted_types' => ['.csv', '.xls', '.xlsx'],
                ]
            );

            $files = $fs->get_area_files($context->id, 'mod_diplomaproject', 'csvfiles', 0, '', false);

            $file = null;
            foreach ($files as $f) {
                if (!$f->is_directory()) {
                    $file = $f;
                    break;
                }
            }

            if (!$file) {
                echo $OUTPUT->notification("No file loaded.", 'notifyproblem');
            } else {
                $filename = $file->get_filename();
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $content = $file->get_content();
                $rows = [];

                if ($ext === 'csv') {
                    $delimiter = strpos($content, ';') !== false ? ';' : ',';
                    $handle = fopen('php://memory', 'r+');
                    fwrite($handle, $content);
                    rewind($handle);

                    while (($line = fgetcsv($handle, 0, $delimiter)) !== false) {
                        $rows[] = $line;
                    }
                    fclose($handle);

                } elseif (in_array($ext, ['xls', 'xlsx'])) {
                    require_once($CFG->libdir . '/phpspreadsheet/vendor/autoload.php');
                    $tmp = tmpfile();
                    $path = stream_get_meta_data($tmp)['uri'];
                    fwrite($tmp, $content);

                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
                    $spreadsheet = $reader->load($path);
                    $worksheet = $spreadsheet->getActiveSheet();

                    foreach ($worksheet->toArray() as $row) {
                        $rows[] = $row;
                    }
                    fclose($tmp);
                }

                if (!empty($rows)) {
                    $headers = array_shift($rows);

                    foreach ($rows as $row) {
                        if (count($row) < 2) {
                            continue;
                        }

                        $record = new stdClass();
                        $record->diplomaprojectid = $cm->instance;
                        $record->teacher_name = trim($row[0]);
                        $record->number_of_papers = (int) $row[1];

                        $DB->insert_record('diplomaproject_number_of_papers', $record);
                    }

                    echo $OUTPUT->notification(get_string('csvimported', 'mod_diplomaproject'), 'notifysuccess');
                    redirect(new moodle_url('/mod/diplomaproject/view.php', ['id' => $cm->id]));
                }
            }

        } else {
            echo html_writer::tag('h4', get_string('importdata', 'mod_diplomaproject'));
            $form->display();
        }
    }
}

echo $OUTPUT->footer();
