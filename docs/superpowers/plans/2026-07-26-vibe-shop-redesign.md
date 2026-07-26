# vibe_shop Theme Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `design/vibe_shop` as a clear, fast, quiet conversion-first OkayCMS storefront theme — monochrome chrome, semantic colour, mobile-first, WCAG AA — without breaking any JavaScript, form, admin or module contract.

**Architecture:** A new token layer (`tokens.css`) bridges the admin-editable `--okay-*` variables into a semantic scale that every component consumes. `base.css` carries the reset and typography, and `components.css` is a brand-new file holding the component layer. Both load **after** every legacy sheet, so they win without any specificity tricks. The four legacy sheets (`okay.css`, `theme.css`, `media.css`, `mobile_menu.css`) keep their original relative order untouched, shrink progressively as components are reimplemented, and are deleted in Task 8 — so the site is never half-styled and no legacy interdependency is disturbed on the way.

**Tech Stack:** Smarty 4 templates, plain CSS with custom properties, vanilla JS + jQuery 3.4 (`okay.js` untouched), Swiper, noUiSlider, select2. No build step — OkayCMS concatenates and caches CSS itself.

**Reference spec:** `docs/superpowers/specs/2026-07-26-vibe-shop-redesign-design.md`
**Project context:** `PRODUCT.md`

---

## Global Constraints

Every task's requirements implicitly include this section. The first four are properties of the
OkayCMS CSS compiler that were verified by reading
`Okay/Core/TemplateConfig/CssConfig.php`. They are silent: violating them produces CSS that
looks correct in the source file and is wrong in the browser.

**C1 — Comments occupy whole lines. Never put a comment on the same line as a declaration.**
`CssConfig::compileFile()` truncates a line at `/*`, then, if the same line also contains `*/`,
*overwrites* the result with only the text following `*/`. So `color: red; /* note */` compiles
to nothing at all — the declaration is silently deleted.

**C2 — A line referencing an `--okay-*` variable must contain exactly one `var()`, no fallback,
in the shape `property: var(--okay-x);`.** `CssConfig::setCssVariables()` extracts the variable
name with a per-line regex and substitutes the value from `theme-settings.css`. Two `var()`
calls on one line, or `var(--okay-bg, #fff)` with a fallback, make the regex capture garbage,
the lookup miss, and the substitution silently not happen — leaving an undefined variable,
because `theme-settings.css` is never served to the browser.

Corollary: **`--okay-*` variables may be referenced only inside `tokens.css`.** Everywhere else
uses the `--vs-*` semantic tokens, which are real CSS custom properties served in the compiled
output and resolved natively by the browser. Multiple `var(--vs-*)` on one line are fine.

**C3 — Never break a selector across lines except immediately after a comma.** The compiler
`rtrim`s each line and joins them with no separator, so

```
.a
.b { }
```

compiles to `.a.b { }` — a different selector. `.a,` / `.b {` on two lines is safe.

**C4 — `theme-settings.css` contains exactly one `:root` rule set.**
`CssConfig::initCssVariables()` flattens every rule set into one map keyed by variable name, and
the admin panel's save path writes the chosen value into all of them. A second rule set
declaring the same names (a dark theme, a media query) would be corrupted on first save.

**C5 — The 89 `fn_*` classes are a JavaScript contract.** They may not be renamed, removed, or
given styling. Baseline is captured in Task 1 and diffed at the end of every later task.

**C6 — Form contracts are frozen:** `name=`, `action=`, `<option value>`, and the `data-*`
attributes `okay.js` reads (`data-price`, `data-stock`, `data-cprice`, `data-discount`,
`data-sku`, `data-id`, `data-result-text`, `data-language`).

**C7 — Generic component class names survive.** `.button`, `.boxed`, `.block`, `.block__title`,
form-control classes, `.tabs`, `.accordion`, `.popup`, `.table` are rendered by module templates
outside this theme. They get restyled, never dropped.

**C8 — Grid utilities stay.** `grid.css` keeps `f_row`, `f_col-*`, `d-flex`, `align-items-*`,
`hidden-*`, `.container` (max-width 1366px) and `.container-less` (860px). Module templates use
them.

**C9 — Nothing outside `design/vibe_shop/` is modified,** with one sanctioned exception:
`config/config.local.php` gets `smarty_force_compile = true` for the duration of the work
(Task 1) and is reverted in Task 9. No core file, no backend file, no other theme, no module.

**C10 — No raw values in components.** No colour, shadow, radius, duration or font stack appears
outside `tokens.css`. This is what keeps the palette coherent and leaves a dark theme as a
future one-rule-set addition rather than a refactor.

**C11 — Anti-references are binding.** No cream/sand/beige canvas, no gradient text, no glass
cards, no tracked-uppercase eyebrow above every section, no `border-left` colour stripes, no
nested cards. See `PRODUCT.md` → Anti-references.

### Environment

- Storefront: `http://localhost/` (already serves the `vibe_shop` theme; no hosts entry needed)
- Admin: `http://localhost/admin`, `admin` / `1234`
- CSS is compiled to `cache/css/` with a content hash, so stylesheet edits appear on reload with
  no cache clearing.
- Template edits do **not** appear until `compiled/` is cleared, unless
  `smarty_force_compile = true` (Task 1 sets it).

### Verification loop (used by every task)

```bash
# 1. fn_* contract intact
grep -oh "fn_[a-z_0-9]*" design/vibe_shop/html/*.tpl | sort -u > /tmp/vibe-fn-now.txt
comm -23 /tmp/vibe-fn-baseline.txt /tmp/vibe-fn-now.txt   # MUST be empty

# 2. compiler landmines (C1, C2)
grep -nE '[^ \t].*;.*/\*' design/vibe_shop/css/*.css                    # MUST be empty (C1)
grep -n 'var(--okay-' design/vibe_shop/css/*.css | grep -v '^design/vibe_shop/css/tokens.css'   # MUST be empty (C2)
grep -nE 'var\(--okay-[a-z-]+ *,' design/vibe_shop/css/tokens.css       # MUST be empty (C2, no fallbacks)

# 3. no raw values leaked into components (C10)
grep -nE '#[0-9a-fA-F]{3,8}\b' design/vibe_shop/css/components.css design/vibe_shop/css/base.css   # MUST be empty
```

Then in the browser, via chrome-devtools MCP: `navigate_page` to the task's pages,
`resize_page` to 375×812 and 1440×900, `take_screenshot` at both, and `list_console_messages`
to confirm no new errors.

---

## File Structure

```
design/vibe_shop/
  css/
    tokens.css          NEW   design tokens + the --okay-* bridge. Only file with raw values.
    base.css            NEW   reset, typography, focus ring, link and form baseline
    components.css      NEW   the component layer; loads dead last
    vendor.css          NEW (Task 8)   noUiSlider, swiper, loader, lazyload, readmore
    grid.css            KEPT  utilities (C8)
    okay.css            LEGACY, shrinks across Tasks 2-7, deleted in Task 8
    theme.css           LEGACY, shrinks across Tasks 2-7, deleted in Task 8
    media.css           LEGACY, shrinks across Tasks 2-7, deleted in Task 8
    mobile_menu.css     LEGACY, folded into components.css in Task 2, deleted in Task 8
    theme-settings.css  17 unchanged names, new values (Task 1)
  js/
    vibe.js             NEW   bottom sheet, sticky buy bar, quantity steppers, touch fallbacks
    okay.js             UNTOUCHED
  fonts/inter/          NEW   InterVariable.woff2
  html/*.tpl            restructured per task
  css.php, js.php       registration order updated
```

`css.php` order during the migration — the new files go last, the legacy block is not reordered
internally:

```
tokens.css,
  grid.css, okay.css, theme.css, select2.min.css, media.css, mobile_menu.css,   <- legacy, untouched
base.css, components.css                                                        <- ours, appended
```

Two rules produced this, and both were learned by breaking them:

- **`base.css` must come after `okay.css`.** Both style bare `body`, `h1`–`h4` and `a` at
  identical specificity, so an earlier `base.css` loses and the Inter/15px/1.55 typography
  silently never applies — the page keeps rendering Montserrat 14px/1.4 and the whole task looks
  like it did nothing.
- **The legacy sheets keep their original relative order.** `media.css` and `mobile_menu.css`
  contain mobile overrides that depend on coming *after* the old `theme.css`; moving `theme.css`
  past them resurrects unconditional desktop rules (`.main_banner` was the one that surfaced) and
  breaks mobile layout. This is why the new component layer is a **separate file** rather than a
  rewritten `theme.css`: one file cannot be both the legacy sheet that must stay early and the
  new layer that must come last.

Final order after the Task 8 teardown, once the legacy block is gone:

```
tokens.css, grid.css, vendor.css, select2.min.css, base.css, components.css
```

---

## Task 1: Foundation — branch, fonts, tokens, base

**Files:**
- Create: `design/vibe_shop/css/tokens.css`
- Create: `design/vibe_shop/css/base.css`
- Create: `design/vibe_shop/fonts/inter/InterVariable.woff2`
- Modify: `design/vibe_shop/css/theme-settings.css` (values only)
- Modify: `design/vibe_shop/css.php`
- Modify: `design/vibe_shop/html/head.tpl:5-50` (font preloads and `@font-face`)
- Modify: `config/config.local.php:56`

**Interfaces:**
- Consumes: nothing.
- Produces: the complete `--vs-*` token vocabulary that every later task uses. Names are fixed
  here; later tasks must not invent new tokens without adding them to `tokens.css`.

- [ ] **Step 1: Branch and capture the `fn_*` baseline**

```bash
cd /home/sviat/projects/OkayCMS
git checkout -b feature/vibe-shop-redesign
grep -oh "fn_[a-z_0-9]*" design/vibe_shop/js/*.js design/vibe_shop/html/*.tpl \
  | sort -u > /tmp/vibe-fn-baseline.txt
wc -l /tmp/vibe-fn-baseline.txt
```

Expected: 89 or more lines. Keep this file for the whole job.

- [ ] **Step 2: Enable template recompilation**

Set `smarty_force_compile = true` in `config/config.local.php:56`. Reverted in Task 9.

```bash
rm -rf compiled/vibe_shop/*
```

- [ ] **Step 3: Download Inter**

```bash
mkdir -p design/vibe_shop/fonts/inter
curl -sSfo design/vibe_shop/fonts/inter/InterVariable.woff2 \
  https://rsms.me/inter/font-files/InterVariable.woff2
ls -l design/vibe_shop/fonts/inter/InterVariable.woff2
```

Expected: a file of roughly 300–400 KB. If the download fails, stop and report — do not
substitute a different family; the fallback decision is the user's.

- [ ] **Step 4: Write `theme-settings.css`**

Exactly one `:root` rule set (C4), the same seventeen names, new values.

```css
/**
* Файл стилей для настройки шаблона.
* Регистрировать этот файл для подключения в шаблоне не нужно
*/

:root {
	--okay-button-color: #17171a;
	--okay-button-text: #ffffff;
	--okay-button-color-hover: #2b2b2f;
	--okay-button-text-hover: #ffffff;
	--okay-basic-company: #17171a;
	--okay-second-company: #17171a;
	--okay-basic-company-text: #ffffff;
	--okay-second-company-text: #f5f5f6;
	--okay-bg: #f5f5f6;
	--okay-body-text: #1c1c20;
	--okay-body-heading: #1c1c20;
	--okay-boxed-color: #ffffff;
	--okay-boxed-text: #1c1c20;
	--okay-button-second-color: #ffffff;
	--okay-button-second-text: #1c1c20;
	--okay-shadow-color: 0 2px 4px rgba(23,23,26,.05), 0 8px 16px -4px rgba(23,23,26,.08);
	--okay-border-color: #dcdcde;
}
```

- [ ] **Step 5: Write `tokens.css`**

Note the bridge block: one `var(--okay-*)` per line, no fallbacks, nothing else on the line
(C2). Comments sit on their own lines (C1).

```css
/**
* vibe_shop design tokens.
* The only file in this theme that may contain raw colour, shadow, radius,
* duration or font-stack values.
*
* Dark-theme rule sets, if ever added, belong HERE and never in
* theme-settings.css: CssConfig flattens every rule set in that file into one
* variable map, so the admin panel would overwrite both themes at once.
*/

:root {
	--vs-n-0: #ffffff;
	--vs-n-25: #fafafa;
	--vs-n-50: #f5f5f6;
	--vs-n-100: #ededee;
	--vs-n-200: #e0e0e2;
	--vs-n-300: #cbcbce;
	--vs-n-400: #a5a5aa;
	--vs-n-500: #6b6b71;
	--vs-n-600: #5c5c62;
	--vs-n-700: #45454a;
	--vs-n-800: #2b2b2f;
	--vs-n-900: #1c1c20;
	--vs-n-950: #17171a;

	--vs-sale-50: #fff1f4;
	--vs-sale-500: #e11d48;
	--vs-sale-600: #be1739;
	--vs-ok-50: #ecfdf5;
	--vs-ok-600: #047857;
	--vs-warn-50: #fffbeb;
	--vs-warn-600: #b45309;

	--vs-canvas: var(--okay-bg);
	--vs-surface: var(--okay-boxed-color);
	--vs-border: var(--okay-border-color);
	--vs-text: var(--okay-body-text);
	--vs-heading: var(--okay-body-heading);
	--vs-ink: var(--okay-second-company);
	--vs-ink-text: var(--okay-second-company-text);
	--vs-accent: var(--okay-basic-company);
	--vs-accent-text: var(--okay-basic-company-text);
	--vs-cta: var(--okay-button-color);
	--vs-cta-hover: var(--okay-button-color-hover);
	--vs-cta-text: var(--okay-button-text);
	--vs-cta-2: var(--okay-button-second-color);
	--vs-cta-2-text: var(--okay-button-second-text);

	--vs-surface-subtle: #ededee;
	--vs-hairline: #e0e0e2;
	--vs-border-strong: #a5a5aa;
	--vs-text-muted: #6b6b71;

	--vs-font-ui: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
	--vs-font-display: 'Montserrat', 'Inter', system-ui, sans-serif;

	--vs-text-xs: 0.75rem;
	--vs-text-sm: 0.8125rem;
	--vs-text-base: 0.9375rem;
	--vs-text-lg: 1.0625rem;
	--vs-text-xl: clamp(1.125rem, 1rem + 0.6vw, 1.375rem);
	--vs-text-2xl: clamp(1.375rem, 1.1rem + 1.2vw, 1.875rem);
	--vs-text-3xl: clamp(1.75rem, 1.3rem + 2vw, 2.75rem);
	--vs-leading-tight: 1.2;
	--vs-leading-body: 1.55;

	--vs-space-1: 0.25rem;
	--vs-space-2: 0.5rem;
	--vs-space-3: 0.75rem;
	--vs-space-4: 1rem;
	--vs-space-5: 1.25rem;
	--vs-space-6: 1.5rem;
	--vs-space-7: 2rem;
	--vs-space-8: 2.5rem;
	--vs-space-9: 3rem;
	--vs-space-10: 4rem;
	--vs-space-11: 5rem;
	--vs-space-12: 6rem;

	--vs-radius-sm: 0.5rem;
	--vs-radius-md: 0.75rem;
	--vs-radius-lg: 1rem;
	--vs-radius-xl: 1.5rem;
	--vs-radius-full: 999px;

	--vs-shadow-1: 0 1px 2px rgb(23 23 26 / .06), 0 1px 3px rgb(23 23 26 / .04);
	--vs-shadow-2: 0 2px 4px rgb(23 23 26 / .05), 0 8px 16px -4px rgb(23 23 26 / .08);
	--vs-shadow-3: 0 4px 8px rgb(23 23 26 / .06), 0 16px 32px -8px rgb(23 23 26 / .14);

	--vs-dur-1: 120ms;
	--vs-dur-2: 200ms;
	--vs-dur-3: 320ms;
	--vs-ease: cubic-bezier(.2, .7, .3, 1);

	--vs-z-dropdown: 100;
	--vs-z-sticky: 200;
	--vs-z-backdrop: 300;
	--vs-z-modal: 400;
	--vs-z-toast: 500;
}
```

- [ ] **Step 6: Write `base.css`**

Reset, typography, link treatment, focus ring, form baseline, reduced motion. The two-tone
focus ring stays visible on both the light canvas and the ink header.

```css
*,
*::before,
*::after {
	box-sizing: border-box;
}

html {
	-webkit-text-size-adjust: 100%;
	text-size-adjust: 100%;
}

body {
	margin: 0;
	background-color: var(--vs-canvas);
	color: var(--vs-text);
	font-family: var(--vs-font-ui);
	font-size: var(--vs-text-base);
	line-height: var(--vs-leading-body);
	font-weight: 400;
	-webkit-font-smoothing: antialiased;
}

h1,
h2,
h3,
h4 {
	margin: 0 0 var(--vs-space-4);
	color: var(--vs-heading);
	font-family: var(--vs-font-display);
	font-weight: 600;
	line-height: var(--vs-leading-tight);
	letter-spacing: -0.01em;
	text-wrap: balance;
}

h1 { font-size: var(--vs-text-3xl); }
h2 { font-size: var(--vs-text-2xl); }
h3 { font-size: var(--vs-text-xl); }
h4 { font-size: var(--vs-text-lg); }

p {
	margin: 0 0 var(--vs-space-4);
	text-wrap: pretty;
	max-width: 70ch;
}

a {
	color: inherit;
	text-decoration-color: var(--vs-border-strong);
	text-underline-offset: 0.2em;
	transition: text-decoration-color var(--vs-dur-1) var(--vs-ease);
}

a:hover {
	text-decoration-color: currentColor;
}

img {
	max-width: 100%;
	height: auto;
}

:focus-visible {
	outline: none;
	box-shadow: 0 0 0 2px var(--vs-canvas), 0 0 0 4px var(--vs-ink);
	border-radius: var(--vs-radius-sm);
}

button,
input,
select,
textarea {
	font: inherit;
	color: inherit;
}

.vs-tabular {
	font-variant-numeric: tabular-nums;
}

@media (prefers-reduced-motion: reduce) {
	*,
	*::before,
	*::after {
		animation-duration: 0.01ms !important;
		animation-iteration-count: 1 !important;
		transition-duration: 0.01ms !important;
		scroll-behavior: auto !important;
	}
}
```

- [ ] **Step 7: Rewrite the font block in `head.tpl`**

Replace lines 5–50 (the four Montserrat preloads and their four `@font-face` blocks) with one
Inter preload plus Montserrat SemiBold, and drop the Montserrat Regular/Medium faces — body
text is Inter now, so those two files stop being referenced.

Leave both `cdnjs` `<link>` tags at `head.tpl:52-53` alone in this task. The font-awesome link
is removed in Task 2, in the same step that replaces the icons it serves — removing it here
would leave every `fa fa-*` glyph blank until Task 2 lands. The fancybox link stays permanently:
the local `jquery.fancybox.min.css` is 0 bytes and commented out of `css.php`, so the CDN copy
is the one actually styling the gallery lightbox and the comparison image viewer.

```smarty
    {* Include fonts *}
    <link href="{$rootUrl}/design/{$settings->theme}/fonts/inter/InterVariable.woff2" rel="preload" as="font" crossorigin="anonymous" type="font/woff2">
    <link href="{$rootUrl}/design/{$settings->theme}/fonts/montserrat/Montserrat-SemiBold.woff2" rel="preload" as="font" crossorigin="anonymous" type="font/woff2">
    <style>
        @font-face {
            font-family: 'Inter';
            font-display: swap;
            src: url('{$rootUrl}/design/{$settings->theme}/fonts/inter/InterVariable.woff2') format('woff2');
            font-weight: 100 900;
            font-style: normal;
        }
        @font-face {
            font-family: 'Montserrat';
            font-display: swap;
            src: url('{$rootUrl}/design/{$settings->theme}/fonts/montserrat/Montserrat-SemiBold.woff2') format('woff2'),
                 url('{$rootUrl}/design/{$settings->theme}/fonts/montserrat/Montserrat-SemiBold.woff') format('woff');
            font-weight: 600;
            font-style: normal;
        }
        @font-face {
            font-family: 'Montserrat';
            font-display: swap;
            src: url('{$rootUrl}/design/{$settings->theme}/fonts/montserrat/Montserrat-Bold.woff2') format('woff2'),
                 url('{$rootUrl}/design/{$settings->theme}/fonts/montserrat/Montserrat-Bold.woff') format('woff');
            font-weight: 700;
            font-style: normal;
        }
    </style>
```

Note: this `<style>` block is inline in a `.tpl`, so it is **not** processed by `CssConfig` and
C1/C2/C3 do not apply to it.

- [ ] **Step 8: Update `css.php` registration order**

Also create `design/vibe_shop/css/components.css` as an empty placeholder — Task 2 starts filling
it. Do **not** rewrite the existing `theme.css`; it stays a legacy sheet until Task 8.

```php
return [
    (new Css('tokens.css')),
    (new Css('grid.css')),
    (new Css('okay.css')),
    (new Css('theme.css')),
    (new Css('select2.min.css')),
    (new Css('media.css')),
    (new Css('mobile_menu.css')),
    (new Css('base.css')),
    (new Css('components.css')),
];
```

The rule is simple: **the entire original order is preserved untouched, and the two new files are
appended.** `tokens.css` leads because it only declares custom properties and must be defined
before anything reads them.

Two failures taught this, both worth stating so nobody re-derives them:

- **`base.css` must come after `okay.css`.** Both style bare `body`, `h1`–`h4` and `a` at
  identical specificity, so an earlier `base.css` loses and the Inter/15px/1.55 typography
  silently never applies — the page keeps rendering Montserrat 14px/1.4 and everything looks
  like the task did nothing.
- **The legacy block must not be reordered internally.** `media.css` and `mobile_menu.css` hold
  mobile overrides that depend on coming after the old `theme.css`; moving `theme.css` past them
  resurrects unconditional desktop rules and breaks mobile layout. `.main_banner` surfaces first,
  but it is unlikely to be the only one. `grid.css` stays early for the same reason — legacy
  sheets may deliberately override its utilities.

`okay.css`, `theme.css`, `media.css` and `mobile_menu.css` are removed from this list in Task 8.

- [ ] **Step 9: Verify in the browser**

Run the whole verification loop from Global Constraints. Then, via chrome-devtools:
`navigate_page` to `http://localhost/`, `resize_page` 1440×900 and 375×812, `take_screenshot`
at both, `list_console_messages`.

Expected at this point: the site is still structurally the old layout, but the canvas is
neutral `#f5f5f6`, body text is Inter at 15px/1.55, buttons are near-black, and headings are
Montserrat. Nothing is unstyled or overlapping. Confirm in DevTools that a computed
`background-color` on `body` resolves to `rgb(245, 245, 246)` — that proves the C2 bridge
substitution actually happened rather than silently failing.

- [ ] **Step 10: Verify the admin contract still works**

`navigate_page` to `http://localhost/admin`, log in as `admin` / `1234`, open the theme
settings page, and confirm the nine translated colour swatches render with the new values.
Change one, save, reload the storefront, and confirm the change applies. Then set it back.

This is the only direct test that the `--okay-*` bridge (C2) is intact end to end.

- [ ] **Step 11: Commit**

```bash
git add design/vibe_shop/css/tokens.css design/vibe_shop/css/base.css \
        design/vibe_shop/css/theme-settings.css design/vibe_shop/css.php \
        design/vibe_shop/html/head.tpl design/vibe_shop/fonts/inter \
        config/config.local.php
git commit -m "feat(vibe_shop): add design token layer, Inter, and typographic base"
```

---

## Task 2: Global chrome — header, menus, footer

**Files:**
- Modify: `design/vibe_shop/html/index.tpl:22-175` (header) and the footer block
- Modify: `design/vibe_shop/html/menu.tpl`, `mobile_menu.tpl`, `desktop_categories.tpl`,
  `switcher.tpl`, `user_informer.tpl`, `cart_informer.tpl`, `wishlist_informer.tpl`,
  `comparison_informer.tpl`
- Modify: `design/vibe_shop/html/svg.tpl` (add `chevron`, `compare`, `heart`, `close` symbols)
- Modify: `design/vibe_shop/css/components.css` (start the rewrite here)
- Create: `design/vibe_shop/js/vibe.js`
- Modify: `design/vibe_shop/js.php`
- Delete from: `design/vibe_shop/css/okay.css`, `theme.css`, `media.css`, `mobile_menu.css` — only the rules
  this task replaces

**Interfaces:**
- Consumes: all `--vs-*` tokens from Task 1.
- Produces: `.vs-header`, `.vs-header__bar`, `.vs-nav`, `.vs-search`, `.vs-informer`,
  `.vs-footer`; the button primitives `.vs-btn`, `.vs-btn--primary`, `.vs-btn--secondary`,
  `.vs-btn--ghost`, `.vs-btn--icon`; the overlay primitives `.vs-sheet`, `.vs-sheet__backdrop`;
  and the JS API `window.vibeSheet.open(el)` / `window.vibeSheet.close(el)`. Tasks 3–7 reuse all
  of these rather than defining their own.

- [ ] **Step 1: Replace font-awesome icons with the SVG sprite**

The eight `fa fa-*` usages across the templates are purely presentational —
`grep -c "fa-" design/vibe_shop/js/okay.js` returns 0, so no script depends on them. Add the
missing symbols to `svg.tpl` (`chevron`, `compare`, `heart`, `heart_filled`, `close`) following
the existing `{if $svgId == "..."}` pattern, then replace each `<i class="fa fa-x"></i>` with
`{include file="svg.tpl" svgId="..."}`. Mapping:

| was | becomes |
| --- | --- |
| `fa-chevron-down` | `chevron` (rotated by CSS where needed) |
| `fa-balance-scale` | `compare` |
| `fa-heart-o` / `fa-heart` | `heart` / `heart_filled` |
| `fa-shopping-cart` | `cart_icon` (already in the sprite) |
| `fa-times` | `close` |
| `fa-bars` | `menu_icon` (already in the sprite) |

Keep every `fn_*` and `data-*` attribute on the elements being changed (C5, C6). The wishlist
and comparison toggles in `product_list.tpl` carry `fn_wishlist` / `fn_comparison` plus
`data-id` and `data-result-text` — those move onto the new markup unchanged.

Once no `fa fa-*` class remains anywhere in `design/vibe_shop/html/`, delete the font-awesome
`<link>` at `head.tpl:53`. Verify before deleting:

```bash
grep -rn "fa fa-\|fa-[a-z]" design/vibe_shop/html/*.tpl | grep -v svg.tpl
```

Expected: no output. Leave the fancybox `<link>` at `head.tpl:52` in place — it styles the
gallery lightbox and the comparison image viewer, and the local copy is an empty file.

- [ ] **Step 2: Build the button primitives in `components.css`**

Every later task depends on these. 44px minimum height satisfies the touch-target rule.

```css
.vs-btn {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	gap: var(--vs-space-2);
	min-height: 44px;
	padding: 0 var(--vs-space-5);
	border: 1px solid transparent;
	border-radius: var(--vs-radius-md);
	font-size: var(--vs-text-sm);
	font-weight: 500;
	line-height: 1;
	text-decoration: none;
	cursor: pointer;
	transition: background-color var(--vs-dur-1) var(--vs-ease), border-color var(--vs-dur-1) var(--vs-ease), transform var(--vs-dur-1) var(--vs-ease);
}

.vs-btn:active {
	transform: scale(0.98);
}

.vs-btn[disabled] {
	opacity: 0.5;
	cursor: not-allowed;
}

.vs-btn--primary {
	background-color: var(--vs-cta);
	color: var(--vs-cta-text);
}

.vs-btn--primary:hover {
	background-color: var(--vs-cta-hover);
}

.vs-btn--secondary {
	background-color: var(--vs-cta-2);
	border-color: var(--vs-border);
	color: var(--vs-cta-2-text);
}

.vs-btn--secondary:hover {
	border-color: var(--vs-border-strong);
}

.vs-btn--ghost {
	background-color: transparent;
	color: var(--vs-text);
}

.vs-btn--ghost:hover {
	background-color: var(--vs-surface-subtle);
}

.vs-btn--icon {
	min-width: 44px;
	padding: 0;
}
```

- [ ] **Step 3: Restructure the desktop header to two bars**

In `index.tpl`, merge the current three-bar structure (`header__top`, `header__center`,
`header__bottom`) into a thin utility bar and one main bar. The utility bar keeps account,
language/currency switcher and callback. The main bar carries logo, catalogue button, search
at full remaining width, and the informers.

Preserve `fn_menu_switch`, `fn_catalog_switch`, `fn_search`, `fn_search_mob`,
`fn_search_toggle`, `fn_header__sticky` and the `data-sticky-*` attributes read by
`sticky.min.js`.

Search gets real width because it is a primary conversion path; today it is squeezed between
buttons.

- [ ] **Step 4: Build the mobile header and menu**

One sticky bar at 375px: menu toggle, logo, search toggle, cart. The mobile menu panel gets the
sheet treatment shared with catalogue filters in Task 4 — build it here as `.vs-sheet` in
`components.css`, since this is where it is first needed:

```css
.vs-sheet {
	position: fixed;
	z-index: var(--vs-z-modal);
	background-color: var(--vs-surface);
	display: flex;
	flex-direction: column;
	max-height: 90dvh;
	transition: transform var(--vs-dur-3) var(--vs-ease);
}

.vs-sheet__backdrop {
	position: fixed;
	inset: 0;
	z-index: var(--vs-z-backdrop);
	background-color: rgb(23 23 26 / .4);
	opacity: 0;
	pointer-events: none;
	transition: opacity var(--vs-dur-3) var(--vs-ease);
}

.vs-sheet__backdrop.is-open {
	opacity: 1;
	pointer-events: auto;
}
```

Move whatever of `mobile_menu.css` is still needed into `components.css` and empty that file.
`mobile_menu.js` stays registered and untouched.

- [ ] **Step 4b: Create `js/vibe.js` with the shared sheet behaviour**

Created here because this is where an overlay is first needed. Tasks 4 and 5 extend the same
file; nothing is moved out of `okay.js`.

```js
(function () {
    'use strict';

    var lastFocused = null;

    function trapFocus(sheet, event) {
        var focusables = sheet.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    window.vibeSheet = {
        open: function (sheet) {
            lastFocused = document.activeElement;
            sheet.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            var focusable = sheet.querySelector('a[href], button:not([disabled]), input');
            if (focusable) focusable.focus();
        },
        close: function (sheet) {
            sheet.classList.remove('is-open');
            document.body.style.overflow = '';
            if (lastFocused) lastFocused.focus();
        }
    };

    document.addEventListener('keydown', function (event) {
        var sheet = document.querySelector('.vs-sheet.is-open');
        if (!sheet) return;
        if (event.key === 'Escape') window.vibeSheet.close(sheet);
        if (event.key === 'Tab') trapFocus(sheet, event);
    });
}());
```

Register it in `js.php`, after `okay.js` so `okay.js` has already bound its handlers:

```php
    (new Js('vibe.js'))->setPosition('footer'),
```

- [ ] **Step 5: Rebuild the footer**

Multi-column layout: navigation, contacts, payment icons (the sprite already has
`visacard_icon`, `mastercard_icon`), subscribe form. Keep `fn_subscribe_form`,
`fn_subscribe_error`, `fn_subscribe_success`.

- [ ] **Step 6: Verify**

Full verification loop. In the browser check `/`, a category page and the product page at both
widths — the header appears on every page, so a regression here is global. Specifically
confirm: the sticky header still sticks on scroll, the catalogue dropdown opens, search
submits, the mobile menu opens and closes, Escape closes it, focus is visible on every header
control, and the console has no new errors.

- [ ] **Step 7: Commit**

```bash
git add design/vibe_shop/
git commit -m "feat(vibe_shop): rebuild header, navigation and footer; drop font-awesome"
```

---

## Task 3: Product card

**Files:**
- Modify: `design/vibe_shop/html/product_list.tpl` (full rewrite of the markup)
- Modify: `design/vibe_shop/css/components.css`
- Delete from: `okay.css`, `theme.css`, `media.css` — the `.product_preview*` rules

**Interfaces:**
- Consumes: `.vs-btn*` from Task 2, all tokens from Task 1.
- Produces: `.vs-card`, `.vs-card__media`, `.vs-card__badges`, `.vs-card__body`,
  `.vs-card__price`, `.vs-stock`, `.vs-badge`, `.vs-badge--sale`, `.vs-badge--hit`,
  `.vs-chip`, `.vs-chip--selected`. Tasks 4 and 7 render grids of `.vs-card`; Task 5 reuses
  `.vs-stock` and `.vs-badge`.

Every `fn_*` hook currently on this template must survive: `fn_product`, `fn_transfer`,
`fn_img`, `fn_wishlist`, `fn_comparison`, `fn_discount_label`, `fn_old_price`, `fn_price`,
`fn_sku`, `fn_variants`, `fn_variant`, `fn_select2`, `fn_is_stock`, `fn_is_preorder`,
`fn_not_preorder`. So must the `hidden`/`hidden-xs-up` toggling classes `okay.js` adds and
removes, and every `data-*` on the `<option>` elements (C6).

- [ ] **Step 1: Fix the media box**

```css
.vs-card__media {
	position: relative;
	display: block;
	aspect-ratio: 4 / 5;
	background-color: var(--vs-surface-subtle);
	border-radius: var(--vs-radius-md);
	overflow: hidden;
}

.vs-card__media img {
	width: 100%;
	height: 100%;
	object-fit: contain;
	transition: transform var(--vs-dur-3) var(--vs-ease);
}

.vs-card:hover .vs-card__media img {
	transform: scale(1.03);
}
```

The fixed ratio is what stops rows from jumping as images load, and `contain` on a plate is what
makes uneven shop photography line up (`PRODUCT.md` principle 5).

- [ ] **Step 2: Clamp the product name to two lines**

```css
.vs-card__name {
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	min-height: calc(2em * var(--vs-leading-body));
	font-size: var(--vs-text-sm);
	color: var(--vs-text);
}
```

The `min-height` reserves both lines so single-line titles do not shorten the card and break
grid alignment.

- [ ] **Step 3: Price row and stock indicator**

Discount *text* uses `--vs-sale-600`; the badge fill uses `--vs-sale-500` with white text. That
split exists because the brighter rose only reaches 4.34:1 as text on the canvas.

```css
.vs-card__price {
	display: flex;
	align-items: baseline;
	gap: var(--vs-space-2);
	font-variant-numeric: tabular-nums;
}

.vs-card__price-current {
	font-size: var(--vs-text-lg);
	font-weight: 600;
	color: var(--vs-text);
}

.vs-card__price-current--sale {
	color: var(--vs-sale-600);
}

.vs-card__price-old {
	font-size: var(--vs-text-sm);
	color: var(--vs-text-muted);
	text-decoration: line-through;
}

.vs-stock {
	display: inline-flex;
	align-items: center;
	gap: var(--vs-space-2);
	font-size: var(--vs-text-xs);
	color: var(--vs-text-muted);
}

.vs-stock::before {
	content: "";
	width: 6px;
	height: 6px;
	border-radius: var(--vs-radius-full);
	background-color: currentColor;
}

.vs-stock--in { color: var(--vs-ok-600); }
.vs-stock--low { color: var(--vs-warn-600); }
```

The dot plus label pairing is deliberate: colour alone must not carry the meaning.

- [ ] **Step 4: Wishlist control with a touch fallback**

```css
.vs-card__wish {
	position: absolute;
	top: var(--vs-space-2);
	right: var(--vs-space-2);
	opacity: 0;
	transition: opacity var(--vs-dur-2) var(--vs-ease);
}

.vs-card:hover .vs-card__wish,
.vs-card__wish:focus-visible,
.vs-card__wish.selected {
	opacity: 1;
}

@media (hover: none) {
	.vs-card__wish {
		opacity: 1;
	}
}
```

Without the `(hover: none)` branch the control is unreachable on a phone — and mobile is the
design target.

- [ ] **Step 5: Variant chips for four or fewer variants**

Keep the `<select class="fn_variant">` in the DOM as the form value carrier (C6) but visually
hide it when rendering chips; clicking a chip sets `select.value` and dispatches
`new Event('change', {bubbles: true})` so `okay.js`'s existing handler recalculates price, SKU
and stock. Append the handler to `js/vibe.js` (created in Task 2).

```smarty
{if $product->variants|count > 1 && $product->variants|count <= 4}
    <div class="vs-chips" role="group" aria-label="{$lang->product_variant|escape}">
        {foreach $product->variants as $v}
            <button type="button" class="vs-chip{if $v@first} vs-chip--selected{/if}" data-variant-id="{$v->id}">{if $v->name}{$v->name|escape}{else}{$product->name|escape}{/if}</button>
        {/foreach}
    </div>
{/if}
```

- [ ] **Step 6: Verify**

Full verification loop, plus by hand in the browser: add to cart from a card, toggle wishlist,
toggle comparison, switch a variant and confirm the price and SKU update, and confirm the
discount badge shows the right percentage. Check a card with no image, a card with a very long
title, and an out-of-stock card. Check the grid at 375px (2 columns) and 1440px (4 columns).

- [ ] **Step 7: Commit**

```bash
git add design/vibe_shop/
git commit -m "feat(vibe_shop): rebuild the product card"
```

---

## Task 4: Catalogue — grid, filters, sorting

**Files:**
- Modify: `design/vibe_shop/html/products.tpl`, `products_content.tpl`, `features.tpl`,
  `products_sort.tpl`, `selected_features.tpl`, `pagination.tpl`, `chpu_pagination.tpl`,
  `top_categories.tpl`, `breadcrumb.tpl`
- Modify: `design/vibe_shop/js/vibe.js`, `design/vibe_shop/css/components.css`

**Interfaces:**
- Consumes: `.vs-card` (Task 3), `.vs-btn*` (Task 2), `.vs-sheet` and `window.vibeSheet`
  (Task 2).
- Produces: `.vs-catalog`, `.vs-catalog__grid`, `.vs-filters`, `.vs-filter-group`, `.vs-sort`,
  `.vs-pagination`, `.vs-applied`, `.vs-skeleton`.

Hooks that must survive: `fn_features`, `fn_selected_features`, `fn_products_sort`,
`fn_sort_pagination_link`, `fn_pagination`, `fn_ajax_content`, `fn_ajax_wait`,
`fn_ajax_buttons`, `fn_categories`, `fn_category_scroll`, `fn_accordion`, and the noUiSlider
price-range markup.

- [ ] **Step 1: Responsive product grid without breakpoints**

```css
.vs-catalog__grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: var(--vs-space-4);
}

@media (min-width: 768px) {
	.vs-catalog__grid {
		grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
		gap: var(--vs-space-6);
	}
}
```

- [ ] **Step 2: Desktop filter rail**

Two-column layout at ≥992px: a 260px filter rail and the grid. Below that the rail is hidden
and its content is moved into the sheet by Step 3 — same markup, so `fn_features` binds once.

- [ ] **Step 3: Wire the mobile filter sheet**

The sheet mechanics — focus trap, scroll lock, Escape to close — already exist as
`window.vibeSheet` from Task 2. This step only binds the filter panel to it and adds the sticky
apply button showing the live result count.

```js
(function () {
    'use strict';
    var sheet = document.querySelector('.vs-filters.vs-sheet');
    if (!sheet) return;

    document.addEventListener('click', function (event) {
        if (event.target.closest('.vs-filters__open')) {
            window.vibeSheet.open(sheet);
        }
        if (event.target.closest('.vs-filters__close') || event.target.closest('.vs-sheet__backdrop')) {
            window.vibeSheet.close(sheet);
        }
    });
}());
```

The filter markup itself is rendered once and moved between the desktop rail and the sheet by
CSS alone, so `fn_features` binds a single time and the AJAX filter path is unaffected.

- [ ] **Step 4: Applied-filter chips and sorting**

Render the already-selected filters from `selected_features.tpl` as removable `.vs-chip`
elements above the grid, and turn `products_sort.tpl` into a segmented control. Keep
`fn_sort_pagination_link` on the sort links so AJAX pagination keeps working.

- [ ] **Step 5: Skeletons for the AJAX region**

```css
.vs-skeleton {
	background-image: linear-gradient(90deg, var(--vs-surface-subtle) 25%, var(--vs-n-200) 37%, var(--vs-surface-subtle) 63%);
	background-size: 400% 100%;
	border-radius: var(--vs-radius-md);
	animation: vs-shimmer 1.4s ease-in-out infinite;
}

@keyframes vs-shimmer {
	0% { background-position: 100% 50%; }
	100% { background-position: 0 50%; }
}
```

Shown while `fn_ajax_wait` is active, hidden when the new content lands. The grid must render
its real content by default and only *add* the skeleton state — never gate visibility on a
class that a script has to remove, or the page ships blank when the script does not run.

- [ ] **Step 6: Verify**

Full verification loop. By hand: apply a filter and confirm AJAX replaces the grid; use the
price slider; open the mobile sheet at 375px, tab through it, close with Escape and confirm
focus returns to the trigger; page through results; sort. Confirm the applied-filter chips
remove correctly.

- [ ] **Step 7: Commit**

```bash
git add design/vibe_shop/
git commit -m "feat(vibe_shop): rebuild catalogue grid, filters and sorting"
```

---

## Task 5: Product page

**Files:**
- Modify: `design/vibe_shop/html/product.tpl` (644 lines — the largest template)
- Modify: `design/vibe_shop/html/browsed_products.tpl`, `selected_features.tpl`,
  `user_comments.tpl`
- Modify: `design/vibe_shop/js/vibe.js`, `design/vibe_shop/css/components.css`

**Interfaces:**
- Consumes: `.vs-btn*`, `.vs-badge`, `.vs-stock`, `.vs-chip`, `window.vibeSheet`.
- Produces: `.vs-pdp`, `.vs-pdp__gallery`, `.vs-buybox`, `.vs-buybox__submit` (the inline
  add-to-cart button the sticky bar observes), `.vs-sticky-buy`, `.vs-tabs`, `.vs-stepper`.

Hooks that must survive: `fn_image_gallery`, `fn_image_gallery_2`, `fn_img_gallery`,
`fn_img_gallery_2`, `fn_image_slider`, `fn_img_slider`, `fn_slider_gallery`,
`fn_slider_gallery_2`, `fn_img_zoom`, `fn_features`, `fn_selected_features`,
`fn_product_amount`, `fn_variants`, `fn_variant`, `fn_price`, `fn_old_price`, `fn_sku`,
`fn_in_stock`, `fn_not_stock`, `fn_is_stock`, `fn_is_preorder`, `fn_not_preorder`,
`fn_anchor_comments`, `fn_accordion`, `fn_tabs` if present.

- [ ] **Step 1: Two-column layout with a sticky buy box**

```css
@media (min-width: 992px) {
	.vs-pdp {
		display: grid;
		grid-template-columns: minmax(0, 1fr) 380px;
		gap: var(--vs-space-9);
		align-items: start;
	}

	.vs-buybox {
		position: sticky;
		top: var(--vs-space-6);
	}
}
```

- [ ] **Step 2: Sticky mobile buy bar**

```css
.vs-sticky-buy {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: var(--vs-z-sticky);
	display: flex;
	align-items: center;
	gap: var(--vs-space-4);
	padding: var(--vs-space-3) var(--vs-space-4);
	background-color: var(--vs-surface);
	border-top: 1px solid var(--vs-border);
	box-shadow: var(--vs-shadow-3);
	transform: translateY(100%);
	transition: transform var(--vs-dur-3) var(--vs-ease);
}

.vs-sticky-buy.is-visible {
	transform: translateY(0);
}

@media (min-width: 992px) {
	.vs-sticky-buy {
		display: none;
	}
}
```

Toggled by an `IntersectionObserver` on the inline add-to-cart button — add to `vibe.js`:

```js
(function () {
    'use strict';
    var inline = document.querySelector('.vs-buybox__submit');
    var bar = document.querySelector('.vs-sticky-buy');
    if (!inline || !bar || !('IntersectionObserver' in window)) return;

    new IntersectionObserver(function (entries) {
        bar.classList.toggle('is-visible', !entries[0].isIntersecting);
    }, {threshold: 0}).observe(inline);
}());
```

The bar's button submits the same form as the inline one, so no new form contract is introduced.

- [ ] **Step 3: Quantity stepper**

Two buttons around the existing `fn_product_amount` input. Write to the input and dispatch
`change` so `okay.js` recalculates. Never replace the input — it is the form value carrier (C6).

- [ ] **Step 4: Gallery and tabs**

Restyle the Swiper gallery through tokens (`vendor.css` still holds Swiper's own rules until
Task 8). Description, features and comments become a tab set on desktop and an accordion on
mobile, built on the existing `fn_accordion` hook.

- [ ] **Step 5: Verify**

Full verification loop. By hand: change variant and confirm price, old price, SKU, stock and
discount badge all update; add to cart from both the inline and sticky buttons; open the gallery
and zoom; use the quantity stepper; check a product with one variant, one with many, one out of
stock, and one with no image.

- [ ] **Step 6: Commit**

```bash
git add design/vibe_shop/
git commit -m "feat(vibe_shop): rebuild the product page with a sticky buy box"
```

---

## Task 6: Cart and checkout

**Files:**
- Modify: `design/vibe_shop/html/cart.tpl`, `cart_purchases.tpl`, `cart_deliveries.tpl`,
  `cart_coupon.tpl`, `cart_informer.tpl`, `pop_up_cart.tpl`, `order.tpl`
- Modify: `design/vibe_shop/css/components.css`

**Interfaces:**
- Consumes: `.vs-btn*`, `.vs-stock`, the quantity stepper from Task 5.
- Produces: `.vs-cart`, `.vs-cart__line`, `.vs-summary`, `.vs-option-card`, `.vs-empty`.

Hooks that must survive: `fn_coupon`, `fn_sub_coupon`, `fn_delivery_item`, `fn_delivery_price`,
`fn_delivery_module_html`, `fn_product_amount`, `fn_error_text`, `fn_ajax_content`.

Note `index.tpl:22` renders no header at all when `$controller == 'CartController'`. Keep that
behaviour — it is a deliberate distraction-free checkout — but give the cart page its own
minimal branded bar with a link back to the shop, since the current version leaves the user with
no way out.

- [ ] **Step 1: Cart line items**

Media thumbnail at a fixed ratio, name, variant, unit price, stepper, line total, remove
control. Totals use tabular numerals.

- [ ] **Step 2: Sticky order summary**

```css
@media (min-width: 992px) {
	.vs-cart {
		display: grid;
		grid-template-columns: minmax(0, 1fr) 340px;
		gap: var(--vs-space-8);
		align-items: start;
	}

	.vs-summary {
		position: sticky;
		top: var(--vs-space-6);
	}
}
```

- [ ] **Step 3: Delivery and payment as selectable cards**

```css
.vs-option-card {
	display: flex;
	align-items: flex-start;
	gap: var(--vs-space-3);
	padding: var(--vs-space-4);
	border: 1px solid var(--vs-border);
	border-radius: var(--vs-radius-md);
	cursor: pointer;
	transition: border-color var(--vs-dur-1) var(--vs-ease), background-color var(--vs-dur-1) var(--vs-ease);
}

.vs-option-card:hover {
	border-color: var(--vs-border-strong);
}

.vs-option-card:has(input:checked) {
	border-color: var(--vs-accent);
	background-color: var(--vs-surface-subtle);
}
```

The radio input stays in the markup and stays reachable — the card is a `<label>` wrapping it,
so keyboard selection and screen readers work unchanged.

- [ ] **Step 4: Empty cart state**

```css
.vs-empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: var(--vs-space-4);
	padding: var(--vs-space-10) var(--vs-space-4);
	text-align: center;
}
```

Icon from the sprite, one line of plain copy, one primary action back to the catalogue. Reused
for wishlist, comparison and empty search in Task 7.

- [ ] **Step 5: Verify**

Full verification loop. By hand, complete a full purchase: add products, change quantities,
remove a line, apply a coupon, pick a delivery method and confirm the total updates, pick a
payment method, submit the order, and check the order confirmation page. Then empty the cart and
check the empty state. Repeat the whole flow at 375px.

- [ ] **Step 6: Commit**

```bash
git add design/vibe_shop/
git commit -m "feat(vibe_shop): rebuild cart and checkout"
```

---

## Task 7: Secondary pages

**Files:**
- Modify: `blog.tpl`, `blog_sidebar.tpl`, `post.tpl`, `post_list.tpl`, `authors.tpl`,
  `author.tpl`, `user.tpl`, `user_comments.tpl`, `user_deliveries.tpl`, `user_informer.tpl`,
  `wishlist.tpl`, `comparison.tpl`, `brands.tpl`, `brands_content.tpl`, `page.tpl`,
  `page_404.tpl`, `login.tpl`, `register.tpl`, `password_remind.tpl`, `feedback.tpl`,
  `callback.tpl`, `main.tpl`, `tech.tpl`
- Modify: `design/vibe_shop/css/components.css`

**Interfaces:**
- Consumes: everything produced by Tasks 2–6.
- Produces: `.vs-form`, `.vs-field`, `.vs-field__error`, `.vs-table`, `.vs-prose`.

- [ ] **Step 1: Form primitives**

One field component used by login, register, password reminder, feedback, callback, checkout and
the subscribe form. Placeholder text must clear 4.5:1 like any other text — the muted default is
the single most common contrast failure.

```css
.vs-field__input {
	width: 100%;
	min-height: 44px;
	padding: var(--vs-space-3);
	background-color: var(--vs-surface);
	border: 1px solid var(--vs-border);
	border-radius: var(--vs-radius-sm);
	color: var(--vs-text);
	transition: border-color var(--vs-dur-1) var(--vs-ease);
}

.vs-field__input::placeholder {
	color: var(--vs-text-muted);
}

.vs-field__input:hover {
	border-color: var(--vs-border-strong);
}

.vs-field__error {
	margin-top: var(--vs-space-2);
	font-size: var(--vs-text-sm);
	color: var(--vs-sale-600);
}
```

- [ ] **Step 2: Homepage sections in `main.tpl`**

Rebuild the featured / new / discounted product carousels, the categories block, the brands
block and the news block on the new card and section primitives. Vary the section rhythm rather
than stamping one identical block four times, and do **not** add a tracked-uppercase eyebrow
above each one (C11).

- [ ] **Step 3: Blog, account, wishlist, comparison, 404, static pages**

Apply the tokens and shared components. Wishlist, comparison and empty search reuse `.vs-empty`
from Task 6. `.vs-prose` styles admin-authored WYSIWYG output in `page.tpl` and `post.tpl`,
where the markup is not ours to control.

- [ ] **Step 4: Verify**

Full verification loop across every page listed. Log in as a customer and check the account
pages. Submit the feedback form with an empty required field and confirm the inline error
renders through `fn_error_text`.

- [ ] **Step 5: Commit**

```bash
git add design/vibe_shop/
git commit -m "feat(vibe_shop): restyle blog, account and secondary pages"
```

---

## Task 8: Legacy stylesheet teardown

**Files:**
- Create: `design/vibe_shop/css/vendor.css`
- Delete: `design/vibe_shop/css/okay.css`, `theme.css`, `media.css`, `mobile_menu.css`,
  `font-awesome.min.css`, `jquery.fancybox.min.css`
- Modify: `design/vibe_shop/css.php`

This task is deliberately last. Deleting these files earlier would leave the site half-styled;
by now every rule they carried has been reimplemented, and what remains is genuinely dead or
genuinely vendor.

- [ ] **Step 1: Extract the vendor rules from `okay.css` into `vendor.css`**

Copy the `#Ui-slider` (noUiSlider), `#Swiper`, `#Loader amimation`, `#Lazy load` and `#Reedmore`
sections verbatim — these style third-party widgets and are not ours to redesign. Re-point their
hard-coded colours at tokens where they are visible chrome (slider handles, swiper bullets,
loader), leaving structural geometry alone.

- [ ] **Step 2: Confirm the generic class names survived (C7)**

```bash
for c in button boxed block block__title tabs accordion popup table; do
  printf '%s: ' "$c"
  grep -c "\.$c\b" design/vibe_shop/css/components.css
done
```

Every count must be greater than zero. A zero means a class module templates depend on was
dropped rather than restyled — fix before continuing.

- [ ] **Step 3: Delete the legacy sheets and update `css.php`**

Delete `okay.css`, `theme.css`, `media.css`, `mobile_menu.css`, `font-awesome.min.css` and
`jquery.fancybox.min.css`. The legacy ordering constraint disappears with them, which is why this
teardown is last.

```php
return [
    (new Css('tokens.css')),
    (new Css('grid.css')),
    (new Css('vendor.css')),
    (new Css('select2.min.css')),
    (new Css('base.css')),
    (new Css('components.css')),
];
```

`base.css` keeps its position after the vendor sheets for the same reason it sat after
`okay.css`: vendor CSS carries bare-element rules that would otherwise beat the typographic
base.

- [ ] **Step 4: Re-point the colours still hard-coded in `grid.css`**

`grid.css` is kept for its utilities, but any raw colour left in it must resolve through a token
(C10), subject to the one-`var()`-per-line rule only if it references an `--okay-*` name.

- [ ] **Step 5: Verify with modules enabled**

This is the step that catches C7 violations. In the admin panel, confirm which modules are
installed and active, then walk every page in scope again at both widths with those modules
running. A module template rendering `.boxed` or `.button` must still look right.

```bash
grep -c . design/vibe_shop/css/components.css
ls -l design/vibe_shop/css/
```

- [ ] **Step 6: Commit**

```bash
git add design/vibe_shop/
git commit -m "refactor(vibe_shop): retire legacy stylesheets, extract vendor CSS"
```

---

## Task 9: Accessibility, motion and state pass

**Files:**
- Modify: `design/vibe_shop/css/components.css`, `base.css`, `js/vibe.js`, templates as needed
- Modify: `config/config.local.php:56` (revert)

- [ ] **Step 1: Contrast audit**

Walk every foreground/background pair actually shipped and check it with a contrast checker —
body text, muted text, placeholder text, prices, badge text, stock labels, text on the ink
header, disabled controls. Body ≥4.5:1; large text and UI component boundaries ≥3:1. Fix by
moving toward the ink end of the neutral ramp, never by lightening.

- [ ] **Step 2: Keyboard pass**

Tab through every page from the top. Confirm: focus is always visible, order is logical, the
skip path into the main content works, sheets and modals trap focus and restore it on close,
Escape closes every overlay, and no control is reachable only by mouse.

- [ ] **Step 3: Touch target audit**

At 375px, confirm every interactive element is at least 44×44px, including the wishlist and
comparison toggles on cards, pagination links, quantity steppers and filter chips.

- [ ] **Step 4: Motion audit**

Confirm every transition and animation has a reduced-motion alternative, that the global
`prefers-reduced-motion` block in `base.css` is not overridden anywhere, and that no reveal
animation gates content visibility — content must render without JavaScript running.

- [ ] **Step 5: State audit**

For each of cart, wishlist, comparison and search: check the empty state. For each AJAX region:
check the loading state. For each form: check the error state. For products: check no image,
long title, out of stock, single variant, many variants.

- [ ] **Step 6: Revert the development flag**

Set `smarty_force_compile = false` in `config/config.local.php:56` and clear the cache:

```bash
rm -rf compiled/vibe_shop/* cache/css/* cache/js/*
```

Then reload the storefront and confirm it still renders correctly from cold caches.

- [ ] **Step 7: Final contract check**

```bash
grep -oh "fn_[a-z_0-9]*" design/vibe_shop/html/*.tpl | sort -u > /tmp/vibe-fn-final.txt
comm -23 /tmp/vibe-fn-baseline.txt /tmp/vibe-fn-final.txt
```

Expected: empty output. Any line printed is a JavaScript hook that was lost and must be restored
before the branch is considered done.

- [ ] **Step 8: Commit**

```bash
git add design/vibe_shop/ config/config.local.php
git commit -m "fix(vibe_shop): accessibility, motion and state pass"
```
