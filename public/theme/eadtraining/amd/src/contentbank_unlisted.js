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
 * Content bank "Display unlisted content", instantly and without reloading the page.
 *
 * Core (core_contentbank/displayunlisted) saves the preference and then reloads the whole page, so the navigation
 * blinks and the list takes a while to come back. This handles the click first (capture phase, so the core handler
 * never runs) and applies the same rule as core_contentbank\content::is_view_allowed() in the browser: with the box
 * off every unlisted item (.cb-unlisted) is hidden, with it on every item is shown. The preference is saved in the
 * background.
 *
 * The only case that needs the server is ticking the box on a page that was loaded with it off: the unlisted items are
 * not in the page then, so the file area is fetched once (after saving the preference) and swapped in. From then on
 * every toggle is instant. The search, sort and view buttons of core listen on the content bank region and look up the
 * file area on every use, so they keep working.
 *
 * Unlisted items are hidden with the "hidden" attribute ([hidden] is display: none !important), not with d-none, which
 * the core search uses for its own filtering.
 *
 * @module     theme_eadtraining/contentbank_unlisted
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import Pending from 'core/pending';
import {getString} from 'core/str';
import {setUserPreference} from 'core_user/repository';

const PREFERENCE = 'core_contentbank_displayunlisted';

const SELECTORS = {
    toggle: '[data-action="displayunlisted"]',
    filearea: '[data-region="contentbank"] [data-region="filearea"]',
    unlisted: '.cb-listitem.cb-unlisted',
    item: '.cb-listitem',
    searchinput: '[data-region="contentbank"] input[data-action="search"]',
    empty: '[data-region="theme-cb-empty"]',
};

/** @var {boolean} Whether the unlisted items are in the page (it was loaded with the box on, or they were fetched). */
let hasUnlisted = false;

/**
 * Re-applies a search the user has typed (core search listens to "input" on the search field).
 */
const reapplySearch = () => {
    const search = document.querySelector(SELECTORS.searchinput);
    if (search && search.value) {
        search.dispatchEvent(new Event('input', {bubbles: true}));
    }
};

/**
 * Shows the "No content available" message when every item of the list is hidden, and removes it otherwise.
 *
 * @param {HTMLElement} filearea
 * @returns {Promise<void>}
 */
const updateEmptyMessage = async(filearea) => {
    const items = filearea.querySelectorAll(SELECTORS.item);
    if (!items.length) {
        // The server rendered the empty state itself.
        return;
    }
    const allhidden = Array.from(items).every((item) => item.hidden);
    let message = filearea.querySelector(SELECTORS.empty);
    if (allhidden && !message) {
        message = document.createElement('div');
        message.dataset.region = 'theme-cb-empty';
        message.className = 'w-100 p-3 text-center text-muted';
        message.textContent = await getString('nocontentavailable', 'core_contentbank');
        filearea.append(message);
    } else if (!allhidden && message) {
        message.remove();
    }
};

/**
 * Shows or hides the unlisted items already in the page.
 *
 * @param {boolean} show
 * @returns {Promise<void>}
 */
const toggleUnlisted = (show) => {
    const filearea = document.querySelector(SELECTORS.filearea);
    if (!filearea) {
        return Promise.resolve();
    }
    filearea.querySelectorAll(SELECTORS.unlisted).forEach((item) => {
        item.hidden = !show;
    });
    reapplySearch();
    return updateEmptyMessage(filearea);
};

/**
 * Replaces the file area with the one of the page as the server renders it now (with the unlisted items).
 *
 * @returns {Promise<void>}
 */
const fetchFileArea = async() => {
    const response = await fetch(window.location.href, {credentials: 'same-origin'});
    if (!response.ok) {
        throw new Error(response.statusText || `HTTP ${response.status}`);
    }
    const html = await response.text();
    const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector(SELECTORS.filearea);
    const current = document.querySelector(SELECTORS.filearea);
    if (!fresh || !current) {
        // Unexpected page: fall back to the core behaviour.
        window.location.reload();
        return;
    }
    current.replaceWith(document.importNode(fresh, true));
    hasUnlisted = true;
    reapplySearch();
};

/**
 * Handles a change of the checkbox.
 *
 * @param {HTMLInputElement} toggle
 */
const onToggle = (toggle) => {
    const show = toggle.checked;
    const pending = new Pending('theme_eadtraining/contentbank_unlisted');
    const save = setUserPreference(PREFERENCE, show ? 1 : 0);

    if (!show || hasUnlisted) {
        // Instant: the items are in the page. The preference is saved in the background.
        toggleUnlisted(show)
            .then(() => save)
            .catch(Notification.exception)
            .finally(() => pending.resolve());
        return;
    }

    // The page was loaded with the box off: the unlisted items have to come from the server, once.
    const filearea = document.querySelector(SELECTORS.filearea);
    toggle.disabled = true;
    if (filearea) {
        filearea.style.opacity = '.5';
        filearea.setAttribute('aria-busy', 'true');
    }
    save
        .then(fetchFileArea)
        .catch(Notification.exception)
        .finally(() => {
            const area = document.querySelector(SELECTORS.filearea);
            if (area) {
                area.style.opacity = '';
                area.removeAttribute('aria-busy');
            }
            toggle.disabled = false;
            pending.resolve();
        });
};

/**
 * Initialise.
 */
export const init = () => {
    const toggle = document.querySelector(SELECTORS.toggle);
    // Loaded with the box on: every item, the unlisted ones included, is in the page.
    hasUnlisted = !!(toggle && toggle.checked);

    window.addEventListener('click', (event) => {
        const target = event.target.closest(SELECTORS.toggle);
        if (!target) {
            return;
        }
        // Keep the checkbox change, but stop the core handler (it reloads the page).
        event.stopImmediatePropagation();
        onToggle(target);
    }, true);
};
