// Per-user Appearance panel: applies choices instantly and saves them as user preferences.
import {setUserPreference} from 'core_user/repository';
import Notification from 'core/notification';

const PREF = 'theme_modernui_';
const root = document.documentElement;

const toRgb = (hex) => {
    const h = hex.replace('#', '');
    return [0, 2, 4].map((i) => parseInt(h.substring(i, i + 2), 16));
};
const toHex = (rgb) => '#' + rgb.map((v) => Math.round(v).toString(16).padStart(2, '0')).join('').toUpperCase();
const mix = (hex, target, p) => toHex(toRgb(hex).map((v) => v + (target - v) * p));
const lum = (hex) => {
    const c = toRgb(hex).map((v) => {
        v /= 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
};
const onColor = (hex) => {
    const l = lum(hex);
    return (1.05 / (l + 0.05)) >= ((l + 0.05) / 0.0587) ? '#FFFFFF' : '#0F172A';
};

const ACCENT_PROPS = ['--mu-primary', '--mu-primary-rgb', '--mu-primary-hover', '--mu-on-primary',
    '--mu-lift', '--mu-lift-rgb', '--mu-lift-hover', '--mu-on-lift'];

const applyAccent = (hex) => {
    if (!hex) {
        ACCENT_PROPS.forEach((p) => root.style.removeProperty(p));
        return;
    }
    const lift = mix(hex, 255, 0.55);
    const set = (k, v) => root.style.setProperty(k, v);
    set('--mu-primary', hex);
    set('--mu-primary-rgb', toRgb(hex).join(','));
    set('--mu-primary-hover', mix(hex, 0, 0.12));
    set('--mu-on-primary', onColor(hex));
    set('--mu-lift', lift);
    set('--mu-lift-rgb', toRgb(lift).join(','));
    set('--mu-lift-hover', mix(lift, 0, 0.12));
    set('--mu-on-lift', onColor(lift));
};

const applyMode = (mode) => {
    const dark = mode === 'dark' || (mode === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    root.setAttribute('data-mu-mode', mode);
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
};

const save = (name, value) => setUserPreference(PREF + name, value).catch(Notification.exception);

export const init = (cfg) => {
    const toggle = document.getElementById('mu-appearance-toggle');
    const panel = document.getElementById('mu-appearance-panel');
    if (!toggle || !panel) {
        return;
    }
    const state = {...cfg};

    const radios = (name) => panel.querySelectorAll('input[name="mu-' + name + '"]');
    const syncUi = () => {
        ['mode', 'fontsize', 'density'].forEach((n) => {
            radios(n).forEach((r) => {
                r.checked = r.value === state[n];
            });
        });
        panel.querySelector('#mu-motion').checked = !!state.motion;
        panel.querySelector('#mu-contrast').checked = !!state.contrast;
        panel.querySelectorAll('.mu-swatch').forEach((s) => {
            s.setAttribute('aria-pressed', String(!!state.accent && s.dataset.color.toUpperCase() === state.accent.toUpperCase()));
        });
        if (state.accent) {
            panel.querySelector('#mu-custom-color').value = state.accent.toLowerCase();
        }
    };

    const open = (show) => {
        panel.hidden = !show;
        toggle.setAttribute('aria-expanded', String(show));
        if (show) {
            const first = panel.querySelector('input, button');
            if (first) {
                first.focus();
            }
        } else {
            toggle.focus();
        }
    };
    toggle.addEventListener('click', () => open(panel.hidden));
    panel.querySelector('#mu-appearance-close').addEventListener('click', () => open(false));
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) {
            open(false);
        }
    });

    radios('mode').forEach((r) => r.addEventListener('change', () => {
        state.mode = r.value;
        applyMode(r.value);
        save('mode', r.value);
    }));
    radios('fontsize').forEach((r) => r.addEventListener('change', () => {
        state.fontsize = r.value;
        root.setAttribute('data-mu-fontsize', r.value);
        save('fontsize', r.value);
    }));
    radios('density').forEach((r) => r.addEventListener('change', () => {
        state.density = r.value;
        root.setAttribute('data-mu-density', r.value);
        save('density', r.value);
    }));
    panel.querySelector('#mu-motion').addEventListener('change', (e) => {
        state.motion = e.target.checked;
        root.setAttribute('data-mu-motion', e.target.checked ? 'reduce' : 'normal');
        save('motion', e.target.checked ? 1 : null);
    });
    panel.querySelector('#mu-contrast').addEventListener('change', (e) => {
        state.contrast = e.target.checked;
        root.setAttribute('data-mu-contrast', e.target.checked ? 'high' : 'normal');
        save('contrast', e.target.checked ? 1 : null);
    });

    const setAccent = (hex) => {
        state.accent = hex;
        applyAccent(hex);
        save('accent', hex ? hex.replace('#', '') : null);
        syncUi();
    };
    panel.querySelectorAll('.mu-swatch').forEach((s) => {
        s.addEventListener('click', () => setAccent(s.dataset.color.toUpperCase()));
    });
    panel.querySelector('#mu-custom-color').addEventListener('change', (e) => setAccent(e.target.value.toUpperCase()));

    panel.querySelector('#mu-reset').addEventListener('click', () => {
        Object.assign(state, {
            mode: cfg.defaultmode,
            accent: '',
            fontsize: 'medium',
            density: 'comfortable',
            motion: false,
            contrast: false,
        });
        applyMode(state.mode);
        applyAccent('');
        root.setAttribute('data-mu-fontsize', 'medium');
        root.setAttribute('data-mu-density', 'comfortable');
        root.setAttribute('data-mu-motion', 'normal');
        root.setAttribute('data-mu-contrast', 'normal');
        ['mode', 'accent', 'fontsize', 'density', 'motion', 'contrast'].forEach((n) => save(n, null));
        syncUi();
    });

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
            if (state.mode === 'auto') {
                applyMode('auto');
            }
        });
    }
    syncUi();
};
