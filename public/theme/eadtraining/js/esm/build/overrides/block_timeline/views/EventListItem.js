/**
 * Theme override of the Timeline event item (block_timeline/views/EventListItem, swizzlable).
 *
 * Same data, link, accessible name and action as the core item, laid out as a card for the modern Timeline:
 * a time pill, the activity icon, the name with its kind and course, and the overdue badge and action at the end.
 * The colour of the card's left edge comes from the kind of event (theme SCSS, moodle/_timeline.scss):
 * calendar page events, the activity purpose, or overdue.
 * The data-region attributes and the event-name / event-action / timeline-action-button classes of the core item
 * are kept, so the Behat steps and the side-region rules of block_timeline still apply.
 *
 * @module     theme_eadtraining/overrides/block_timeline/views/EventListItem
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import { createElement as h, useState, useEffect } from "react";
import { getString } from "@moodle/lms/core/stringUtils";
import { ActivityIcon } from "@moodle/lms/block_timeline/views/ActivityIcon";

export default function EventListItem({ event, courseview = false }) {
  const [overdueLabel, setOverdueLabel] = useState("");
  const [ariaLabel, setAriaLabel] = useState("");

  useEffect(() => {
    getString("overdue", "block_timeline").then(setOverdueLabel);
  }, []);

  const coursename = event.course?.fullnamedisplay ?? "";
  useEffect(() => {
    getString("ariaeventlistitem", "block_timeline", {
      name: event.activityname ?? "",
      course: coursename,
      date: event.formatteddatetime
    }).then(setAriaLabel);
  }, [event.activityname, coursename, event.formatteddatetime]);

  const time = new Date(event.timesort * 1000).toLocaleTimeString(undefined, {
    hour: "2-digit",
    minute: "2-digit",
    hour12: false
  });

  const classes = ["list-group-item", "timeline-event-list-item", "tl-item"];
  if (courseview) {
    classes.push("tl-item--courseview");
  }
  if (event.calendarevent) {
    classes.push("tl-item--calendar");
  } else if (event.purpose) {
    classes.push(`tl-item--${event.purpose}`);
  }
  if (event.overdue) {
    classes.push("tl-item--overdue");
  }

  const meta = [event.activitystr, !courseview ? coursename : ""].filter(Boolean).join(" · ");
  const action = event.action?.actionable ? event.action : null;

  return h("div", { className: classes.join(" "), "data-region": "event-list-item" },
    h("time", { className: "tl-item__time", dateTime: new Date(event.timesort * 1000).toISOString() }, time),
    event.icon && h("div", {
      className: `tl-item__icon courseicon activityiconcontainer${event.purpose ? ` ${event.purpose}` : ""}`
    }, h(ActivityIcon, { modulename: event.modulename, iconurl: event.icon.iconurl, alt: event.icon.alttext })),
    h("div", { className: "tl-item__body event-name-container" },
      h("h5", { className: "h6 event-name tl-item__title" },
        h("a", { href: event.url, title: event.name, "aria-label": ariaLabel || undefined }, event.activityname)
      ),
      meta && h("p", { className: "tl-item__meta", title: meta }, meta)
    ),
    (event.overdue || action) && h("div", { className: "tl-item__end timeline-action-button" },
      event.overdue && h("span", { className: "tl-badge tl-badge--overdue" }, overdueLabel),
      action && h("h5", { className: "h6 event-action mb-0" },
        h("a", {
          className: "tl-action list-group-item-action",
          href: action.url,
          "aria-label": action.name,
          title: action.name
        },
          action.name,
          action.showitemcount && h("span", { className: "tl-action__count" }, String(action.itemcount))
        )
      )
    )
  );
}
