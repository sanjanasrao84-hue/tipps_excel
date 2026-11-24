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
 * Settings forms on every page (any form in the main region with collapsible sections):
 *  - a sticky row of section chips above the form, with scroll spy, and a tick or error marker per section;
 *  - a Required/Optional badge (and, if given, a one-line description) in every section header;
 *  - each date kept on one line from md up (core lets it wrap, which can drop the calendar icon below).
 *
 * Works on top of the unmodified core form: it only adds things around the existing fields and never moves the form
 * itself (moving it would reload the editor iframes). The styles are in scss/moodle/_formlayout.scss.
 *
 * theme_eadtraining/course_edit uses enhance() with its own icons and descriptions on the course settings page.
 *
 * @module     theme_eadtraining/form_layout
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';

/** Icons for common section ids (course, activity and profile settings); others get DEFAULT_ICON. */
export const ICONS = {
    general: 'fa-pen',
    descriptionhdr: 'fa-align-left',
    appearancehdr: 'fa-palette',
    availability: 'fa-calendar-days',
    availabilityconditionsheader: 'fa-lock',
    timing: 'fa-clock',
    activitycompletionheader: 'fa-circle-check',
    completionhdr: 'fa-circle-check',
    modstandardelshdr: 'fa-gear',
    modstandardgrade: 'fa-star',
    modstandardratings: 'fa-star-half-stroke',
    gradeheader: 'fa-star',
    tagshdr: 'fa-tags',
    competenciessection: 'fa-list-check',
    groups: 'fa-users',
    groupsubmissionsettings: 'fa-users',
    submissiontypes: 'fa-upload',
    submissionsettings: 'fa-file-arrow-up',
    feedbacktypes: 'fa-comment',
    notifications: 'fa-bell',
    layouthdr: 'fa-table-columns',
    display: 'fa-display',
    interactionhdr: 'fa-hand-pointer',
    reviewoptionshdr: 'fa-eye',
    security: 'fa-shield-halved',
    overallfeedbackhdr: 'fa-comment-dots',
    contentsection: 'fa-file-lines',
    filehdr: 'fa-folder-open',
    rolerenaming: 'fa-id-badge',
    aitoolshdr: 'fa-wand-magic-sparkles',
};
export const DEFAULT_ICON = 'fa-sliders';

const INVALID = '.is-invalid, .has-danger';
const FORMS = '#region-main form.mform';

/**
 * Create an element.
 *
 * @param {string} tag
 * @param {string} className
 * @param {string} [text]
 * @returns {HTMLElement}
 */
export const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) {
        node.className = className;
    }
    if (text !== undefined) {
        node.textContent = text;
    }
    return node;
};

/**
 * Icon element.
 *
 * @param {string} icon fa-* class
 * @returns {HTMLElement}
 */
export const faIcon = (icon) => {
    const i = el('i', `icon fa ${icon} fa-fw`);
    i.setAttribute('aria-hidden', 'true');
    return i;
};

/**
 * The input that holds the value of a required form item, or null.
 *
 * @param {HTMLElement} fitem
 * @returns {HTMLElement|null}
 */
const valueField = (fitem) => fitem.querySelector('select, textarea, input:not([type=hidden]):not([type=button])'
    + ':not([type=submit]):not([type=checkbox]):not([type=radio])');

/**
 * The form's sections: its collapsible fieldsets.
 *
 * @param {HTMLFormElement} form
 * @returns {HTMLFieldSetElement[]}
 */
const sectionsOf = (form) => Array.from(form.querySelectorAll(':scope > fieldset.collapsible[id]'))
    .filter((fs) => fs.querySelector(':scope > legend'));

/**
 * Badge in a section header.
 *
 * @param {HTMLFieldSetElement} fs
 * @param {boolean} required
 * @param {object} strings
 * @param {string} [description]
 */
const decorateHeader = (fs, required, strings, description) => {
    const header = fs.querySelector(':scope > legend + div');
    if (!header) {
        return;
    }
    header.classList.add('ce-section-header');
    const badge = el('span', `ce-badge ${required ? 'ce-badge-required' : 'ce-badge-optional'}`,
        required ? strings.formnav_required : strings.formnav_optional);
    (header.querySelector('.ftoggler') || header).after(badge);
    if (description) {
        header.after(el('p', 'ce-section-desc', description));
    }
};

/**
 * Sticky row of section chips before the form, with scroll spy.
 *
 * @param {HTMLFormElement} form
 * @param {object[]} sections {fs, id, title}
 * @param {object} icons
 * @param {object} strings
 */
const buildNavigator = (form, sections, icons, strings) => {
    const nav = el('nav', 'ce-nav');
    nav.setAttribute('aria-label', strings.formnav_sections);
    const card = el('div', 'ce-nav-card');
    const list = el('ol', 'ce-nav-list');
    card.append(list);
    nav.append(card);

    let active = null;
    const setActive = (link) => {
        if (active === link) {
            return;
        }
        if (active) {
            active.classList.remove('active');
            active.removeAttribute('aria-current');
        }
        active = link;
        link.classList.add('active');
        link.setAttribute('aria-current', 'true');
        // Keep the active chip in view when the row scrolls sideways.
        if (list.scrollWidth > list.clientWidth) {
            list.scrollTo({left: link.parentElement.offsetLeft - 16, behavior: 'smooth'});
        }
    };

    sections.forEach((s) => {
        const li = el('li');
        const link = el('a', 'ce-nav-link');
        link.href = `#${s.fs.id}`;
        link.title = s.title; // The name may be shortened with "…" when the row is tight.
        const icon = el('span', 'ce-nav-icon');
        icon.append(faIcon(icons[s.id] || DEFAULT_ICON));
        const text = el('span', 'ce-nav-text');
        text.append(el('span', 'ce-nav-title', s.title));
        s.status = el('span', 'ce-nav-status');
        link.append(icon, text, s.status);
        li.append(link);
        list.append(li);
        s.link = link;

        link.addEventListener('click', (e) => {
            e.preventDefault();
            const toggle = s.fs.querySelector(':scope > legend + div a.fheader.collapsed');
            if (toggle) {
                toggle.click();
            }
            s.fs.scrollIntoView({behavior: 'smooth', block: 'start'});
            setActive(link);
        });
    });

    form.parentElement.insertBefore(nav, form);

    // Shrink the chips only when they nearly fit (a few "…" are fine); with many sections keep the full names and
    // let the row scroll. Measured at full size, again whenever the row changes width.
    const fit = () => {
        list.classList.remove('ce-nav-shrink');
        const over = list.scrollWidth - list.clientWidth;
        list.classList.toggle('ce-nav-shrink', over > 0 && over <= list.clientWidth * 0.12);
    };

    // Jumping to a section stops below the sticky row.
    const setNavHeight = () => form.style.setProperty('--ce-nav-h', `${nav.offsetHeight}px`);
    let width = 0;
    if (window.ResizeObserver) {
        new ResizeObserver(() => {
            setNavHeight();
            if (list.clientWidth !== width) {
                width = list.clientWidth;
                fit();
            }
        }).observe(nav);
    }
    fit();
    setNavHeight();

    let ticking = false;
    const spy = () => {
        ticking = false;
        const line = window.innerHeight * 0.3;
        let current = sections[0];
        for (const s of sections) {
            if (s.fs.getBoundingClientRect().top <= line) {
                current = s;
            }
        }
        // At the very bottom, the last short sections never reach the line.
        if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
            current = sections[sections.length - 1];
        }
        setActive(current.link);
    };
    window.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(spy);
        }
    }, {passive: true});
    spy();
};

/**
 * Tick / error markers on the chips.
 *
 * @param {HTMLFormElement} form
 * @param {object[]} sections
 * @param {object} strings
 * @returns {Function} refresh
 */
const trackStatus = (form, sections, strings) => {
    const filled = (field) => (field.value || '').trim() !== '';
    const refresh = () => {
        for (const s of sections) {
            if (!s.link) {
                continue;
            }
            const invalid = !!s.fs.querySelector(INVALID);
            const ok = s.required.length > 0 && s.required.every(filled);
            s.link.classList.toggle('has-error', invalid);
            s.link.classList.toggle('is-done', !invalid && ok);
            s.status.replaceChildren();
            s.status.title = invalid ? strings.formnav_hasissues : '';
            if (invalid) {
                s.status.append(faIcon('fa-circle-exclamation'));
            } else if (ok) {
                s.status.append(faIcon('fa-check'));
            }
        }
    };
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    // Client-side validation marks fields on submit; check once it is done.
    form.addEventListener('submit', () => setTimeout(refresh, 50), true);
    form.querySelectorAll('[type=submit]').forEach((btn) => btn.addEventListener('click', () => setTimeout(refresh, 50)));
    refresh();
    return refresh;
};

/**
 * Lay out one form.
 *
 * @param {HTMLFormElement} form
 * @param {object} [options]
 * @param {object} [options.icons] Section id (without "id_") => fa-* icon.
 * @param {object} [options.descriptions] Section id => one-line description.
 * @returns {Promise<{refresh: Function}|null>} null when the form was already done or has no sections.
 */
export const enhance = async(form, {icons = ICONS, descriptions = {}} = {}) => {
    if (!form || form.dataset.ceInit) {
        return null;
    }
    const fieldsets = sectionsOf(form);
    if (!fieldsets.length) {
        return null;
    }
    form.dataset.ceInit = '1';

    // Dates on one line from md up.
    form.querySelectorAll('.fdate_time_selector.flex-wrap, .fdate_selector.flex-wrap')
        .forEach((row) => row.classList.add('flex-md-nowrap'));

    const keys = ['formnav_sections', 'formnav_required', 'formnav_optional', 'formnav_hasissues'];
    let strings;
    try {
        const list = await getStrings(keys.map((key) => ({key, component: 'theme_eadtraining'})));
        strings = Object.fromEntries(keys.map((key, i) => [key, list[i]]));
    } catch (e) {
        return null; // Leave the form as it is rather than half done.
    }

    const sections = fieldsets.map((fs) => {
        const id = fs.id.replace(/^id_/, '');
        const required = Array.from(fs.querySelectorAll('.fitem'))
            .filter((fi) => fi.querySelector(':scope > .col-form-label .form-label-addon .text-danger'))
            .map(valueField)
            .filter(Boolean);
        decorateHeader(fs, required.length > 0, strings, descriptions[id]);
        return {fs, id, title: fs.querySelector(':scope > legend').textContent.trim(), required};
    });

    // The chips only help when there is more than one section to move between.
    if (sections.length > 1) {
        buildNavigator(form, sections, icons, strings);
    }
    const refresh = trackStatus(form, sections, strings);

    // A page that came back with errors: bring the first one into view.
    const firstError = form.querySelector(INVALID);
    if (firstError) {
        (firstError.closest('.fitem') || firstError).scrollIntoView({block: 'center'});
    }

    return {refresh};
};

/**
 * Lay out every settings form on the page.
 */
export const init = () => {
    document.querySelectorAll(FORMS).forEach((form) => enhance(form));
};
