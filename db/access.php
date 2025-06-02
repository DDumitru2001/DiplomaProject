<?php
/**
 * Plugin capabilities
 *
 * * @package     mod_diplomaproject
 * * @copyright   2025 Danica Dumitru danicadumitru15@gmail.com
 * * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'mod/diplomaproject:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ],
    'mod/diplomaproject:addinstance' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities'
    ]
];