# Product

## Register

product

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
