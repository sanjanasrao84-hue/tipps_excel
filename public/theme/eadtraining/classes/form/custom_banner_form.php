<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace theme_eadtraining\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("{$CFG->libdir}/formslib.php");

use theme_eadtraining\section_manager;

/**
 * Create/edit form of a custom homepage banner.
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_banner_form extends \moodleform {

    /**
     * Form definition.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        // Content.
        $mform->addElement('header', 'contentheader', get_string('banners_content', 'theme_eadtraining'));

        $mform->addElement('text', 'name', get_string('banners_name', 'theme_eadtraining'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'banners_name', 'theme_eadtraining');

        $mform->addElement('text', 'title', get_string('banners_title', 'theme_eadtraining'), ['size' => 50]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'subtitle', get_string('banners_subtitle', 'theme_eadtraining'), ['size' => 50]);
        $mform->setType('subtitle', PARAM_TEXT);
        $mform->addRule('subtitle', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'description_editor', get_string('banners_description', 'theme_eadtraining'),
            ['rows' => 6], ['maxfiles' => 0, 'context' => \context_system::instance()]);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('text', 'buttontext', get_string('banners_buttontext', 'theme_eadtraining'), ['size' => 30]);
        $mform->setType('buttontext', PARAM_TEXT);
        $mform->addRule('buttontext', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'buttonurl', get_string('banners_buttonurl', 'theme_eadtraining'), ['size' => 50]);
        $mform->setType('buttonurl', PARAM_URL);
        $mform->addHelpButton('buttonurl', 'banners_buttonurl', 'theme_eadtraining');

        // Appearance.
        $mform->addElement('header', 'appearanceheader', get_string('banners_appearance', 'theme_eadtraining'));
        $mform->setExpanded('appearanceheader');

        $mform->addElement('filemanager', 'backgroundimage', get_string('banners_backgroundimage', 'theme_eadtraining'),
            null, section_manager::file_options());
        $mform->addHelpButton('backgroundimage', 'banners_backgroundimage', 'theme_eadtraining');

        $positions = [];
        foreach (section_manager::POSITIONS as $position) {
            $positions[$position] = get_string("banners_position_{$position}", 'theme_eadtraining');
        }
        $mform->addElement('select', 'textposition', get_string('banners_textposition', 'theme_eadtraining'), $positions);
        $mform->addElement('select', 'textalign', get_string('banners_textalign', 'theme_eadtraining'), $positions);

        $verticalpositions = [];
        foreach (section_manager::VERTICALPOSITIONS as $position) {
            $verticalpositions[$position] = get_string("banners_position_{$position}", 'theme_eadtraining');
        }
        $mform->addElement('select', 'verticalposition', get_string('banners_verticalposition', 'theme_eadtraining'),
            $verticalpositions);

        $mform->addElement('text', 'textcolor', get_string('banners_textcolor', 'theme_eadtraining'), ['size' => 10]);
        $mform->setType('textcolor', PARAM_RAW_TRIMMED);

        $mform->addElement('text', 'overlaycolor', get_string('banners_overlaycolor', 'theme_eadtraining'), ['size' => 10]);
        $mform->setType('overlaycolor', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('overlaycolor', 'banners_overlaycolor', 'theme_eadtraining');

        $opacities = [];
        for ($opacity = 0; $opacity <= 90; $opacity += 10) {
            $opacities[$opacity] = "{$opacity}%";
        }
        $mform->addElement('select', 'overlayopacity', get_string('banners_overlayopacity', 'theme_eadtraining'), $opacities);

        $mform->addElement('text', 'height', get_string('banners_height', 'theme_eadtraining'), ['size' => 6]);
        $mform->setType('height', PARAM_INT);
        $mform->addHelpButton('height', 'banners_height', 'theme_eadtraining');

        // Homepage.
        $mform->addElement('header', 'displayheader', get_string('banners_display', 'theme_eadtraining'));
        $mform->setExpanded('displayheader');
        $mform->addElement('advcheckbox', 'showonhome', get_string('banners_showonhome', 'theme_eadtraining'));
        $mform->addHelpButton('showonhome', 'banners_showonhome', 'theme_eadtraining');

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('savechanges')),
            $mform->createElement('submit', 'previewbutton', get_string('preview')),
            $mform->createElement('cancel'),
        ];
        $mform->registerNoSubmitButton('previewbutton');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * Validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     * @throws \coding_exception
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim($data['name']) === '') {
            $errors['name'] = get_string('required');
        }
        foreach (['textcolor', 'overlaycolor'] as $field) {
            if ($data[$field] !== '' && !section_manager::is_color($data[$field])) {
                $errors[$field] = get_string('banners_invalidcolor', 'theme_eadtraining');
            }
        }
        if ($data['height'] < 200 || $data['height'] > 1200) {
            $errors['height'] = get_string('banners_invalidheight', 'theme_eadtraining');
        }
        if (trim($data['buttontext']) !== '' && trim($data['buttonurl']) === '') {
            $errors['buttonurl'] = get_string('banners_buttonurl_required', 'theme_eadtraining');
        }

        return $errors;
    }
}
