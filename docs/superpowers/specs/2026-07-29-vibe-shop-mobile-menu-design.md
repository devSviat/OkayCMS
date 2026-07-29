# vibe_shop — mobile menu, second pass

Date: 2026-07-29
Theme: `design/vibe_shop`
Branch: `feature/vibe-shop-responsive` (PR #5, open)
Status: design approved, not implemented

## Problem

The first menu pass (spec item 9 of `2026-07-28-vibe-shop-responsive-design.md`) fixed one
thing — every row's text now starts at the same 52 px rail — and the owner's verdict on the
result was that it still looks bad. That verdict is correct, and the earlier scope was too
narrow: alignment was never the reason the menu looked wrong.

Measured on the rendered menu, the eleven top-level rows are assembled from four different
visual languages:

| # | defect | evidence |
| --- | --- | --- |
| 1 | The submenu chevron sits in its own bordered cell | `.nav-next` carries `border-left: 1px solid rgb(224,224,226)` on "Категорії", "Клієнтам", currency and language. A vertical rule splits those four rows into two apparent buttons. |
| 2 | Two type scales in one list | Nav rows are 15 px `rgb(28,28,32)`; currency, language, phone and email are 13 px `rgb(107,107,113)`. Same row height, same rail, but smaller and dimmer — so the lower half reads as disabled. |
| 3 | The five CMS rows have an empty rail | Aligned but with nothing in the 20 px slot, "Блог / Клієнтам / Акції / Бренди / Контакти" read as children of "Порівняння" rather than as peers. |
| 4 | Social links are lowercase text pills | A third visual language, in `bottom-nav`. |
| 5 | The close control looks like a text input | `.nav-close` renders as a grey filled box with a `×` floated right, sitting at the very bottom of the panel. |

Nothing here is an alignment problem. Rows 1, 2 and 3 are the ones that make the screenshot
look unfinished.

## Changes

**1. Remove the chevron's dividing rule.** Drop `border-left` from `.nav-next`. The chevron
stays; the row becomes one target again instead of two. This is the single loudest defect and
the cheapest fix.

**2. One type scale.** Currency, language, phone and email move to the same 15 px and the same
`--vs-text` colour as the nav rows. There is no hierarchy reason for "Валюта" to be quieter
than "Бренди" — they are peers in the same list, and dimming half a menu is not hierarchy, it
is the appearance of disablement.

**3. Fill the CMS rail with a marker, not a borrowed icon.** A 6 px dot rendered via `::before`
on `.hc-offcanvas-nav .vs-menu__link`, centred in the 20 px rail slot. CMS pages are arbitrary —
the shop owner names them — so any real glyph would assert a meaning the theme cannot know. A
list marker asserts only "this is an item", which is true. Scoped inside `.hc-offcanvas-nav` so
the desktop menu, which shares `menu.tpl`, is untouched, and implemented in CSS so no template
change is needed.

**4. Social links become icons.** Round 44 px buttons in a row, replacing the lowercase text
pills. `svg.tpl` ships no social glyphs, so they are added: facebook, instagram, telegram,
youtube, tiktok, twitter/X, viber, linkedin, **plus a generic fallback**. The fallback is not
optional — `bottom-nav` is built by extracting the domain from
`$settings->site_social_links` with a regular expression, so a shop can supply any host, and
an unmatched domain must not render an empty circle. The network name stays as the button's
accessible name.

Hand-drawn brand marks carry a real risk: a wonky "f" looks worse than the text it replaced.
Each glyph is checked on a screenshot at 390 px, and any that does not read cleanly falls back
to its text label rather than shipping badly drawn.

**5. Move the close control to the panel header, as a bare ×.** `scripts.tpl:196` currently
passes `insertClose: -1`, which is why the control lands last. Setting it to `1` prepends it
into the first list, and the panel's top row becomes what a panel header should be: brand on
the left, close on the right.

The account link moves out of that header and becomes the first row of the navigation list. It
already carries the `user` glyph, so it lands on the existing rail with no special case, and
the header stops carrying three competing elements at 390 px wide.

**The label is hidden, not deleted.** This build of hc-offcanvas-nav has no `ariaLabels`
option — its defaults are `{maxWidth, pushContent, position, levelOpen, levelSpacing,
levelTitles, navTitle, navClass, disableBody, closeOnClick, customToggle, insertClose,
insertBack, labelClose, labelBack}` and nothing else — and it renders the label as a bare text
node inside the anchor, which cannot be wrapped from the template. So `labelClose` keeps its
translated string and the text is moved off screen with `text-indent`, leaving the `×` drawn by
the `<span>` the library already emits. Setting `labelClose: ''` would leave the button with no
accessible name at all.

**6. Capitalisation of social labels — already done, no work.** `components.css` already carries
`text-transform: capitalize` on `.hc-offcanvas-nav ul.bottom-nav .nav-item`, which is why the
current screenshot reads "Facebook" and "Twitter" rather than the bare lowercase domains the
regex produces. Item 4 removes that rule along with the text it capitalised. It is only worth
keeping if a glyph turns out not to read cleanly and its label is reverted to text.

## Explicitly out of scope

- **Restructuring the menu into labelled sections.** Fixing the four visual languages is what
  the screenshot demands; grouping is a separate question and may not be needed once rows 1-3
  are consistent.
- **The currency switcher's `href="#"` plus inline `onclick`.** It looks like a defect, but the
  desktop switcher deliberately POSTs a form carrying `prg_seo_hide` to keep `currency_id` URLs
  out of the index. Making the mobile one a GET link would undo that. It needs its own item.
- **The CMS menu item reading "Контакты" in Russian** while the rest of the menu is Ukrainian.
  That is database content, not theme markup.
- **The first-screen furniture pass** (313 px of 390 on the catalogue, 250 px on the product
  page). Brainstormed and paused at the owner's redirection; it resumes after this.

## Verification

The menu does not exist until it is opened — every check clicks `.fn_menu_switch` and waits.

- **Per row:** text edge, font size and colour must be uniform across every row outside
  `top-nav` and `bottom-nav`. The two clusters recorded above — 15 px/`#1c1c20` and
  13 px/`#6b6b71` — must collapse to one.
- **No `border-left`** on any `.nav-next` in the rendered menu.
- **Every CMS row** shows its dot; no row outside `bottom-nav` has an empty rail slot.
- **Every social link** renders a glyph, including one pointed at a domain not in the set, to
  prove the fallback works.
- **The close control** sits in the panel header, renders as an `×` with no filled box, and
  still exposes its translated accessible name.
- **The desktop menu and the desktop switcher are unchanged** — `menu.tpl` and `svg.tpl` are
  shared, so both are re-checked at 1440×900.
- **Screenshots at 390×844 and 844×390, opened and looked at.** Two tasks in the previous pass
  passed every numeric criterion while the page was visibly wrong; only looking caught them.
