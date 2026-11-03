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
 * Preselects the page a banner button opens from its label, until the creator picks one.
 * Same matching as theme_eadtraining\link_map::suggest().
 *
 * @module     theme_eadtraining/link_picker
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    /**
     * Lowercase words of a text.
     *
     * @param {String} text
     * @return {String[]}
     */
    var words = function(text) {
        return String(text || "").toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(function(word) {
            return word !== "";
        });
    };

    /**
     * Route key suggested for a label, or null.
     *
     * @param {String} label
     * @param {Object} keywords route key => keywords
     * @return {String|null}
     */
    var suggest = function(label, keywords) {
        var labelwords = words(label);
        var best = null;
        var bestscore = 0;
        Object.keys(keywords).forEach(function(key) {
            keywords[key].forEach(function(keyword) {
                var keywordwords = words(keyword);
                var matches = keywordwords.length && keywordwords.every(function(word) {
                    return labelwords.indexOf(word) !== -1;
                });
                if (matches && keyword.length > bestscore) {
                    best = key;
                    bestscore = keyword.length;
                }
            });
        });
        return best;
    };

    return {
        suggest: suggest,

        /**
         * Links a label input to a destination select.
         *
         * @param {String} labelid
         * @param {String} selectid
         * @param {Object} keywords route key => keywords
         */
        init: function(labelid, selectid, keywords) {
            var label = document.getElementById(labelid);
            var select = document.getElementById(selectid);
            if (!label || !select) {
                return;
            }
            // Follow the label while the destination is the suggested one (or the default of a new button).
            var auto = !label.value.trim() || suggest(label.value, keywords) === select.value;

            label.addEventListener("input", function() {
                if (!auto) {
                    return;
                }
                var key = suggest(label.value, keywords);
                if (key && select.querySelector('option[value="' + key + '"]')) {
                    select.value = key;
                    select.dispatchEvent(new Event("change", {bubbles: true}));
                }
            });
            select.addEventListener("change", function(event) {
                if (event.isTrusted) {
                    auto = false;
                }
            });
        }
    };
});
