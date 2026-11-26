<?php
namespace theme_modernui\admin;

use theme_modernui\presets;

defined('MOODLE_INTERNAL') || die();

/**
 * Preset cards. Clicking a card fills the colour, font and radius fields (you then press Save).
 * Shows which preset the current field values match, or "Custom".
 */
class setting_presets extends \admin_setting {
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, 'indigo');
    }

    public function get_setting() {
        return $this->config_read($this->name);
    }

    public function write_setting($data) {
        $valid = array_merge(array_keys(presets::all()), ['custom']);
        if (!in_array($data, $valid, true)) {
            $data = 'custom';
        }
        return $this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin');
    }

    public function output_html($data, $query = '') {
        global $PAGE;
        $c = 'theme_modernui';
        $all = presets::all();
        $selected = isset($all[$data]) ? $data : 'indigo';
        $id = $this->get_id();
        $fullname = $this->get_full_name();

        $cards = '';
        foreach ($all as $key => $p) {
            $label = get_string('preset_' . $key, $c);
            $cards .= '<button type="button" class="mu-preset-card btn btn-outline-secondary me-2 mb-2 text-start" '
                . 'data-preset="' . s($key) . '">'
                . '<span class="d-block fw-bold">' . s($label) . '</span>'
                . '<span class="d-flex gap-1 mt-1">'
                . '<span style="width:24px;height:24px;border-radius:50%;background:' . s($p['primary']) . '"></span>'
                . '<span style="width:24px;height:24px;border-radius:50%;background:' . s($p['accent']) . '"></span>'
                . '<span style="width:24px;height:24px;border-radius:50%;border:1px solid var(--bs-border-color);background:' . s($p['background']) . '"></span>'
                . '</span></button>';
        }
        $html = '<div id="mu-presets">'
            . '<input type="hidden" id="' . $id . '" name="' . $fullname . '" value="' . s($selected) . '">'
            . '<div class="d-flex flex-wrap">' . $cards . '</div>'
            . '<p class="mt-2 mb-2"><strong>' . get_string('presetmatches', $c) . ':</strong> '
            . '<span id="mu-preset-status"></span></p>'
            . '<button type="button" class="btn btn-secondary" id="mu-preset-reset">'
            . get_string('presetreset', $c) . '</button>'
            . '</div>';

        $names = [];
        foreach ($all as $key => $p) {
            $names[$key] = get_string('preset_' . $key, $c);
        }
        $config = json_encode([
            'presets' => $all,
            'names' => $names,
            'custom' => get_string('preset_custom', $c),
            'prefix' => 'id_s_theme_modernui_',
            'selected' => $selected,
        ]);
        $PAGE->requires->js_amd_inline("
            (function(cfg){
                var f = function(n){ return document.getElementById(cfg.prefix + n); };
                var hidden = document.getElementById('" . $id . "');
                var status = document.getElementById('mu-preset-status');
                var map = {primary: 'primarycolor', accent: 'accentcolor', background: 'backgroundcolor', font: 'fontfamily', radius: 'radius'};
                var norm = function(v){ return String(v || '').toLowerCase(); };
                var setVal = function(el, v){
                    if (!el) { return; }
                    el.value = v;
                    el.dispatchEvent(new Event('input', {bubbles: true}));
                    el.dispatchEvent(new Event('change', {bubbles: true}));
                };
                var refresh = function(){
                    var found = null;
                    Object.keys(cfg.presets).forEach(function(k){
                        var ok = Object.keys(map).every(function(p){
                            var el = f(map[p]);
                            return el && norm(el.value) === norm(cfg.presets[k][p]);
                        });
                        if (ok) { found = k; }
                    });
                    status.textContent = found ? cfg.names[found] : cfg.custom;
                    hidden.value = found || 'custom';
                };
                var apply = function(key){
                    var p = cfg.presets[key];
                    if (!p) { return; }
                    Object.keys(map).forEach(function(k){ setVal(f(map[k]), p[k]); });
                    hidden.value = key;
                    refresh();
                };
                document.querySelectorAll('.mu-preset-card').forEach(function(b){
                    b.addEventListener('click', function(){ apply(b.getAttribute('data-preset')); });
                });
                document.getElementById('mu-preset-reset').addEventListener('click', function(){
                    apply(cfg.presets[hidden.value] ? hidden.value : cfg.selected);
                });
                Object.keys(map).forEach(function(k){
                    var el = f(map[k]);
                    if (el) { el.addEventListener('change', refresh); el.addEventListener('input', refresh); }
                });
                setTimeout(refresh, 300);
            })(" . $config . ");
        ");

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', null, $query);
    }
}
