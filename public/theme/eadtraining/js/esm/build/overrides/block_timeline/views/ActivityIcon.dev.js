/**
 * Theme override of the Timeline activity icon (block_timeline/views/ActivityIcon, swizzlable).
 *
 * Calendar page events without an activity (site, course, organisation events, reminders) come with the Moodle
 * calendar icon: it is shown in the same box as the activity icons, so every Timeline item lines up.
 * Activity icons are the core ones.
 *
 * @module     theme_eadtraining/overrides/block_timeline/views/ActivityIcon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { createElement } from "react";
import { ActivityIcon as CoreActivityIcon } from "@moodle/lms/theme-original/block_timeline/views/ActivityIcon";

function ActivityIcon({ modulename, iconurl, alt = "" }) {
  if ((!modulename || modulename === "undefined") && iconurl) {
    return createElement(
      "span",
      { className: "mds-activity-icon mds-activity-icon--none mds-activity-icon--size-xl timeline-calendar-icon" },
      createElement("img", { src: iconurl, alt, className: "w-100 h-100" })
    );
  }
  return createElement(CoreActivityIcon, { modulename, iconurl, alt });
}

export { ActivityIcon };
