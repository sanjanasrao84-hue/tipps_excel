# theme_modernui

A fully customisable child theme of Boost (Moodle 5.3, Bootstrap 5.3.8).

- `parents = ['boost']`. The theme doesn't inherit from `eadtraining` or `eadflix` and doesn't copy their renderers.
- Layouts are inherited from Boost. A file in `layout/` overrides Boost's file of the same name. Right now
  that's `drawers.php` and `login.php`, which render `theme_modernui/drawers` and `theme_modernui/login`.
- SCSS: Boost's default preset, then `scss/modernui.scss`. Outside the token and preset definitions, partials
  may only use `var(--mu-*)`.
- Dark mode builds on Boost's colour-mode engine (`theme_boost\colour_mode`: `data-bs-theme` on `<html>`, the
  `theme_boost_colourmode` user preference, and the head script that resolves "auto" with no flash). It works
  for every theme whose parents include `boost`.
- Setting names must not clash with Boost's (`brandcolor`, `scss`, `scsspre`, `backgroundimage`,
  `loginbackgroundimage`). Boost's pre/extra SCSS callbacks read the child theme's settings too.

See `CHANGES.md` (Step 9) for the admin guide.
