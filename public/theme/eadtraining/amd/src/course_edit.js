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

/**
 * "Add a new course" / "Edit course settings" page (course/edit.php).
 *
 * The section chips, badges and layout are the same as on every settings form (theme_eadtraining/form_layout); this
 * adds what only the course form has:
 *  - an intro under the title and a one-line description in every section header;
 *  - a short name suggested from the full name while the user has not typed one (new courses only);
 *  - a Show/Hide toggle in place of the course visibility select (the select stays in the form and is submitted);
 *  - a plain dropdown for the course category in place of core's autocomplete;
 *  - the Course ID number right after the visibility.
 *
 * The page styles are in scss/moodle/_courseedit.scss.
 *
 * @module     theme_eadtraining/course_edit
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';
import {unhide} from 'core/aria';
import {enhance, el, faIcon, ICONS} from 'theme_eadtraining/form_layout';

/** Course sections that have a description string (theme_eadtraining courseedit_desc_<id>). */
const DESCRIBED = ['general', 'descriptionhdr', 'courseformathdr', 'appearancehdr', 'filehdr', 'completionhdr', 'groups',
    'rolerenaming', 'tagshdr', 'aitoolshdr'];

/** Icons for the course sections that are not in form_layout's list. */
const COURSE_ICONS = {courseformathdr: 'fa-table-columns'};

/** Words left out of the suggested short name. */
const STOPWORDS = new Set(['a', 'an', 'and', 'the', 'of', 'to', 'in', 'for', 'on', 'with', 'at', 'by', '&']);

/**
 * Builds a short name such as "INTR-PSYC" from "Introduction to Psychology".
 *
 * @param {string} fullname
 * @returns {string}
 */
const suggestShortname = (fullname) => {
    const words = fullname.replace(/[^\p{L}\p{M}\p{N}\s]/gu, ' ').split(/\s+/).filter(Boolean);
    if (!words.length) {
        return '';
    }
    let keep = words.filter((w) => !STOPWORDS.has(w.toLowerCase()));
    if (!keep.length) {
        keep = words;
    }
    if (keep.length === 1) {
        return Array.from(keep[0]).slice(0, 12).join('').toUpperCase();
    }
    return keep.slice(0, 4)
        .map((w) => (/^\d+$/.test(w) ? w : Array.from(w).slice(0, 4).join('')))
        .join('-')
        .toUpperCase();
};

/**
 * Course category: a plain dropdown with the chosen category shown in the box, in place of core's searchable
 * autocomplete (the choice as a tag above a search field). The autocomplete keeps the original select and submits it,
 * so show that select again and hide the autocomplete parts; this runs again whenever the autocomplete (which renders
 * asynchronously) adds them.
 *
 * @param {HTMLFormElement} form
 */
const plainCategorySelect = (form) => {
    const category = form.querySelector('select#id_category');
    if (!category || !category.parentElement) {
        return;
    }
    const field = category.parentElement;
    const plain = () => {
        Array.from(field.children).forEach((child) => {
            if (child !== category && !child.classList.contains('invalid-feedback')) {
                // Inline !important: the search box wrapper has .d-md-inline-block, which beats [hidden].
                child.style.setProperty('display', 'none', 'important');
            }
        });
        if (category.style.display === 'none') {
            category.style.display = '';
            unhide(category);
        }
        // Core CSS keeps [data-fieldtype=autocomplete] select invisible (it reserves its space while loading).
        category.style.setProperty('visibility', 'visible');
        // The autocomplete adds a blank first option so a single choice can be cleared; this field is required.
        Array.from(category.options)
            .filter((opt) => opt.value === '' && !opt.text.trim() && !opt.selected)
            .forEach((opt) => opt.remove());
        const label = document.getElementById('id_category_label');
        if (label) {
            label.htmlFor = 'id_category';
        }
    };
    plain();
    new MutationObserver(plain).observe(field, {childList: true});
};

/**
 * The Course ID number right after the visibility, so they share a row (moved in the DOM, not just visually, so the
 * tab order follows what is on screen).
 *
 * @param {HTMLFormElement} form
 */
const arrangeFields = (form) => {
    const idnumber = form.querySelector('#fitem_id_idnumber');
    const visibility = form.querySelector('#fitem_id_visible');
    if (idnumber && visibility && idnumber.parentElement === visibility.parentElement) {
        visibility.after(idnumber);
    }
};

/**
 * Initialise the page.
 */
export const init = async() => {
    const form = document.querySelector('#region-main form.mform');
    if (!form || form.dataset.ceInit) {
        return;
    }

    const idinput = form.querySelector('input[name="id"]');
    const isnew = !idinput || !parseInt(idinput.value, 10);

    const keys = [
        'courseedit_intro_new', 'courseedit_intro_edit', 'courseedit_shortname_suggested', 'courseedit_visible_0',
        'courseedit_visible_1',
        ...DESCRIBED.map((id) => `courseedit_desc_${id}`),
    ];
    let strings;
    try {
        const list = await getStrings(keys.map((key) => ({key, component: 'theme_eadtraining'})));
        strings = Object.fromEntries(keys.map((key, i) => [key, list[i]]));
    } catch (e) {
        return;
    }

    // Before enhance(), which places the section chips right before the form.
    arrangeFields(form);
    const heading = Array.from(form.parentElement.children).find((n) => /^H[12]$/.test(n.tagName));
    if (heading) {
        heading.classList.add('ce-title');
        heading.after(el('p', 'ce-intro', isnew ? strings.courseedit_intro_new : strings.courseedit_intro_edit));
    }

    const descriptions = Object.fromEntries(DESCRIBED.map((id) => [id, strings[`courseedit_desc_${id}`]]));
    const layout = await enhance(form, {icons: {...ICONS, ...COURSE_ICONS}, descriptions});
    const refresh = layout ? layout.refresh : () => null;

    // Short name suggestion (new courses only, and only until the user types one).
    const fullname = form.querySelector('input#id_fullname');
    const shortname = form.querySelector('input#id_shortname');
    if (isnew && fullname && shortname && !shortname.value.trim()) {
        let auto = true;
        const hint = el('div', 'ce-field-hint');
        hint.append(faIcon('fa-wand-magic-sparkles'), document.createTextNode(` ${strings.courseedit_shortname_suggested}`));
        hint.hidden = true;
        shortname.after(hint);
        shortname.addEventListener('input', () => {
            auto = shortname.value.trim() === '';
            hint.hidden = true;
        });
        fullname.addEventListener('input', () => {
            if (!auto) {
                return;
            }
            shortname.value = suggestShortname(fullname.value);
            hint.hidden = shortname.value === '';
            shortname.classList.remove('is-invalid');
            refresh();
        });
    }

    plainCategorySelect(form);

    // Visibility: Show/Hide toggle in place of the select.
    const visible = form.querySelector('select#id_visible');
    if (visible && !visible.disabled && visible.options.length === 2) {
        const group = el('div', 'ce-segmented');
        group.setAttribute('role', 'radiogroup');
        const label = document.getElementById('id_visible_label');
        if (label) {
            group.setAttribute('aria-labelledby', 'id_visible_label');
        }
        const help = el('div', 'ce-field-hint ce-visibility-hint');
        const buttons = Array.from(visible.options)
            .sort((a, b) => Number(b.value) - Number(a.value)) // Show first.
            .map((opt) => {
                const btn = el('button', 'ce-segment');
                btn.type = 'button';
                btn.dataset.value = opt.value;
                btn.setAttribute('role', 'radio');
                btn.append(faIcon(opt.value === '1' ? 'fa-eye' : 'fa-eye-slash'), document.createTextNode(` ${opt.text}`));
                btn.addEventListener('click', () => {
                    visible.value = opt.value;
                    visible.dispatchEvent(new Event('change', {bubbles: true}));
                });
                group.append(btn);
                return btn;
            });
        const sync = () => {
            buttons.forEach((b) => {
                const on = b.dataset.value === visible.value;
                b.classList.toggle('active', on);
                b.setAttribute('aria-checked', on ? 'true' : 'false');
                b.tabIndex = on ? 0 : -1;
            });
            help.textContent = strings[`courseedit_visible_${visible.value === '1' ? 1 : 0}`];
        };
        group.addEventListener('keydown', (e) => {
            if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
                return;
            }
            e.preventDefault();
            const next = buttons.find((b) => b.dataset.value !== visible.value);
            next.click();
            next.focus();
        });
        visible.addEventListener('change', sync);
        visible.classList.add('d-none');
        visible.after(group, help);
        sync();
    }
};
