/**
 * Replacement of core/fetch (the Moodle REST API client, /r.php/api/rest/v2/...).
 *
 * Saving or reading user preferences must not depend on the REST router being set up on the web server. The Dashboard
 * course overview filters (grouping, custom field, sort, view), the drawers and other core screens save preferences
 * through core_user/repository, which calls this module. When the router is not reachable (vendor/ libraries missing,
 * no FallbackResource / PATH_INFO, $CFG->routerconfigured set without the rewrite...) the request fails and core shows
 * an "undefined" error box: the failure reason is the HTTP status text, which is empty over HTTP/2.
 *
 * This module:
 * - sends user preference requests (core_user current/preferences[/<name>]) to the AJAX web services
 *   (core_user_get_user_preferences and core_user_set_user_preferences, lib/ajax/service.php), which run the same
 *   preference checks, and answers with the same response shape as the REST API;
 * - sends the question bank requests (question/counts, question/banks, question/categories: question counts on the
 *   question banks page, the bank switcher, moving questions) to theme_eadtraining_question_api, which runs the same
 *   code as those REST routes;
 * - runs every other request through the core module unchanged, rejecting with an Error that names the request instead
 *   of an empty string.
 *
 * Registered for every theme in the import map as "@moodle/lms/core/fetch" (theme_eadtraining\hook_callbacks::
 * before_import_map_config); the AMD name core/fetch resolves to it as well. Not under js/esm/build/overrides, which
 * would only apply while theme_eadtraining itself is the current theme.
 *
 * @module     theme_eadtraining/fetch_fallback
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import CoreFetch from "@moodle/lms/theme-original/core/fetch";
import { fetchOne } from "@moodle/lms/core/ajax";

/** Preference endpoints of core_user: "current/preferences" and "current/preferences/<name>". */
const PREFERENCES = /^current\/preferences(?:\/([^/?#]+))?$/;

/**
 * Body of a request as an object.
 *
 * @param {string|object|FormData|null} body
 * @returns {object}
 */
const parseBody = (body) => {
  if (!body) {
    return {};
  }
  if (typeof body === "string") {
    try {
      return JSON.parse(body);
    } catch (e) {
      return {};
    }
  }
  if (typeof FormData !== "undefined" && body instanceof FormData) {
    return Object.fromEntries(body.entries());
  }
  return body;
};

/**
 * A JSON response, like the REST API returns.
 *
 * @param {object} data
 * @returns {Response}
 */
const jsonResponse = (data) => new Response(JSON.stringify(data), {
  status: 200,
  headers: { "Content-Type": "application/json" },
});

/**
 * Value of a preference as the web service accepts it (strings; null clears it).
 *
 * @param {*} value
 * @returns {string}
 */
const preferenceValue = (value) => {
  if (value === null || value === undefined) {
    return "";
  }
  return typeof value === "object" ? JSON.stringify(value) : String(value);
};

/**
 * Repeats a failed user preference request through the AJAX web services.
 *
 * @param {string} component
 * @param {string} action
 * @param {object} options
 * @returns {Promise<Response>|null} null when the request is not a user preference request
 */
const preferenceFallback = (component, action, options) => {
  if (component.replace(/^core_/, "") !== "user") {
    return null;
  }
  const match = PREFERENCES.exec(action);
  if (!match) {
    return null;
  }
  const name = match[1] ? decodeURIComponent(match[1]) : null;
  const method = (options.method || "GET").toUpperCase();

  if (method === "GET") {
    return fetchOne("core_user_get_user_preferences", { name: name || "" }).then((result) => {
      const values = {};
      (result.preferences || []).forEach((preference) => {
        values[preference.name] = preference.value;
      });
      if (name) {
        return jsonResponse({ [name]: Object.prototype.hasOwnProperty.call(values, name) ? values[name] : null });
      }
      return jsonResponse(values);
    });
  }

  if (method === "POST" || method === "PUT" || method === "PATCH") {
    const body = parseBody(options.body);
    const values = name ? { [name]: body.value } : (body.preferences || {});
    const preferences = Object.keys(values).map((key) => ({
      name: key,
      value: preferenceValue(values[key]),
      userid: 0,
    }));
    return fetchOne("core_user_set_user_preferences", { preferences }).then((result) => {
      if (result.warnings && result.warnings.length) {
        throw new Error(result.warnings[0].message);
      }
      return jsonResponse(values);
    });
  }

  return null;
};

/** Question bank endpoints of the REST API (core_question\route\api\bank) served by theme_eadtraining_question_api. */
const QUESTION_ACTIONS = ["counts", "banks", "categories"];

/**
 * Sends a question bank request (GET question/counts, question/banks, question/categories) to the AJAX web service.
 *
 * @param {string} component
 * @param {string} action may hold a query string, e.g. "counts?course=2"
 * @param {object} options
 * @returns {Promise<Response>|null} null when the request is not one of these
 */
const questionRequest = (component, action, options) => {
  if (component.replace(/^core_/, "") !== "question" || (options.method || "GET").toUpperCase() !== "GET") {
    return null;
  }
  const [path, query] = action.split("?");
  if (!QUESTION_ACTIONS.includes(path)) {
    return null;
  }
  const params = Object.assign(Object.fromEntries(new URLSearchParams(query || "")), options.params || {});
  const args = { action: path };
  ["course", "currentmodule", "coursemodule"].forEach((key) => {
    if (params[key] !== undefined && params[key] !== null && params[key] !== "") {
      args[key] = parseInt(params[key], 10) || 0;
    }
  });
  ["includeshared", "includerecent", "includeprivate"].forEach((key) => {
    if (params[key] !== undefined && params[key] !== null) {
      args[key] = params[key] === true || params[key] === "true" || params[key] === "1" || params[key] === 1;
    }
  });
  return fetchOne("theme_eadtraining_question_api", args).then((json) => new Response(json, {
    status: 200,
    headers: { "Content-Type": "application/json" },
  }));
};

export default class Fetch extends CoreFetch {
  /**
   * Make a single request to the Moodle API (see core/fetch), with the user preference fallback.
   *
   * @param {string} component
   * @param {string} action
   * @param {object} options
   * @returns {Promise<Response>}
   */
  static async request(component, action, options = {}) {
    // User preferences: always the AJAX web services, so they work whatever the REST router set-up of the server.
    // Likewise the question bank counts, banks and categories.
    const handled = preferenceFallback(component, action, options) || questionRequest(component, action, options);
    if (handled) {
      return handled;
    }
    try {
      return await super.request(component, action, options);
    } catch (error) {
      if (error instanceof Error) {
        throw error;
      }
      // Core rejects with the HTTP status text, which is empty over HTTP/2 (shown as "undefined").
      throw new Error(error ? String(error) : `The request ${component}/${action} failed. Please reload the page and try again.`);
    }
  }
}
