# vibe_shop — product card on mobile, plus translucent chrome

Date: 2026-07-29
Theme: `design/vibe_shop`
Branch: `feature/vibe-shop-responsive` (PR #5, open)
Status: design approved, not implemented

## Problem

The owner reported the catalogue cards look wrong on a phone. Measured at 390 px, two
adjacent cards in the same row, both 412 px tall:

| element | card without a discount | card with one |
| --- | --- | --- |
| name | y=183 h=44 | y=183 h=44 |
| sku | y=231 | y=231 |
| price | y=262 **h=26** | y=262 **h=54** |
| old price | absent | y=296 |
| stock | y=296 | y=**324** |
| actions | y=359 | y=359 |

**The price block is 26 px on a card with no old price and 54 px on one with it.** Everything
between the price and the actions shifts by 28 px, so the green availability line sits at a
different height in neighbouring cards. The buttons still line up only because they are pinned
to the bottom of a stretched grid row.

**Correction, found while implementing this spec: that 28 px is the smaller half.** Sampling six
cards instead of two shows `.vs-card__name` rendering at 44, 44, 81, 60, 101 and 60 px — up to
57 px of variance. The title is declared with `-webkit-line-clamp: 2` at `components.css:2561`,
but that clamp only works with `display: -webkit-box`, and the `TOUCH TARGETS` block at
`components.css:10399` overrides it with `display: flex`. The clamp has been dead since that
pass. The computed style still reports `-webkit-line-clamp: 2`, which is why it survived a
touch-target pass, a whole-branch review and two responsive passes unnoticed.

The same file prescribes the correct shape two rules below the mistake, for `.vs-cart__name`:
"-webkit-box has to stay - it is what the clamp needs - so the height comes from min-height
rather than from becoming a flex box." Restoring the clamp is item 5.

Two other things load the card:

- **The SKU line** costs 19 px plus its margin on every card, for a value most shoppers do not
  scan in a grid.
- **The corner holds up to three badges** — "featured", the discount percentage, and `special`,
  which is a raster image the shop owner uploads. The theme controls neither its shape nor its
  palette, so it stacks a second visual language under two theme-styled pills.

## Changes

**1. Reserve the old-price line; do not inline it.**

The obvious fix — putting the old price beside the current one — does not work at this width.
Measured: the two prices side by side need **182 px**, and the card body is **162 px**. It would
wrap, and variable height would return through the back door.

So the price block becomes two fixed tiers: the current price on the first, the struck-through
old price on the second. When there is no discount the second tier is empty but its space is
held. The card already uses this technique on the title — `-webkit-line-clamp: 2` with a
reserved `min-height` — so this is the component's existing answer applied again, not a new one.

**2. The discount percentage moves out of the corner and onto the reserved tier**, beside the
old price it modifies. Measured: "540 000,00 ₴" plus a "−34 %" pill is about 145 px against 162
available. The percentage belongs next to the number it refers to, not floating over the
photograph, and the corner drops to at most two badges.

`fn_discount_label` is a JavaScript hook — `okay.js` reveals the badge by removing
`hidden-xs-up` when a discounted variant is selected. The class stays; only its position in the
DOM changes.

**3. The SKU leaves the card body — commented, not deleted.**

Both scripts are safe: `okay.js:59` reaches it with `parent.find(".fn_sku")`, and jQuery methods
on an empty set are a no-op; `vibe.js:470` guards with `if (sku)` before adding it to the live
announcement.

It is removed as a commented block carrying a note on how to restore it, matching the
convention already used in this file for the comparison control. For a parts or electronics
shop the SKU on a card may be the primary way customers scan, and the owner of such a shop
should have one edit to make, not a reconstruction.

**4. Translucent chrome on the header and the sticky buy bar.**

Requested by the owner. Two facts decide how it is built:

- **`backdrop-filter` does nothing behind an opaque background.** Both `.vs-header__main` and
  `.vs-sticky-buy` currently set `background-color: var(--vs-surface)`, fully opaque. The real
  change is the translucency; the filter only makes it legible.
- **`--vs-surface` is `var(--okay-boxed-color)`** — the colour the shop owner picks in the admin
  panel, substituted into the bundle at compile time by `CssConfig`. The translucent value
  therefore cannot be a hard-coded rgba; it has to be derived from whatever the owner chose,
  with `color-mix(in srgb, var(--vs-surface) N%, transparent)`. By the time the browser sees it,
  `--vs-surface` holds a literal colour, so `color-mix` resolves normally and no `--okay-*`
  compiler rule is involved.

**Translucency ships only where the blur does.** Both go inside
`@supports (backdrop-filter: blur(12px)) or (-webkit-backdrop-filter: blur(12px))`, with the
`-webkit-` prefix for Safari. A browser without support keeps today's opaque surface. Applying
the translucency unconditionally would hand those browsers a see-through bar with no blur —
strictly worse contrast than what they have now.

**Contrast is measured, not assumed.** This theme's stated bar is WCAG 2.1 AA — body text
≥4.5:1, verified with a checker rather than estimated. A translucent surface over arbitrary
shop photography cannot be reasoned about; it has to be sampled over a dark product image at
both viewports, and the opacity raised until the header's own text and icons clear 4.5:1 in the
worst case found. The starting point is 82 %, but the measurement decides the shipped number.

**Performance is checked once.** `backdrop-filter` on an element that repaints every scroll
frame is among the more expensive effects on a phone. If a scroll trace shows it costing frames
at 390×844, the effect is dropped rather than shipped slow — a smooth opaque header beats a
janky translucent one on the device this whole project exists to serve.

**5. Restore the card title's two-line clamp.** Drop `display: flex` and
`align-items: flex-start` from `components.css:10399-10403`, keeping `min-height: 44px`. The
base rule at `:2561` already sets `display: -webkit-box`, so the clamp returns and the 44 px
touch target survives on `min-height` — exactly the shape the neighbouring `.vs-cart__name`
comment prescribes.

This is the larger half of the alignment problem and it was not in the spec's original
diagnosis. `.vs-post-card__link` shares the rule; its own clamp must be checked rather than
assumed, and the blog grid re-measured.

**6. Give the page a real edge gutter on phones.** Reported by the owner with three
screenshots. Measured at 390 px: on the catalogue and the product page every block starts at
**x=7**, and on the home page at **x=0**.

The 7 px comes from `grid.css`, which sets `.container { padding-left: 7px; padding-right: 7px }`
— a legacy Bootstrap gutter. It is too small for a phone against a theme whose own rhythm steps
in 16 and 20.

The home page's 0 is different and half-deliberate. `.vs-home__section` zeroes the container's
horizontal padding, and the carousels then pull themselves out by half a gutter so a card peeks
off the edge and signals the row scrolls. That bleed is intentional and documented in place. The
defect is that the **section headings** inherited it: `.vs-home__title` sits at x=0 inside
`.vs-home__head`, and a heading cannot scroll.

So: raise `.container` to 16 px below 576 px only — the width where it hurts, leaving tablet and
desktop untouched so no regression is possible there — and give `.vs-home__head` the same 16 px
so headings align with card content while the rails keep bleeding.

**7. Put the label back on the sticky buy button.** Requested by the owner after seeing it ship.

The floating bar's CTA is currently `.vs-sticky-buy__cta--icon`, a 52 px circle holding the cart
glyph with its label hidden in `.vs-sr-only`. It becomes a pill carrying the glyph and the
visible words again.

The spec that made it icon-only recorded the trade at the time: "an icon-only primary action is
less explicit than a labelled one, and on a purchase button that is a real conversion
consideration." The owner has now seen it in place and asked for the label. The `data-language`
attribute stays on the span that holds the text — it marks the element whose text the language
switch rewrites, and it must not move back onto the button, which also contains the SVG.

## Explicitly out of scope

- **The `special` badge image.** It is the shop owner's uploaded asset, and the theme's own
  principle is that the owner's brand wins. It keeps its `max-height: 36px` and is otherwise
  left alone.
- **Restructuring the card** — new typography scale, moving the CTA or the wishlist control, a
  single-column list view on narrow screens. The owner chose the narrower option.
- **The catalogue's page furniture** — 313 px of a 390 px landscape viewport consumed before the
  grid starts. Brainstormed, paused at the owner's redirection, still queued.

## Verification

- **The alignment claim is the deliverable and is measured directly:** for two adjacent cards in
  one row, one discounted and one not, the `y` of the name, price, stock and actions must match
  pair for pair. Before this work they diverge by 28 px at the stock line.
- Card height before and after, at 390×844 and 844×390.
- The discount badge still appears and disappears as `okay.js` toggles `hidden-xs-up`, checked
  by selecting a discounted variant rather than by reading the CSS.
- With the SKU block commented out, the card renders and neither script throws — the console
  must be clean.
- Contrast sampled over a dark product photograph, both viewports, with the measured ratio
  recorded.
- A scroll trace at 390×844 with the blur active.
- Screenshots at 390×844 and 844×390, opened and looked at. Two tasks in the earlier passes on
  this theme met every numeric criterion while the page was visibly broken, and only looking
  caught them.
