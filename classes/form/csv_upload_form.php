<?php

namespace mod_diplomaproject\form;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class csv_upload_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('filepicker', 'csvfile', get_string('uploadcsv', 'mod_diplomaproject'), null, [
            'accepted_types' => ['.csv'],
            'maxbytes' => 0,
        ]);

        $mform->addRule('csvfile', null, 'required', null, 'client');

        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}