/**
 * Theme override of the Timeline block data layer (block_timeline/repository).
 *
 * The dashboard Timeline also lists the calendar page events (lessons, live classes, course, organisation and site
 * events) next to the activity deadlines, in both views:
 * - "Sort by dates": getTimelineEvents() calls local_gcal_get_timeline_events;
 * - "Sort by courses": getEventsByCourses() calls local_gcal_get_timeline_course_events and getEventsByCourse()
 *   ("More activities" of a course) calls local_gcal_get_timeline_events with the course.
 * These take the same arguments and return the same shapes as the core_calendar functions they replace. On error the
 * core functions are used, so the Timeline still shows the deadlines. Everything else is the core module.
 *
 * Loaded by the import map from theme/<current theme>/js/esm/build/overrides/<component>/<module>.js.
 *
 * @module     theme_eadtraining/overrides/block_timeline/repository
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { fetchOne } from "@moodle/lms/core/ajax";
import * as core from "@moodle/lms/theme-original/block_timeline/repository";

export {
  getEnrolledCourses,
  getFormattedDays,
  getFormattedEventDateTimes,
  setUserPreference
} from "@moodle/lms/theme-original/block_timeline/repository";

const getTimelineEvents = (args) => fetchOne({
  methodname: "local_gcal_get_timeline_events",
  args: {
    timesortfrom: args.timesortfrom ?? 0,
    timesortto: args.timesortto ?? null,
    aftereventid: args.aftereventid ?? 0,
    limitnum: args.limitnum ?? 20,
    searchvalue: args.searchvalue ?? null
  }
}).catch(() => core.getTimelineEvents(args));

const getEventsByCourses = (args) => fetchOne({
  methodname: "local_gcal_get_timeline_course_events",
  args: {
    courseids: args.courseids,
    timesortfrom: args.timesortfrom ?? null,
    timesortto: args.timesortto ?? null,
    limitnum: args.limitnum ?? 10,
    searchvalue: args.searchvalue ?? null
  }
}).catch(() => core.getEventsByCourses(args));

const getEventsByCourse = (args) => fetchOne({
  methodname: "local_gcal_get_timeline_events",
  args: {
    courseid: args.courseid,
    timesortfrom: args.timesortfrom ?? 0,
    timesortto: args.timesortto ?? null,
    aftereventid: args.aftereventid ?? 0,
    limitnum: args.limitnum ?? 20,
    searchvalue: args.searchvalue ?? null
  }
}).catch(() => core.getEventsByCourse(args));

export { getTimelineEvents, getEventsByCourses, getEventsByCourse };
