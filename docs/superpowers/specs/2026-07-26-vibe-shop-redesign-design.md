# vibe_shop theme redesign — design

Date: 2026-07-26
Status: approved (design phase)

## 1. Context

`design/vibe_shop` is a byte-for-byte copy of `design/okay_shop`: 109 files, ~14.5k lines of
CSS across 5 stylesheets, ~55 Smarty templates. It is a free-standing theme, so it may be
changed without restriction — nothing outside `design/vibe_shop/` is edited by this work.

The current visual language is dated: three competing brand colours (`#c5530c` buttons,
`#00afee`, `#232f3e`), a single heavy shadow (`0 2px 5px rgba(0,0,0,.3)`), 14px Montserrat
body text at line-height 1.4, and a desktop-first responsive layer bolted on through
`media.css`.

## 2. Goal and direction

Rebuild the theme as a **modern, conversion-first storefront**: dense product grids, soft
cards, one decisive accent, prominent search, and a mobile experience designed first rather
than degraded into.

Scope is the whole theme — homepage, catalogue, product page, cart and checkout, global
chrome, and secondary pages (blog, account, wishlist, comparison, 404, forms).

Explicit quality requirements carried through every screen:

- mobile-first, designed from 375px up
- WCAG AA contrast and full keyboard operability
- deliberate micro-interactions and complete component states

The theme is universal: it is installed by arbitrary shops and must hold up with three products
or thirty thousand, and with photography of uneven quality. That rules out any design that
depends on curated imagery or on a fixed catalogue shape.

**Anti-references** — all four rejected explicitly, and each has a named failure mode to guard
against:

| Rejected | Failure mode to avoid |
| --- | --- |
| Marketplace clutter (Amazon, Rozetka) | banner walls, red price tags everywhere, three stacked nav bars, noise substituting for hierarchy — what the theme looks like today |
| Default Bootstrap | blue buttons, grey boxes, stock radii; a template belonging to nobody |
| Generated-design defaults, 2026 | cream canvas, gradient text, glass cards, tracked-uppercase eyebrow over every section, endless identical card grids |
| Cold SaaS minimalism | dashboard sterility, air instead of merchandise, product stops being the hero |

The last two pull in opposite directions, which is the real constraint: restraint without
sterility. Warmth is carried by typographic contrast, spacing rhythm and tactile feedback —
never by a beige background.

## 3. Compatibility contracts

These are the boundaries the redesign must not cross. Everything not listed here is
presentational and fully ours to rewrite.

### 3.1 `fn_*` classes are the JavaScript contract

`js/okay.js` binds to 89 distinct `fn_*` class names. They look presentational and are
therefore easy to delete while rewriting markup. No `fn_*` class may be removed or renamed.
They carry no styling and must never be given any.

Invariant capture, before implementation starts:

```bash
grep -oh "fn_[a-z_0-9]*" design/vibe_shop/js/*.js design/vibe_shop/html/*.tpl \
  | sort -u > /tmp/vibe-fn-baseline.txt
```

After each page is rebuilt, re-run against the templates and diff. A missing hook is a
regression even when the page looks correct.

### 3.2 Form contract

`name=` attributes, `action=` targets, `<option value>` payloads and the `data-*` attributes
read by `okay.js` (`data-price`, `data-stock`, `data-cprice`, `data-discount`, `data-sku`,
`data-id`, `data-result-text`) are server and script contracts. Markup around them may change
freely; the attributes may not.

### 3.3 `--okay-*` variables are the admin contract

`theme-settings.css` is not an ordinary stylesheet. `Okay\Core\TemplateConfig\CssConfig`
parses it and surfaces every `--*` declaration as an editable colour in **Settings → Theme**,
but only for variables that have a `settings_theme_<name>` backend translation. Nine of the
seventeen currently qualify.

Consequences:

- The seventeen existing `--okay-*` names are kept exactly as they are. Only their *values*
  change. Shop owners keep the recolouring they have today, and no backend file is touched.
- New variable names invented by this theme would be invisible in the admin panel, because
  adding their translations would mean editing `backend/lang/*.php`. The new token layer
  therefore lives in `tokens.css` and *derives* from `--okay-*`.

**Non-obvious hazard:** `CssConfig::initCssVariables()` flattens every rule set in the file
into one map keyed by variable name. Two rule sets declaring the same variable (for example a
`:root` block plus a `[data-theme="dark"]` block) collapse to one entry, and
`updateCssVariables()` then writes the admin's chosen value into *both*. `theme-settings.css`
must contain exactly one `:root` rule set.

### 3.4 Grid utilities are a module contract

`grid.css` supplies `f_row`, `f_col-*`, `d-flex`, `align-items-*`, `hidden-*` and friends.
Module templates outside this theme use them. `grid.css` is kept, including the existing
`.container` max-width of 1366px and `.container-less` of 860px.

### 3.5 Generic component classes are a module contract

`okay.css` defines generic classes — `.button`, `.boxed`, `.block`, `.block__title`,
form-control styling, `.tabs`, `.accordion`, `.popup`, `.table` — that module templates
render against. When `okay.css` is dissolved, `theme.css` must re-implement these class names
under the new design. They are restyled, never dropped.

## 4. Architecture

```
css/tokens.css     new       design tokens; the only place raw colour values exist
css/base.css       new       reset, typography, focus ring, form baseline
css/theme.css      rewritten component layer (incl. the §3.5 generic classes)
css/vendor.css     new       extracted from okay.css: noUiSlider, swiper, loader,
                             lazyload, readmore, fancybox remnants
css/okay.css       removed   presentational content moves into base.css + theme.css
css/media.css      removed   responsiveness lives inside components, mobile-first
css/grid.css       kept      utilities (§3.4); hard-coded colours re-pointed at tokens
css/theme-settings.css       same 17 variable names, new values
js/vibe.js         new       bottom-sheet filters, sticky buy bar, quantity steppers
```

`css.php` and `js.php` are updated to match. CSS is concatenated and cached by content hash
(`CssConfig::compileRegistered`), so file count carries no runtime cost.

`okay.js` is left alone. New behaviour goes into `js/vibe.js` so the two stay separable.

**Rule:** no component stylesheet contains a raw colour, shadow, radius, duration or font
stack. Everything resolves through a semantic token. This is what keeps the option in §9 open
at near-zero cost, and it is how the palette stays coherent across 55 templates.

## 5. Design tokens

### 5.1 Colour

The organising idea is that three roles currently blur into one another and must be separated:
in a storefront, **red belongs to discounts**, **green to availability**, and the **call to
action can be neither** — otherwise promotions and buy buttons compete for the same attention.

The colour strategy is **restrained**: a monochrome chrome carrying the whole interface, with
chromatic colour reserved for two semantic events. Two rejected alternatives, recorded so they
are not re-proposed:

- *A warm-tinted neutral canvas* (`#fbfaf9` and its band — light, warm, low chroma) was the
  first draft. It is the saturated cream/sand/paper default of current generated design and
  reads as exactly the anti-reference the project rejects. Neutrals are therefore **true
  neutral at chroma 0**, tinted neither warm nor cool.
- *An indigo call-to-action* was the second draft. "E-commerce that isn't marketplace-red"
  resolves to a violet-blue accent by reflex; avoiding the first cliché landed on the second.
  It also fights the shop owner: `--okay-basic-company` is theirs to set, and an opinionated
  indigo competes with whatever brand colour they choose.

The call to action is therefore **near-black**. It holds against any product photograph,
leaves the owner's brand colour as the only opinionated hue on the page, and makes the rose
discount badge the loudest chromatic event in the catalogue — which is what should be loudest.

```
--vs-n-0:   #ffffff      --vs-n-500: #6b6b71   muted text, 4.9:1 on canvas
--vs-n-25:  #fafafa      --vs-n-600: #5c5c62
--vs-n-50:  #f5f5f6      --vs-n-700: #45454a
--vs-n-100: #ededee      --vs-n-800: #2b2b2f   CTA hover
--vs-n-200: #e0e0e2      --vs-n-900: #1c1c20   body text, 15.7:1 on canvas
--vs-n-300: #cbcbce      --vs-n-950: #17171a   ink chrome, CTA
--vs-n-400: #a5a5aa

--vs-sale-500: #e11d48      discount badge fill, white text on it 4.7:1
--vs-sale-600: #be1739      discount *text*, 5.7:1 on canvas
--vs-sale-50:  #fff1f4

--vs-ok-600:   #047857      in stock, 5.1:1 on canvas
--vs-ok-50:    #ecfdf5

--vs-warn-600: #b45309      low stock, 4.6:1 on canvas
--vs-warn-50:  #fffbeb
```

Note the split between `--vs-sale-500` and `--vs-sale-600`: the brighter rose clears AA as a
badge fill with white text but falls to 4.34:1 as text on the canvas. Discount *text* uses the
600.

Focus is a two-tone ring — `0 0 0 2px var(--vs-canvas), 0 0 0 4px var(--vs-ink)` — so it stays
visible on light surfaces and on the dark header alike, without a second focus token.

Because the chrome is monochrome, links are distinguished by an offset underline rather than
by colour alone. That also satisfies the "colour is not the only carrier of meaning" rule.

Semantic layer — the only names components are allowed to use:

```
--vs-canvas          page background
--vs-surface         card and panel background
--vs-surface-subtle  image plates, inset regions
--vs-hairline        low-contrast separators
--vs-border          control and card borders
--vs-border-strong   hover / active borders
--vs-text            body and headings
--vs-text-muted      secondary text
--vs-text-inverse    text on ink and accent
--vs-ink             header and footer chrome
--vs-cta             primary action background
--vs-cta-hover
--vs-cta-text
--vs-focus           focus ring
```

Bridge to the admin contract (§3.3), declared in `tokens.css`:

| `--okay-*` (admin-editable)     | drives                         |
| ------------------------------- | ------------------------------ |
| `--okay-button-color`           | `--vs-cta`                     |
| `--okay-button-color-hover`     | `--vs-cta-hover`               |
| `--okay-button-text`            | `--vs-cta-text`                |
| `--okay-basic-company`          | owner's brand accent — active states, link hover |
| `--okay-second-company`         | `--vs-ink`                     |
| `--okay-second-company-text`    | text on ink                    |
| `--okay-bg`                     | `--vs-canvas`                  |
| `--okay-boxed-color`            | `--vs-surface`                 |
| `--okay-body-text`              | `--vs-text`                    |
| `--okay-border-color`           | `--vs-border`                  |

New `theme-settings.css` values (names unchanged):

```
--okay-button-color: #17171a         --okay-boxed-color: #ffffff
--okay-button-text: #ffffff          --okay-boxed-text: #1c1c20
--okay-button-color-hover: #2b2b2f   --okay-button-second-color: #ffffff
--okay-button-text-hover: #ffffff    --okay-button-second-text: #1c1c20
--okay-basic-company: #17171a        --okay-border-color: #dcdcde
--okay-second-company: #17171a       --okay-bg: #f5f5f6
--okay-basic-company-text: #ffffff   --okay-body-text: #1c1c20
--okay-second-company-text: #f5f5f6  --okay-body-heading: #1c1c20
--okay-shadow-color: 0 2px 4px rgba(23,23,26,.05), 0 8px 16px -4px rgba(23,23,26,.08)
```

`--okay-basic-company` ships as ink so the theme is monochrome out of the box. A shop that sets
its own brand colour in the admin gets it applied to accents without any part of the design
fighting it. `--okay-button-second-color` flips from the old dark fill to a white surface: the
secondary button becomes an outline control.

Contrast figures above are design intent. Every foreground/background pair actually shipped is
verified with a contrast checker during implementation (§10), not estimated by eye.

### 5.2 Typography

Montserrat is a geometric sans: wide, low x-height, weak for interface text at 13–15px. It is
kept for **display and headings only**, where its character is an asset. Body and UI text
switch to **Inter**, self-hosted next to it (`fonts/inter/InterVariable.woff2`, verified
reachable for download).

```
--vs-font-display: 'Montserrat', system-ui, sans-serif     600, 700
--vs-font-ui: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif   400, 500, 600

--vs-text-xs:   0.75rem      12px  meta, badges
--vs-text-sm:   0.8125rem    13px  secondary
--vs-text-base: 0.9375rem    15px  body        (was 14px)
--vs-text-lg:   1.0625rem    17px
--vs-text-xl:   clamp(1.125rem, 1rem + 0.6vw, 1.375rem)
--vs-text-2xl:  clamp(1.375rem, 1.1rem + 1.2vw, 1.875rem)
--vs-text-3xl:  clamp(1.75rem, 1.3rem + 2vw, 2.75rem)

--vs-leading-tight: 1.2     headings
--vs-leading-body:  1.55    body        (was 1.4)
```

Prices, quantities and totals use `font-variant-numeric: tabular-nums`. Without it, digits
change width when `okay.js` rewrites a price on variant change and the layout twitches.

Font payload drops rather than grows: `head.tpl` currently preloads four Montserrat weights.
It will preload one Inter variable file and Montserrat SemiBold; Montserrat Regular and Medium
are no longer referenced.

### 5.3 Space, shape, elevation, motion

```
--vs-space-1 … -12:  4 8 12 16 20 24 32 40 48 64 80 96 px (as rem)

--vs-radius-sm:   0.5rem      inputs, small controls
--vs-radius-md:   0.75rem     buttons, chips
--vs-radius-lg:   1rem        cards, panels
--vs-radius-xl:   1.5rem      sheets, modals
--vs-radius-full: 999px       pills, avatars

--vs-shadow-1: 0 1px 2px rgb(23 23 26 / .06), 0 1px 3px rgb(23 23 26 / .04)
--vs-shadow-2: 0 2px 4px rgb(23 23 26 / .05), 0 8px 16px -4px rgb(23 23 26 / .08)
--vs-shadow-3: 0 4px 8px rgb(23 23 26 / .06), 0 16px 32px -8px rgb(23 23 26 / .14)

--vs-dur-1: 120ms   colour, opacity
--vs-dur-2: 200ms   transform, reveal
--vs-dur-3: 320ms   panels, sheets
--vs-ease:  cubic-bezier(.2, .7, .3, 1)
```

Motion is suppressed globally under `prefers-reduced-motion: reduce`.

Focus is `:focus-visible` with a 2px ring at 2px offset in `--vs-focus`. `outline: none`
without a replacement ring does not appear anywhere in the theme.

## 6. Components

### 6.1 Product card — `product_list.tpl`

The highest-leverage element in the theme; it repeats hundreds of times per session.

Current problems: the image has no fixed aspect ratio so rows jump as photos load; the product
name is unclamped and pushes cards to unequal heights; the add-to-cart control is an icon
button; a select2 dropdown for variants is always rendered even for single-variant products;
stock state is buried in a sentence.

Redesigned:

- image in an `aspect-ratio: 4/5` box, `object-fit: contain` on a `--vs-surface-subtle` plate,
  so mixed-quality catalogue photography aligns to a common grid
- badges (hit, discount percentage, special) top-left; wishlist top-right, revealed on hover
  on pointer devices but **permanently visible under `(hover: none)`** — a hover-only control
  is unreachable on touch
- name clamped to two lines
- price row: current price prominent, `compare_price` struck through and muted, discount in
  `--vs-sale-500`, all tabular
- availability as a coloured dot plus short label (emerald in stock, amber low, neutral out)
- full-width primary CTA
- variants: an inline chip row when four or fewer, `fn_select2` dropdown beyond. One tap
  instead of open-dropdown-then-choose. The `<select>` remains in the DOM as the form value
  carrier, so §3.2 holds.

### 6.2 Global chrome — `index.tpl`, `menu.tpl`, `mobile_menu.tpl`, `desktop_categories.tpl`

Three desktop header bars collapse to two: a thin utility bar (account, language, currency,
callback) and a main bar (logo, catalogue, search, informers). Mobile gets a single sticky
bar. Search moves to the main bar at full width — it is a primary conversion path and is
currently squeezed between buttons. `fn_search`, `fn_search_mob`, `fn_search_toggle`,
`fn_catalog_switch` and `fn_header__sticky` keep their behaviour.

Footer is restructured into a clean multi-column layout with contact block and payment icons.

### 6.3 Catalogue — `products_content.tpl`, `features.tpl`, `products_sort.tpl`, `selected_features.tpl`

Desktop: filters in a left rail, results grid at 2/3/4 columns by width.

Mobile: filters become a **bottom sheet** rather than the current inline toggle, with a
sticky "Показати N товарів" action. Applied filters render as removable chips above the grid
via the existing `fn_selected_features` hook. Sorting becomes a segmented control.

The noUiSlider price range keeps its markup contract and is restyled through tokens.

### 6.4 Product page — `product.tpl`

Desktop: gallery left, buy box right and sticky through the fold. Mobile: gallery first, then
buy box; once the inline CTA scrolls out of view a **sticky bottom bar** with price and
add-to-cart appears. Features, description and comments become a tab or accordion set built on
the existing `fn_features` / `fn_accordion` hooks.

### 6.5 Cart and checkout — `cart.tpl`, `cart_purchases.tpl`, `cart_deliveries.tpl`, `order.tpl`, `pop_up_cart.tpl`

Line items get real quantity steppers instead of a bare number input. The order summary is
sticky. Delivery and payment options become selectable radio cards rather than bare radio
buttons, which is both easier to tap and clearer about what is selected. `fn_coupon`,
`fn_sub_coupon`, `fn_delivery_item`, `fn_delivery_price` and `fn_delivery_module_html` are
preserved.

### 6.6 States

- skeleton placeholders for AJAX regions driven by `fn_ajax_content` / `fn_ajax_wait`
- empty states with a clear next action for cart, wishlist, comparison and no-results search
- inline validation errors through the existing `fn_error_text` / `fn_error_text_blog` hooks
- hover, active, focus-visible, disabled and loading defined for every interactive control

### 6.7 Secondary pages

`blog.tpl`, `post.tpl`, `post_list.tpl`, `authors.tpl`, `author.tpl`, `user.tpl`,
`user_comments.tpl`, `user_deliveries.tpl`, `wishlist.tpl`, `comparison.tpl`, `brands.tpl`,
`page.tpl`, `page_404.tpl`, `login.tpl`, `register.tpl`, `password_remind.tpl`,
`feedback.tpl`, `callback.tpl` are brought onto the same tokens and components. The goal is
that no screen reads as leftover old theme.

Transactional email templates under `html/email/` are out of scope: they need table-based
layout and inline styles, and share nothing with the stylesheet system.

## 7. New JavaScript — `js/vibe.js`

Registered in `js.php` at footer position, after `okay.js`.

- bottom-sheet filter panel: open/close, focus trap, background scroll lock, Esc to dismiss
- sticky mobile buy bar on the product page, toggled by an `IntersectionObserver` on the
  inline CTA
- quantity steppers that write to the existing quantity input and dispatch the events
  `okay.js` already listens for
- `(hover: none)` affordance handling where a hover-revealed control needs a touch fallback

No behaviour is moved out of `okay.js`.

## 8. Files touched

Everything is inside `design/vibe_shop/`. No core file, no backend file, no other theme, no
module is modified.

## 9. Out of scope: dark theme

Considered and deliberately excluded. The token work is not the obstacle; the uncontrollable
content is:

- product photography is shot on white, so a dark canvas turns every catalogue tile into a
  glowing rectangle unless each image sits on a light plate — at which point the dark theme
  looks like the light one in a frame
- owner-supplied content — banners, brand logos as dark PNGs on transparency, product
  descriptions carrying inline `color:#000` from the WYSIWYG — cannot be recoloured by us
- module templates hard-code light backgrounds and may not be edited under the project's
  constraints, so every installed module would appear as a light patch
- QA surface doubles across ~55 templates for a feature with low storefront adoption

What is retained: the §4 rule that no component may contain a raw colour. Adding a dark theme
later is then one additional rule set in `tokens.css` plus a toggle — not a refactor. Per
§3.3, that rule set must live in `tokens.css` and never in `theme-settings.css`.

## 10. Verification

Verified in a real browser against the running dev environment (`okaycms.loc`, already up)
through chrome-devtools, not by reading CSS:

- 375px and 1440px viewports for every page in scope
- `fn_*` baseline diff after each page (§3.1)
- contrast checked for every shipped foreground/background pair against WCAG AA
- keyboard-only pass: visible focus throughout, no traps, sheets and modals dismissible by Esc
- tap targets at or above 44×44px on mobile
- pages checked with modules enabled, not on a bare theme
- console free of new errors; existing AJAX paths (add to cart, wishlist, comparison, variant
  change, filters, pagination) exercised by hand

## 11. Work order

1. `tokens.css`, `base.css`, Inter self-hosting, `head.tpl` font block, `css.php` / `js.php`
2. global chrome — header, menu, mobile menu, footer
3. product card
4. catalogue — grid, filters, sorting, pagination
5. product page
6. cart and checkout
7. secondary pages
8. accessibility, state and motion pass across everything
