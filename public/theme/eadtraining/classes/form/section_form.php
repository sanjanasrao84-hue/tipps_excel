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
 * Form to customise a homepage section. The elements come from the section model,
 * so each section only shows the settings it supports.
 *
 * Custom data: "fields" (section_manager::get_section_fields()), "rowcount" (items to show) and "addrows".
 *
 * @package   theme_eadtraining
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_form extends \moodleform {

    /**
     * Form definition.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function definition() {
        $mform = $this->_form;
        $fields = $this->_customdata['fields'];

        $mform->addElement('hidden', 'pageid');
        $mform->setType('pageid', PARAM_INT);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);
        $mform->addElement('hidden', 'addrows');
        $mform->setType('addrows', PARAM_INT);
        $mform->setConstant('addrows', $this->_customdata['addrows']);

        // Texts.
        if ($fields['texts']) {
            $this->add_header('textsheader', get_string('banners_group_texts', 'theme_eadtraining'));
            foreach ($fields['texts'] as $field) {
                $attributes = ['placeholder' => $field['default']];
                if ($field['multiline']) {
                    $mform->addElement('textarea', $field['name'], $field['label'], $attributes + ['rows' => 3, 'cols' => 60]);
                } else {
                    $mform->addElement('text', $field['name'], $field['label'], $attributes + ['size' => 60]);
                }
                $mform->setType($field['name'], PARAM_TEXT);
                $mform->addRule($field['name'], null, 'required', null, 'client');
            }
        }

        // Links.
        if ($fields['links']) {
            $this->add_header('linksheader', get_string('sections_links', 'theme_eadtraining'));
            foreach ($fields['links'] as $field) {
                $mform->addElement('text', $field['name'], $field['label'], ['size' => 60, 'placeholder' => $field['default']]);
                $mform->setType($field['name'], PARAM_RAW_TRIMMED);
            }
            $mform->addElement('static', 'linkshelp', '', get_string('sections_links_help', 'theme_eadtraining'));
        }

        // Images.
        if ($fields['images']) {
            $this->add_header('imagesheader', get_string('sections_images', 'theme_eadtraining'));
            foreach ($fields['images'] as $field) {
                $current = \html_writer::empty_tag('img', ['src' => $field['value'], 'alt' => '',
                    'class' => 'rounded border', 'style' => 'max-height: 90px; max-width: 220px; object-fit: cover;']);
                $mform->addElement('static', "{$field['name']}_current", $field['label'], $current);
                $mform->addElement('filepicker', $field['name'], get_string('sections_image_replace', 'theme_eadtraining'),
                    null, section_manager::image_options());
                if ($field['custom']) {
                    $mform->addElement('advcheckbox', "{$field['name']}_reset", '',
                        get_string('sections_image_reset', 'theme_eadtraining'));
                }
            }
        }

        // Options.
        if ($fields['block']) {
            $this->add_header('optionsheader', get_string('sections_options', 'theme_eadtraining'));
            foreach ($fields['block'] as $field) {
                $this->add_field($field['name'], $field);
            }
        }

        // Repeatable items.
        if ($fields['rowdefs']) {
            $mform->addElement('hidden', 'rows_present', 1);
            $mform->setType('rows_present', PARAM_INT);

            $rowcount = $this->_customdata['rowcount'];
            $positions = array_combine(range(1, max(1, $rowcount)), range(1, max(1, $rowcount)));
            $existing = count($fields['rows']);
            for ($index = 0; $index < $rowcount; $index++) {
                $name = $index < $existing ? section_manager::row_name($fields['rows'][$index], $fields['rowdefs']) : '';
                $title = $index < $existing
                    ? get_string('sections_item', 'theme_eadtraining', (object) ['number' => $index + 1, 'name' => $name])
                    : get_string('sections_newitem', 'theme_eadtraining');
                $this->add_header("rowheader_{$index}", $title);

                foreach ($fields['rowdefs'] as $key => $definition) {
                    $this->add_field("row_{$index}_{$key}", $definition, $index >= $existing);
                }
                $mform->addElement('select', "row_{$index}_order", get_string('banners_position', 'theme_eadtraining'), $positions);
                $mform->setDefault("row_{$index}_order", $index + 1);
                $mform->addElement('advcheckbox', "row_{$index}_delete", '',
                    get_string($index < $existing ? 'sections_item_remove' : 'sections_item_discard', 'theme_eadtraining'));
            }

            $mform->addElement('submit', 'additem', get_string('sections_item_add', 'theme_eadtraining'));
            $mform->registerNoSubmitButton('additem');
        }

        if ($fields['hashtml']) {
            $this->add_header('advancedheader', get_string('advanced'), false);
            $mform->addElement('advcheckbox', 'rebuild', get_string('banners_rebuild', 'theme_eadtraining'));
            $mform->addHelpButton('rebuild', 'banners_rebuild', 'theme_eadtraining');
        }

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
     * Adds an expanded section header.
     *
     * @param string $name
     * @param string $title
     * @param bool $expanded
     * @return void
     */
    private function add_header(string $name, string $title, bool $expanded = true): void {
        $this->_form->addElement('header', $name, $title);
        $this->_form->setExpanded($name, $expanded);
    }

    /**
     * Adds an element for a model form field (text, textarea or select).
     *
     * @param string $name
     * @param array $field
     * @param bool $isnew The element belongs to a new, still empty item.
     * @return void
     * @throws \coding_exception
     */
    private function add_field(string $name, array $field, bool $isnew = false): void {
        $mform = $this->_form;
        $attributes = $field['help'] ? ['placeholder' => $field['help']] : [];

        if ($field['type'] == 'select') {
            $options = $field['options'];
            if ($field['required'] || $isnew || !isset($field['default'])) {
                $options = ['' => get_string('choosedots')] + $options;
            }
            $mform->addElement('select', $name, $field['label'], $options);
        } else if ($field['type'] == 'textarea') {
            $mform->addElement('textarea', $name, $field['label'], $attributes + ['rows' => 3, 'cols' => 60]);
        } else {
            $mform->addElement('text', $name, $field['label'], $attributes + ['size' => 60]);
        }
        $mform->setType($name, $field['valuetype'] == 'int' ? PARAM_INT : PARAM_TEXT);
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
        $fields = $this->_customdata['fields'];

        foreach ($fields['texts'] as $field) {
            if (trim($data[$field['name']] ?? '') === '') {
                $errors[$field['name']] = get_string('required');
            }
        }

        foreach ($fields['links'] as $field) {
            $value = trim($data[$field['name']] ?? '');
            if ($value !== '' && $value[0] !== '#' && clean_param($value, PARAM_URL) === '') {
                $errors[$field['name']] = get_string('sections_invalidurl', 'theme_eadtraining');
            }
        }

        foreach ($fields['block'] as $field) {
            $value = (string) ($data[$field['name']] ?? '');
            if ($field['type'] == 'select' && $value !== '' && !array_key_exists($value, $field['options'])) {
                $errors[$field['name']] = get_string('invaliddata', 'error');
            }
        }

        // Kept items must have their required values; empty new items are ignored.
        for ($index = 0; array_key_exists("row_{$index}_order", $data); $index++) {
            if (!empty($data["row_{$index}_delete"])) {
                continue;
            }
            $empty = true;
            foreach ($fields['rowdefs'] as $key => $definition) {
                $value = section_manager::clean_field_value($data["row_{$index}_{$key}"] ?? '', $definition);
                if ($value !== '' && $value !== 0) {
                    $empty = false;
                }
            }
            if ($empty) {
                continue;
            }
            foreach ($fields['rowdefs'] as $key => $definition) {
                $value = section_manager::clean_field_value($data["row_{$index}_{$key}"] ?? '', $definition);
                if ($definition['required'] && ($value === '' || $value === 0)) {
                    $errors["row_{$index}_{$key}"] = get_string('required');
                }
            }
        }

        return $errors;
    }
}
