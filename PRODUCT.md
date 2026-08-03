# Product

## Register

product

## Themes

The repository ships two storefront themes. Everything below describes **`design/vibe_shop`**
unless a section says otherwise; `design/okay_shop` has its own section at the end of this file.

## Users

Two audiences, and they are not the same person.

**Shoppers** arrive on a storefront running the `vibe_shop` theme, most of them on a phone,
often from search or a price aggregator, with a specific thing in mind and little patience.
Their job: find the product, confirm it is the right one and in stock, and buy it. They are not
here to admire the site. Every screen either moves them toward checkout or gets out of the way.

**Shop owners** install the theme onto OkayCMS and run it without a designer. They upload
photography of uneven quality, write their own copy, install modules, and set their brand
colour in the admin panel. The theme has to look composed under all of that. It cannot assume
a curated catalogue, professional product shots, or a fixed number of items.

## Product Purpose

`design/vibe_shop` is a general-purpose OkayCMS storefront theme. It exists because the
catalogue-to-checkout path in the stock theme is dated and noisy — dense navigation, competing
brand colours, desktop-first layout with mobile bolted on.

Success is measured on the shopping path, not on the homepage: products are easy to scan,
filters are usable on a phone, stock and price are unambiguous, and nothing between the
product page and the placed order asks the shopper to think.

## Brand Personality

Clear, fast, quiet.

The interface should recede and leave the product and the next action. Confidence is expressed
through restraint and precision — accurate alignment, real typographic hierarchy, immediate
feedback on touch — not through decoration. Copy is plain and direct, never chirpy.

Quiet is not the same as cold. The shop should feel like a place that is well kept, not like an
enterprise dashboard.

## Anti-references

- **Marketplace clutter (Amazon, Rozetka).** Banner walls, red price tags on everything, three
  stacked navigation bars. Noise standing in for hierarchy. This is what the theme looks like
  before this work.
- **Default Bootstrap.** Blue buttons, grey boxes, stock radii — a template that belongs to
  nobody.
- **Generated-design defaults, 2026.** Cream/sand canvas, gradient text, glass cards, a small
  tracked-uppercase eyebrow above every section, endless identical card grids.
- **Cold SaaS minimalism.** Dashboard sterility, air where merchandise should be, product no
  longer the hero.

The last two pull against each other on purpose. Restraint without sterility is the target, and
warmth comes from typography, rhythm and tactile feedback — never from a beige background.

## Design Principles

1. **The product is the hero.** Photography and price get the space and contrast. Chrome is
   monochrome so nothing competes with merchandise.
2. **Colour means something.** Rose is a discount, emerald is availability. Nothing is coloured
   for decoration, which is what lets the two chromatic events actually register.
3. **The shop owner's brand wins.** The theme ships neutral so that whatever colour the owner
   sets in the admin panel is the only opinionated hue on the page.
4. **The phone is the design target.** Desktop is the widened case, not the source layout.
5. **Degrade gracefully under real data.** Three products or thirty thousand, long titles, bad
   photography, missing fields — the layout holds. Fixed aspect ratios, clamped text, defined
   empty states.

## Accessibility & Inclusion

- WCAG 2.1 AA: body text ≥4.5:1, large text and UI components ≥3:1. Every shipped pair is
  verified with a checker, not estimated.
- Full keyboard operability with a visible `:focus-visible` ring throughout. No `outline: none`
  without a replacement. Sheets and modals trap focus and close on Escape.
- Colour is never the sole carrier of meaning: stock states pair a dot with a label, links
  carry an offset underline rather than relying on hue.
- Touch targets ≥44×44px. Controls revealed on hover have a permanently visible fallback under
  `(hover: none)`.
- Every animation has a `prefers-reduced-motion: reduce` alternative.

---

# `design/okay_shop`

The stock OkayCMS 4.5.2 theme, carried in this repository with the fork's security contract
applied to it. A clean database starts on it (`theme = okay_shop` in the seed dump), so it is
the first thing anyone sees after deploying the fork.

**It is not a design project.** A visual redesign was scoped and cancelled on 2026-08-02; this
section describes the theme as it actually is. `vibe_shop` is where this fork's design work
lives.

## Product Purpose

Two jobs, both practical:

1. **A working default.** A fresh install has a complete, functioning storefront without anyone
   choosing a theme first.
2. **A worked example of the porting contract.** It is stock plus exactly the changes the fork
   requires, so anyone bringing their own theme across can read the diff instead of the prose:
   `git diff upstream/master main -- design/okay_shop`.

That second job is why it stays close to stock. The delta is 23 files, all modifications — no
file was added or removed. Most of it is the security contract and the Smarty 5 migration; the
rest is upstream bugs fixed here and housekeeping. `docs/theme-porting.md` walks through it, and
the diff itself is the authority — the per-category split is not worth maintaining as a number.

Measure it: `git diff --stat upstream/master main -- design/okay_shop`.

**The engine's contract binds.** Mutating forms carry `customer_csrf_token` and use POST, not
GET — cart, wishlist, comparison, feedback, subscription. The checkout form additionally carries
a one-shot `checkout_token` so a double submit cannot place a second order
(`docs/UPGRADE-security.md`). Taking this theme, or `vibe_shop`, onto a stock engine is covered
by `docs/theme-to-stock.md`.

## What this theme is not

It is not held to the design or accessibility bar stated above for `vibe_shop`, and it does not
meet it. Measured, not assumed:

- zero `:focus-visible`, zero `prefers-reduced-motion`, zero `(hover: none)` fallbacks in its
  stylesheets (`vibe_shop` has 6, 1 and 2);
- its `#dbdbdb` borders give **1.24:1** against the `#f2f2f2` canvas, against the 3:1 WCAG
  1.4.11 floor for UI components;
- `theme.css` is 4739 lines with no `@media` at all; responsiveness lives in a separate
  desktop-first `media.css`.

This is a known and accepted gap, not an oversight. Anyone who needs the bar met should use
`vibe_shop`. Anyone editing `okay_shop` should keep changes minimal and in service of the two
jobs above — the value of this theme is that its distance from stock is small and legible.
