// Per-user Appearance panel: applies choices instantly and saves them as user preferences.
import {setUserPreference} from 'core_user/repository';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Offcanvas from 'theme_boost/bootstrap/offcanvas';

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

const save = (name, value) => setUserPreference(PREF + name, value);

/**
 * Add the "Appearance" item to the user menu, just above the divider before "Log out".
 *
 * Done here rather than by overriding core/user_menu: the menu items come from core and end with Log out, which a
 * Mustache override cannot single out, and a copied core template would drift on every upgrade.
 *
 * @param {HTMLElement} panel The offcanvas panel.
 * @returns {HTMLElement|null} The menu item, or null when the page has no user menu (popup/embedded layouts).
 */
const addMenuItem = (panel) => {
    const menu = document.querySelector('#user-action-menu #carousel-item-main');
    const template = document.getElementById('mu-appearance-menuitem');
    if (!menu || !template) {
        return null;
    }
    const item = template.content.firstElementChild.cloneNode(true);
    const logout = menu.querySelector('a[href*="/login/logout.php"]');
    let anchor = logout;
    if (logout && logout.previousElementSibling && logout.previousElementSibling.classList.contains('dropdown-divider')) {
        anchor = logout.previousElementSibling;
    }
    if (anchor) {
        menu.insertBefore(item, anchor);
    } else {
        menu.appendChild(item);
    }
    item.addEventListener('click', (e) => {
        e.preventDefault();
        Offcanvas.getOrCreateInstance(panel).show();
    });
    return item;
};

export const init = (cfg) => {
    const panel = document.getElementById('mu-appearance-panel');
    if (!panel || !addMenuItem(panel)) {
        return;
    }
    // The opener sat in a dropdown that has closed by now, so hand focus back to the user menu button.
    panel.addEventListener('hidden.bs.offcanvas', () => {
        const usermenu = document.getElementById('user-menu-toggle');
        if (usermenu) {
            usermenu.focus();
        }
    });
    const status = panel.querySelector('#mu-appearance-status');
    const announce = (key) => getString(key, 'theme_modernui').then((text) => {
        status.textContent = text;
        return text;
    }).catch(Notification.exception);
    const persist = (name, value) => save(name, value)
        .then(() => announce('appearance_saved'))
        .catch(Notification.exception);
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
        panel.querySelectorAll('.mu-swatch[data-color]').forEach((s) => {
            const pressed = !!state.accent && s.dataset.color.toUpperCase() === state.accent.toUpperCase();
            s.setAttribute('aria-pressed', String(pressed));
        });
        if (state.accent) {
            panel.querySelector('#mu-custom-color').value = state.accent.toLowerCase();
        }
    };

    radios('mode').forEach((r) => r.addEventListener('change', () => {
        state.mode = r.value;
        applyMode(r.value);
        persist('mode', r.value);
    }));
    radios('fontsize').forEach((r) => r.addEventListener('change', () => {
        state.fontsize = r.value;
        root.setAttribute('data-mu-fontsize', r.value);
        persist('fontsize', r.value);
    }));
    radios('density').forEach((r) => r.addEventListener('change', () => {
        state.density = r.value;
        root.setAttribute('data-mu-density', r.value);
        persist('density', r.value);
    }));
    panel.querySelector('#mu-motion').addEventListener('change', (e) => {
        state.motion = e.target.checked;
        root.setAttribute('data-mu-motion', e.target.checked ? 'reduce' : 'normal');
        persist('motion', e.target.checked ? 1 : null);
    });
    panel.querySelector('#mu-contrast').addEventListener('change', (e) => {
        state.contrast = e.target.checked;
        root.setAttribute('data-mu-contrast', e.target.checked ? 'high' : 'normal');
        persist('contrast', e.target.checked ? 1 : null);
    });

    const setAccent = (hex) => {
        state.accent = hex;
        applyAccent(hex);
        persist('accent', hex ? hex.replace('#', '') : null);
        syncUi();
    };
    panel.querySelectorAll('.mu-swatch[data-color]').forEach((s) => {
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
        Promise.all(['mode', 'accent', 'fontsize', 'density', 'motion', 'contrast'].map((n) => save(n, null)))
            .then(() => announce('appearance_resetdone'))
            .catch(Notification.exception);
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
