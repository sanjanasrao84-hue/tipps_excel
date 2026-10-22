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
 * Calendar page: month, week, day and agenda views (FullCalendar) of the user's academic events and
 * Google Calendar, with connection actions and event management.
 *
 * Event times are shown in the user's Moodle time zone: timestamps are converted to wall-clock times
 * in that zone and given to FullCalendar in UTC mode, so the browser's own time zone does not matter.
 *
 * @module     local_gcal/calendar
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax', 'core/notification', 'core/templates', 'core/toast', 'core/modal', 'core_form/modalform',
], function(Ajax, Notification, Templates, Toast, ModalModule, ModalFormModule) {
    'use strict';

    const Modal = ModalModule.default || ModalModule;
    const ModalForm = ModalFormModule.default || ModalFormModule;
    const SMALL_SCREEN = 768;

    const str = (key, param) => M.util.get_string(key, 'local_gcal', param);

    /**
     * Wall-clock time of a timestamp in a time zone, as "YYYY-MM-DDTHH:mm:ss" (no offset).
     *
     * @param {Number} timestamp
     * @param {String} timezone
     * @returns {String}
     */
    const wallClock = (timestamp, timezone) => {
        const parts = {};
        new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
        }).formatToParts(new Date(timestamp * 1000)).forEach((part) => {
            parts[part.type] = part.value;
        });
        return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}`;
    };

    /**
     * Readable date and time of an event in the user's time zone.
     *
     * @param {Object} event
     * @param {Object} config
     * @returns {String}
     */
    const describeWhen = (event, config) => {
        const date = new Intl.DateTimeFormat(config.locale, {
            timeZone: config.timezone, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
        });
        const time = new Intl.DateTimeFormat(config.locale, {timeZone: config.timezone, hour: '2-digit', minute: '2-digit'});
        const start = new Date(event.start * 1000);
        const end = new Date(event.end * 1000);
        if (event.allday) {
            return `${date.format(start)} · ${str('allday')}`;
        }
        if (event.deadline || event.end <= event.start) {
            return `${date.format(start)} · ${time.format(start)} (${str('deadline')})`;
        }
        return `${date.format(start)} · ${time.format(start)}–${time.format(end)}`;
    };

    /**
     * Waits for the FullCalendar library, loaded as a global script.
     *
     * @returns {Promise}
     */
    const fullCalendarReady = () => new Promise((resolve) => {
        const check = () => (window.FullCalendar ? resolve(window.FullCalendar) : setTimeout(check, 50));
        check();
    });

    /**
     * Initialises the page.
     *
     * @param {Object} config timezone, locale, initialdate, cancreate, firstday
     */
    const init = (config) => {
        const root = document.querySelector('[data-region="local-gcal"]');
        if (!root) {
            return;
        }
        const filters = {
            course: root.querySelector('[data-filter="course"]'),
            organisation: root.querySelector('[data-filter="organisation"]'),
        };
        const hidden = new Set();
        const warnings = root.querySelector('[data-region="warnings"]');
        let calendar = null;

        const showWarnings = (messages) => {
            warnings.innerHTML = '';
            messages.forEach((message) => {
                const div = document.createElement('div');
                div.className = 'alert alert-warning py-2';
                div.textContent = message;
                warnings.appendChild(div);
            });
        };

        const loadEvents = (info, success, failure) => {
            const pad = 86400;
            Ajax.call([{
                methodname: 'local_gcal_get_events',
                args: {
                    start: Math.floor(info.start.valueOf() / 1000) - pad,
                    end: Math.floor(info.end.valueOf() / 1000) + pad,
                    courseid: parseInt(filters.course ? filters.course.value : 0, 10) || 0,
                    organisationid: parseInt(filters.organisation ? filters.organisation.value : 0, 10) || 0,
                },
            }])[0].then((result) => {
                showWarnings(result.warnings);
                success(result.events.filter((event) => !hidden.has(event.category)).map((event) => ({
                    id: event.id,
                    title: event.title,
                    start: event.allday ? wallClock(event.start, config.timezone).slice(0, 10)
                        : wallClock(event.start, config.timezone),
                    end: event.end > event.start
                        ? (event.allday ? wallClock(event.end, config.timezone).slice(0, 10) : wallClock(event.end, config.timezone))
                        : null,
                    allDay: event.allday,
                    backgroundColor: event.color,
                    borderColor: event.color,
                    classNames: ['lgc-event', `lgc-event--${event.source}`, event.deadline ? 'lgc-event--deadline' : ''],
                    extendedProps: event,
                })));
                return result;
            }).catch((error) => {
                failure(error);
                Notification.exception(error);
            });
        };

        const showDetails = (event) => {
            const location = event.location || '';
            const context = Object.assign({}, event, {
                when: describeWhen(event, config),
                personal: event.source === 'google',
                isactivity: !!event.editdatesurl || event.category === 'assignment' || event.category === 'assessment'
                    || event.category === 'live' || event.category === 'activity',
                locationurl: /^https?:\/\//i.test(location),
            });
            Templates.renderForPromise('local_gcal/event_details', context).then(({html}) => Modal.create({
                title: event.title,
                body: html,
                show: true,
                removeOnClose: true,
            })).then((modal) => {
                const body = modal.getRoot()[0];
                body.addEventListener('click', (e) => {
                    const button = e.target.closest('[data-action]');
                    if (!button) {
                        return;
                    }
                    if (button.dataset.action === 'edit') {
                        modal.destroy();
                        openForm(parseInt(button.dataset.eventid, 10), '');
                    } else if (button.dataset.action === 'delete') {
                        Notification.saveCancelPromise(str('cancelevent'), str('cancelconfirm', event.title), str('cancelevent'))
                            .then(() => Ajax.call([{methodname: 'local_gcal_delete_event',
                                args: {eventid: parseInt(button.dataset.eventid, 10)}}])[0])
                            .then(() => {
                                modal.destroy();
                                Toast.add(str('eventcancelled'), {type: 'success'});
                                calendar.refetchEvents();
                                return null;
                            })
                            .catch(() => null);
                    }
                });
                return modal;
            }).catch(Notification.exception);
        };

        const openForm = (eventid, start) => {
            const form = new ModalForm({
                formClass: '\\local_gcal\\form\\event_form',
                args: {id: eventid || 0, start: start || ''},
                modalConfig: {title: eventid ? str('editevent') : str('createevent')},
            });
            form.addEventListener(form.events.FORM_SUBMITTED, () => {
                Toast.add(str('eventsaved'), {type: 'success'});
                calendar.refetchEvents();
                refreshStatus();
            });
            form.show();
        };

        const renderStatus = (status) => Templates.renderForPromise('local_gcal/status', status).then(({html, js}) => {
            Templates.replaceNodeContents(root.querySelector('[data-region="status"]'), html, js);
            return null;
        });

        const refreshStatus = () => Ajax.call([{methodname: 'local_gcal_get_status', args: {}}])[0]
            .then(renderStatus)
            .catch(() => null);

        root.addEventListener('click', (e) => {
            const button = e.target.closest('[data-action]');
            if (!button || !root.contains(button)) {
                return;
            }
            if (button.dataset.action === 'sync') {
                button.disabled = true;
                button.textContent = str('syncing');
                Ajax.call([{methodname: 'local_gcal_sync_now', args: {}}])[0].then((result) => {
                    Toast.add(result.message, {type: result.status.lastsyncstatus === 'ok' ? 'success' : 'warning'});
                    calendar.refetchEvents();
                    return refreshStatus();
                }).catch((error) => {
                    button.disabled = false;
                    Notification.exception(error);
                });
            } else if (button.dataset.action === 'create') {
                openForm(0, '');
            }
        });

        root.addEventListener('change', (e) => {
            const input = e.target;
            if (input.dataset.filter) {
                calendar.refetchEvents();
            } else if (input.dataset.category) {
                if (input.checked) {
                    hidden.delete(input.dataset.category);
                } else {
                    hidden.add(input.dataset.category);
                }
                calendar.refetchEvents();
            } else if (input.dataset.action === 'personal') {
                Ajax.call([{methodname: 'local_gcal_set_preferences', args: {showpersonal: input.checked}}])[0]
                    .then(() => calendar.refetchEvents())
                    .catch(Notification.exception);
            }
        });

        fullCalendarReady().then((FullCalendar) => {
            const small = window.innerWidth < SMALL_SCREEN;
            calendar = new FullCalendar.Calendar(root.querySelector('[data-region="calendar"]'), {
                timeZone: 'UTC',
                locale: config.locale,
                firstDay: config.firstday,
                initialView: small ? 'listWeek' : 'dayGridMonth',
                initialDate: config.initialdate || undefined,
                headerToolbar: small
                    ? {left: 'prev,next', center: 'title', right: 'today'}
                    : {left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'},
                footerToolbar: small ? {center: 'dayGridMonth,timeGridDay,listWeek'} : false,
                buttonText: {
                    today: str('today'), month: str('viewmonth'), week: str('viewweek'), day: str('viewday'),
                    list: str('viewagenda'),
                },
                allDayText: str('allday'),
                noEventsText: str('noevents'),
                height: 'auto',
                nowIndicator: true,
                dayMaxEvents: 4,
                eventTimeFormat: {hour: '2-digit', minute: '2-digit', meridiem: false},
                events: loadEvents,
                eventClick: (info) => {
                    info.jsEvent.preventDefault();
                    showDetails(info.event.extendedProps);
                },
                dateClick: config.cancreate ? (info) => {
                    // Wall-clock time of the clicked slot (09:00 for a day), converted by the server in the user's time zone.
                    const wall = info.date.toISOString().slice(0, 16);
                    openForm(0, info.allDay ? wall.slice(0, 10) + 'T09:00' : wall);
                } : undefined,
                windowResize: () => {
                    const isSmall = window.innerWidth < SMALL_SCREEN;
                    if (isSmall && calendar.view.type !== 'listWeek') {
                        calendar.changeView('listWeek');
                    }
                },
            });
            calendar.render();
            return calendar;
        }).catch(Notification.exception);
    };

    return {init: init};
});
