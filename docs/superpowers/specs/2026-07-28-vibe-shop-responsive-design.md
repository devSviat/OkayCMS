# vibe_shop — phone and tablet pass, portrait and landscape

Date: 2026-07-28
Theme: `design/vibe_shop` (the active theme; confirmed from the rendered HTML)
Status: design approved, not implemented

## Problem

The theme's responsive layer is inherited from the Bootstrap grid: width breakpoints at
576 / 768 / 992 / 1200 and nothing else. It has no rule anywhere that reacts to viewport
*height*, so a phone held sideways is treated as a small tablet — it gets the tablet's
width-driven layout on a third of the vertical space.

Measured on the local dev environment, four viewports, no horizontal overflow anywhere:

| viewport | header | sticky buy bar | cards per carousel view | catalogue columns |
| --- | --- | --- | --- | --- |
| 390×844 phone portrait | 61 | 73 | 2 | 2 |
| 844×390 phone landscape | 61 | 73 | **4** | 3 |
| 820×1180 tablet portrait | 61 | 73 | **4** | 3 |
| 1180×820 tablet landscape | 87 | — | 4 | 3 |

Two numbers drive most of this spec:

- On 844×390 the product page spends **134 px of 390 on permanent chrome** (61 px sticky
  header plus 73 px sticky buy bar) — 34 % of the screen — leaving 256 px for content.
- The hero banner is `aspect-ratio: 21 / 9` from 768 px, so at 844 px wide it is 362 px
  tall. With the header that is 423 px against a 390 px viewport: on a phone in landscape
  the first screen is banner and nothing else.

## Decisions taken before this spec

1. **Polish the existing layer, do not rebuild it.** No new components, no new navigation
   patterns, no mobile-first rewrite of `components.css`.
2. **Scope is the commercial core:** home, catalogue with filters, product page, cart,
   checkout. Everything else is out of scope (listed below).
3. **Landscape is detected by viewport height, never by `orientation`.** `orientation:
   landscape` cannot tell a phone held sideways from a tablet held sideways; height can.
4. **`design/vibe_shop` only.** `media.css` belongs to `design/okay_shop`, the stock theme,
   which this site does not serve.
5. **Rules go into the existing `@media` runs in `components.css`**, following the file's
   convention (banner comment, blocks ordered by breakpoint), rather than into a new
   stylesheet. A separate file would create a second source of truth per component and put
   the outcome at the mercy of bundle order — and in this project module CSS lands *after*
   the theme's in the bundle, so load order is not something to rely on.

## Architecture: the breakpoint model

Width breakpoints stay exactly as they are. `grid.css` hangs its column and visibility
utilities (`hidden-lg-up`, `col-*`) off 576 / 768 / 992 / 1200, so moving any of them
ripples through the whole theme for no gain.

One new layer is added, as two blocks appended to the `@media` run in `components.css`:

```
@media (max-height: 500px)                        /* short viewport */
@media (max-height: 500px) and (min-width: 640px) /* short and wide — phone in landscape */
```

**Why 500 px.** Every phone in landscape is under it (844×390, 800×360, 736×414). No
tablet reaches it — an iPad sideways is 1180×**820**. The threshold also catches a short
desktop window and a tablet in split view, which is correct: those want the same treatment.

**Why 640 px.** It keeps iPhone SE in landscape (667×375) inside the two-column layer while
excluding narrow tall windows that happen to be short.

**URL-bar risk.** Mobile browsers change viewport height as the address bar collapses. In
portrait, 844 px is nowhere near the threshold. In landscape, 390 px can grow to roughly
430 px when the bar hides — still about 70 px of headroom, so the layout will not flip
back and forth during a scroll. This needs confirming on a real device; a headless browser
cannot reproduce it. Tracked as an open item.

**Compiler traps.** `Okay\Core\TemplateConfig\CssConfig` preprocesses each stylesheet line
by line and corrupts three shapes silently, with no error anywhere:

- a comment sharing a line with a declaration deletes that declaration — comments own their
  whole line;
- `var(--okay-*)` is substituted only as `property: var(--okay-x);` — one call per line, no
  fallback;
- a selector may break across lines only immediately after a comma, otherwise the parts are
  joined with no separator into a different selector.

Every rule added by this spec is grepped for all three before it is believed.

## Changes

### Home

**1. Fix the Swiper breakpoint ladder** — `design/vibe_shop/js/okay.js:538`, the
`.fn_products_slide` config. The key `768` appears twice in the same object literal; the
second wins, so `768: {slidesPerView: 3}` is dead code and 4 cards per view runs from 768
all the way to 1199. At 820 px that is roughly 190 px per card, which is what wraps titles
onto three lines and crushes the price row.

New ladder: `320→1, 360→2, 768→3, 992→4, 1200→5`. The `576` entry is dropped rather than
set, since it would repeat the value already in force. Every step then lands at 230–240 px
per card, and at 820 px the carousel agrees with the catalogue grid beside it — 3 and 3.

This is the only change in the spec that affects all widths, so the `1200→5` step is
verified on desktop separately.

**2. Cap the hero on short viewports.** Under `max-height: 500px`, override
`.main_banner .banner_group__item` to `aspect-ratio: auto; height: 60vh`. At 390 px that is
234 px, which with the 61 px header leaves about 95 px of the next section visible, so the
first screen shows that the page continues. The image already carries `object-fit`, so the
crop needs nothing further.

**3. Compress the vertical rhythm on short viewports.** Section padding drops from
`--vs-space-9/10` to `--vs-space-5/6`. Applies site-wide within the short-viewport block,
not only on the home page.

### Catalogue

**4. Compact the card on short viewports.** In the `max-height: 500px` block, drop
`.vs-card` padding to `--vs-space-2` and `.vs-card__media-link` to `--vs-space-3` — the
values the ≤575 block already uses — and cap the media plate at 180 px so its 1:1 ratio
cannot make a three-column row 250 px tall. The target is a second row of products starting
within the 390 px viewport.

The column counts themselves — 2 below 768 px, 3 above — measured correct at every viewport
and are not touched.

### Product page

**5. Two columns on short and wide.** `.vs-pdp__layout` is already a two-column grid at
≥992 px (`components.css:5237`, tracks `minmax(0, 760px) minmax(0, 460px)`). Apply the same
layout in the short-and-wide block with narrower tracks sized for 844 px, and with it:

- lift the `max-width: 480px` cap on `.vs-gallery__frame` that the ≤991 block imposes — it
  exists to stop an uncapped square stage from being 897 px tall in one column, which the
  two-column layout no longer risks;
- hide `.vs-sticky-buy`. The bar earns its 73 px only while the real CTA is off screen;
  beside the gallery the CTA is on screen, and the bar is then a duplicate button charging
  19 % of the viewport height for nothing.

**6. Stack the buy row at ≤575 px.** The quantity stepper and "Додати в кошик" share one
row, which squeezes the button to roughly 200 px at 390 px wide. Stepper on its own row,
button full width beneath it.

### Cart and checkout

**7. The short-viewport rhythm compression from item 3 applies here too.** Nothing else.
The cart and checkout layouts measured well at 390, 820 and 844 — single column, full-width
fields, readable rows.

Note on what is *not* being done: at 844×390 the checkout is 2707 px long, seven screens,
with the total and the submit button at the very end. The obvious fix — a two-column layout
with the summary beside the form — is not in this spec, because no such layout exists to
reuse. `.vs-checkout` is a single-column stack at every width, so building one would be a
new layout rather than a polish of an existing one, which is outside the agreed scope. Item
3 shortens the page; it does not restructure it.

### Cross-cutting

**8. Tap targets at the viewports the previous pass never measured.** `components.css`
already ends with a finished touch-target pass — a `TOUCH TARGETS` block raising roughly
twenty selectors to `min-height: 44px`, with three exemptions documented in place: links
inside `.vs-prose` (WCAG 2.5.8 exempts inline links in a sentence), `<label for>` on a text
field whose job is to focus a 44 px input beside it, and `.vs-crumbs__item a` at 34 px,
which is the entire width of the word.

That pass measured **375 px portrait only**. This spec's contribution is the viewports it
never saw: the audit counts 11 elements under 44 px at 844×390 and at 820×1180. The task is
to enumerate those, subtract everything already covered by the three documented exemptions,
and extend the existing `TOUCH TARGETS` block only with what landscape and tablet genuinely
surface. Anything left unraised is documented in the same style and for the same kind of
reason. This is not a re-run of the earlier pass and must not restate its rules.

## Explicitly out of scope

- Pages: `/user`, wishlist, comparison, blog and posts, brands, static pages, 404, search.
- The `design/okay_shop` theme and its `media.css`.
- Moving the 576 / 768 / 992 / 1200 width breakpoints.
- New components or navigation patterns — bottom navigation bar, list-view product card,
  full-screen filter sheet.
- The variant picker on the catalogue card, still open from the previous redesign round.
- The orphan card in the blog grid: an artefact of there being four posts, not a defect.
- Catalogue row alignment. An earlier draft of this spec called the 390 px grid ragged.
  Measured, the cards go 412, 412, 428, 428, 440, 440 — every pair in a row is identical,
  and rows differing from one another is `grid-auto-rows: auto` working as intended.
  `.vs-card__name` already carries `-webkit-line-clamp: 2` with a reserved `min-height`.
  There is nothing to fix.
- A two-column checkout (see item 7).

## Verification

Browser verification is the only real check for layout work, and it runs on every change,
not once at the end.

**Matrix.** 5 pages — home, catalogue, product, cart, checkout — × 4 viewports —
390×844, 844×390, 820×1180, 1180×820 — screenshotted before and after each change.
`shot.mjs` covers the pages reachable cold; the cart and checkout need a session with lines
in it, so a second script fills the cart once and shoots both pages across all four
viewports in the same browser.

**Numbers, not impressions.** At each viewport the audit records: `scrollWidth` against
`innerWidth` (horizontal overflow), cards per carousel view, catalogue column count, header
and sticky-bar heights, and the count of interactive elements under 44 px. The numbers go
in the ledger.

**Desktop regression guard.** Every new rule sits inside `max-height: 500px` or inside an
existing mobile block, which makes desktop structurally unreachable — but that is an
argument, not evidence, so 1440×900 is shot and compared like every other viewport. The
`okay.js` change is the exception that genuinely spans all widths and is checked on its own.

**Cache.** `compiled/` and `cache/css/` are cleared after each CSS edit. Skipping this means
verifying the previous bundle.

**Console.** Changing the Swiper breakpoints re-initialises the carousels; the runs must
come back with no console errors, which `shot.mjs` reports and exits non-zero on.

## Open items

- The URL-bar height behaviour under `max-height: 500px` needs one check on a real phone in
  landscape. Headless cannot reproduce a collapsing address bar.
