# vibe_shop — phone polish: gutters, breadcrumbs, forms, cards

Date: 2026-07-29
Theme: `design/vibe_shop`
Branch: `feature/vibe-shop-responsive` (PR #5, open)
Status: design approved, not implemented

Line numbers are as of `6c0ea2a` and will move as items land. Every one of them is a
starting point for a search, never a substitute for one.

## Problem

The owner reported five defects after the product-card pass shipped, with screenshots. All
five were measured at 390x844 before this spec was written; none is speculative.

| # | report | measured |
| --- | --- | --- |
| 1 | "the gutters still aren't everywhere" | `.vs-home__head`'s title sits at 16, the carousel's first card at **0** |
| 2 | "fix the breadcrumbs, lots of pointless gaps" | the trail wraps to 3 rows and spends ~132px of the first screen |
| 3 | "these inputs differ from the others" | delivery fields are **56px**, every theme field is **44px** |
| 4 | "in the cart lots of places have no gap between inputs" | three stacked fields measure a **0px** gap |
| 5 | "the +/- field is very large" | the stepper is **52x134** |
| 6 | "shrink the gaps between cards, grow the padding inside" | gap **16px**, card padding **8px** |

## Changes

### 1. The home page's gutter, done once instead of four times

Task 6 of the previous pass gave four children of `.vs-home__section` a 16px gutter one at a
time. The carousel was the fifth child and the queue never reached it.

Measured: `.vs-home__head` puts its title at 16 while `.vs-home__rail`'s first card sits at
`card1L: 0`. The rail's own comment (`components.css:7530-7538`) states the `-8/+8` pull exists
"so the first and last card line up with the section title" — so moving the title broke the
alignment the pull was built to create.

Every one of those children — `.vs-home__head`, `.vs-home__about`, `.vs-posts`,
`.vs-brands--compact` and the rail — sits inside `<section class="vs-home__section container">`
(`main.tpl:97`, `:147`). The parent zeroes the container's horizontal padding
(`components.css:7465`) and the children were each given it back.

So: scope that zeroing to `@media (min-width: 576px)` and **delete all four compensating
rules** (`components.css:5647-5648` and `:5660-5661`). Below 576px the section keeps the 16px
`.container` already grants, and everything inside it lands on one line because it inherits from
one box. The rail keeps its `-8/+8`; that is what makes the cards align rather than what breaks
them.

This is the same manoeuvre the previous pass used on `.vs-advantages` — constrain the zero
rather than out-specify it — and it removes four rules instead of adding a fifth.

### 2. Breadcrumbs: one line, scrollable, bleeding past the gutter

Each `.vs-crumbs__item` carries `min-height: 44px` (`components.css:2870`) and the list is
`flex-wrap: wrap`, so a product deep in a tree spends three stacked 44px rows on the trail.
The 44px is not the defect — it is the touch target, and it stays. The wrapping is the defect.

Below 576px `.vs-crumbs` becomes `flex-wrap: nowrap` with `overflow-x: auto`, its items
`flex: none`, and the strip bleeds past the page gutter with a negative margin plus an equal
inner padding, so text starts level with the heading at 16px but scrolls to the physical screen
edge. A word cut at the edge is the affordance; that is how both reference shops the owner
supplied behave.

On load the strip is scrolled to its end, so the current position is what the shopper sees and
dragging right-to-left walks back up the tree.

**The edge fade must be driven by scroll position, not painted statically.** A fixed mask would
fade the tail of the last crumb even when there is nothing further to scroll to. Two state
classes toggled on scroll, and the fade keyed off them.

`breadcrumb.tpl` is not touched. The schema.org `BreadcrumbList` stays complete and visible —
which is the reason the "back to parent" chip was rejected: it would have required hiding
structured data that describes content the shopper can no longer see.

### 3. One field pattern in the shop, not two

The fields the owner flagged are not the `DeliveryFields` module's — they come from
`NovaposhtaCost`, and both render the stock `.form__group` / `.form__input` /
`.form__placeholder` triple: a caption `<span>` that follows its input in the DOM and is
positioned over the field, which is why the field is `min-height: 56px` with
`padding: 22px 12px 6px` (`components.css:9963-10015`).

**The theme already tried to fix this and the attempt has never once worked.**
`components.css:6460` sets `.vs-option__extra .form__input { height: 44px }` against a base
`min-height: 56px`. Those are different properties, so there is no conflict for specificity to
resolve — `min-height` simply wins and the fields have been 56px since the rule was written.
It is deleted, not adjusted.

`.form__group` becomes a column flex box, `.form__placeholder` is taken out of absolute
positioning and ordered above the input, and the input drops to `min-height: 44px` with even
padding. The result is the shape every other field on the page already has: caption above,
44px box.

**This applies to all `.form__*` markup, not only the cart.** The owner chose one pattern over
a smaller diff. `FastOrder`'s lightbox uses the same markup and is included on every page, so it
changes too and must be opened and checked — it is the one legacy form that still reaches
shoppers.

`.form__placeholder`'s error state is selected with `.form__input.error ~ .form__placeholder`.
The span still follows the input in the DOM — only its visual order changes — so that selector
keeps working. Confirm rather than assume.

`NovaposhtaCost` ships a city autocomplete. Whether its dropdown is positioned against
`.form__group` has to be established before that element becomes a flex container, and the
autocomplete has to be exercised, not just looked at.

**No module file is edited.** All of this lives in the theme's own stylesheet, which is the only
place this fork permits it.

### 4. The gap that a Bootstrap column ate

`components.css:6452` reads `.vs-option__extra .form__group:last-child { margin-bottom: 0 }`,
written to kill the trailing gap after the final field.

`NovaposhtaCost` renders `.row > .col-lg-6 / .col-lg-3 / .col-lg-3`, one `.form__group` per
column. Each of those groups is the `:last-child` **of its own column**, so the rule fires on
all of them. On a desktop the three columns sit side by side and no vertical gap is wanted, so
the bug is invisible. On a phone the columns stack and the rule deletes exactly the gaps that
stacking creates. Measured tops: 1250, 1322, 1378, 1434 — 16px after the first, then 0, 0.

The rule is narrowed to direct children (`.vs-option__extra > .form__group:last-child`), which
is the case it was written for. `.vs-option__extra > *:last-child` already handles the trailing
gap generally, so nothing is lost.

### 5. Stepper: 52x134 becomes 44x122

Height 52 -> 44, and all three children — both buttons and the value input — 44 -> 40 wide.
With `box-sizing: border-box` and the shell's own 1px border that is 40*3 + 2 = 122, against
44*3 + 2 = 134 today.

The 44px height is the minimum this theme committed to in its touch-target pass. The owner was
offered a 40x104 variant that goes below that floor and chose to hold the line.

### 6. Card gutters and card padding, in opposite directions

Measured: grid `gap: 16px` (`components.css:3245-3250`), card `padding: 8px` below 576px
(`components.css:5602`). More space between the plates than inside them.

Gap 16 -> 8 and padding 8 -> 12. Card width goes 171 -> 175 and the content box 155 -> 151.

**This reverses a decision the first responsive pass made deliberately.** `.vs-card`'s padding
was cut from 12 to 8 at both `:5602` and `:10324` to buy vertical space on small screens. The
owner has now seen the result and asked for the breathing room back. Only the portrait block
changes; the short-viewport block at `:10324` keeps 8px, because a landscape phone is
height-starved in a way a portrait one is not, and that block is later in the file so it still
wins there.

**The binding constraint is the discount row, and it must be re-measured rather than trusted.**
The product-card spec measured the old-price-plus-badge pair at about 145px against 162
available. After this change 151px is available — roughly 6px of headroom against a number that
was measured once, on one product, against a price this shop happens to carry. Measure the
widest real pair in the database. **If it does not fit, the internal padding gives way first,
never the gap** — the gap is what the owner objected to.

## Explicitly out of scope

- **576-991px.** The home page's headings and static blocks still sit at 0 there, and so does a
  landscape phone at 844x390. That band was deliberately left alone by the previous pass and is
  not reopened here, but it is a real gap and is recorded as a conscious deferral, not an
  oversight.
- **The pre-order button overflowing the sticky bar by ~3px at 320px.** Pre-existing, unchanged
  by the last pass, caused by `.vs-btn`'s `nowrap` plus the default `min-width: auto`.
- **Restructuring the checkout.** Six independent defects are being fixed; the flow is not
  being redesigned.
- **The catalogue's page furniture** — 313px of a 390px landscape viewport before the grid
  starts. Brainstormed, paused at the owner's redirection, still queued.

## Verification

Every item is measured at 390x844 before and after, and every screenshot is opened and looked
at. **Three tasks in this project have now met every numeric criterion while the page was
visibly broken** — the icon-only CTA that left a landscape phone with no reachable buy button,
the dead title clamp that still reported `line-clamp: 2`, and the sticky pill that escaped its
own bar while `overlap: false` held. Looking is not optional and it is not satisfied by the
numbers passing.

- **Item 1:** on the home page the section title, the brand grid, the article grid and the
  rail's first card all report the same left edge. The rail still bleeds `-8`. 576/768/1440
  unchanged, byte for byte.
- **Item 2:** the trail is one row at every phone width; it scrolls; the fade appears only on
  the side that has more content; the strip does not block vertical page scrolling by finger —
  a horizontal scroller inside a vertical page can swallow diagonal gestures on some Android
  builds, and that has to be tried, not reasoned about.
- **Item 3:** delivery fields and contact fields report the same height and the same caption
  position. `FastOrder`'s lightbox opened and looked at. The city autocomplete exercised and its
  dropdown still landing where it should. A rejected field still turns its caption red.
- **Item 4:** the stacked fields report equal, non-zero gaps; the trailing gap after the last
  field has not come back; the desktop three-column row is unchanged.
- **Item 5:** stepper measures 44x122; `okay.js`'s `amount_change` still fires from both
  buttons; the focus ring is still drawn inwards and still visible.
- **Item 6:** grid gap 8, card padding 12, and the widest real old-price-plus-badge pair in the
  database still fits on one line. Card height recorded before and after.
- **The cart cannot be measured empty.** `/cart?variant=<id>&amount=1` is a GET that adds and
  renders in one navigation, which is what every measurement in this spec used.
- **All three `CssConfig` traps checked on every rule touched**: no comment sharing a line with
  a declaration, no `var(--okay-*)` outside `property: var(--okay-x);`, no selector broken
  anywhere but immediately after a comma. The `TOUCH TARGETS` block stays last in the file.

## Addendum — item 7: the sort control on a phone

Reported by the owner after the plan was written, with a screenshot. Desktop is fine.

`.vs-sort__group` is `flex-wrap: wrap` inside a bordered, filled box. At 390px the four
pills fall into a 2x2 grid, so a secondary control spends about 96px of the first screen and
reads as a panel rather than as a choice.

Below 576px it becomes **one 44px row carrying the current sort, which opens the theme's
bottom sheet on tap**. The owner was offered a single scrolling row of pills and a de-boxed
2x2 and chose the sheet: sort is a choose-one-of-four, and unlike a breadcrumb path all four
options have to be visible at the moment of choosing.

**Three findings decided how it is built, all established before the task was written.**

- **The sheet primitive is entirely declarative.** `data-vs-sheet-open="<id>"` on a trigger,
  `.vs-sheet` plus an `id` on the panel, `data-vs-sheet-close` on any closer, one
  `.vs-sheet__backdrop` per page — `vibe.js` already binds all of it, including the focus
  trap, the scroll lock, Escape and focus restore. **This item ships no JavaScript.**
- **The stylesheet warns that a sheet nested inside a positioned, z-indexed ancestor paints
  under the page whatever `--vs-z-modal` says** (`components.css:1385-1390`). Measured: of the
  ten ancestors between `.vs-sort__group` and `<body>`, none creates a stacking context —
  `main.main` is `position: relative` with `z-index: auto`, which does not — and none clips.
  So the group can become the sheet **in place**, and the task must both re-verify that and
  leave the finding in a comment.
- **In place is not a convenience, it is what keeps the control correct.** The four options
  are four `<form>`s posting `prg_seo_hide`, re-rendered inside `.fn_products_sort` on every
  ajax sort. A second copy in `<body>` would not be re-rendered and its tick would drift out
  of sync with the grid it describes.

`.vs-sheet` geometry is unconditional, so the group must be un-sheeted above 576px exactly as
`.vs-filters` is above 992px — the same manoeuvre, a different width.

The trigger's label is the active option's name, derived from `$sort` in the template so the
ajax re-render keeps it honest. Price, name and rating keep their two-arrow sprite: tapping an
already-active option still flips its direction, and the sprite is what says so.

**Out of scope:** the desktop control, which the owner said is fine, and the `prg_seo_hide`
POST mechanism, which exists to keep sort URLs out of the index and is not being touched.
