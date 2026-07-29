# vibe_shop phone polish — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the six phone defects the owner reported after the product-card pass — the gutter the carousel never received, wrapping breadcrumbs, two competing field patterns in one checkout, a vertical gap a Bootstrap column ate, an oversized quantity stepper, and a card with more space outside it than inside.

**Architecture:** Every change is CSS in `design/vibe_shop/css/components.css`, except one small ES5 module appended to `design/vibe_shop/js/vibe.js` for the breadcrumb scroller. No module file is edited — this fork forbids it, and the legacy `.form__*` markup is restyled from the theme's own sheet instead. No `.tpl` file is touched at all.

**Tech Stack:** Plain CSS (compiled by `Okay\Core\TemplateConfig\CssConfig`), ES5 JavaScript, verified in headless Chrome via `shot.mjs` + `puppeteer-core`.

**Spec:** `docs/superpowers/specs/2026-07-29-vibe-shop-phone-polish-design.md`

## Global Constraints

- **Never edit a module, a core file, or another theme.** Restyle from `design/vibe_shop/css/components.css` only.
- **`CssConfig` trap 1:** a comment sharing a line with a declaration silently deletes that declaration. Every comment on its own line.
- **`CssConfig` trap 2:** `var(--okay-*)` substitutes only in the exact shape `property: var(--okay-x);` — one call per line, no fallback. `var(--vs-*)` is ordinary CSS and untouched.
- **`CssConfig` trap 3:** a selector may break across lines only immediately after a comma.
- **The `TOUCH TARGETS` block must stay last in `components.css`.** It wins at equal specificity on source order alone. Never add a rule after it.
- **Comments in Ukrainian or English, never Russian.**
- **No `Co-Authored-By:`, no `Claude-Session:`, no Claude/Anthropic attribution** in any commit message.
- **Clear caches before every measurement:** `rm -f compiled/vibe_shop/*.php cache/css/*`
- **The measurement harness:** `node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url <url> --width W --height H --out <png> --eval "<js>"`. Site at `http://localhost`.
- **The cart cannot be measured empty.** `http://localhost/cart?variant=283&amount=1` is a GET that adds the item and renders the full checkout in one navigation.
- **Looking at the screenshot is a required step, not a formality.** Three tasks in this project have met every numeric criterion while the page was visibly broken. If a step says look, open the PNG and describe what is actually on it.

**Execution order:** Tasks 1 and 6 both edit the `@media (max-width: 575px)` block; Tasks 3 and 4 both edit the legacy form rules. Run the tasks in numerical order and never two at once — a second writer in `components.css` while a first is mid-edit has already cost this project a round.

---

### Task 1: The home gutter, done once instead of four times

**Files:**
- Modify: `design/vibe_shop/css/components.css` (the `@media (max-width: 575px)` block near `:5643-5665`, and `.vs-home__section` near `:7465`)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Record the defect**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/ --width 390 --height 844 \
  --eval "(()=>{const L=e=>e?Math.round(e.getBoundingClientRect().left):null;return JSON.stringify({title:L(document.querySelector('.vs-home__title')),rail:L(document.querySelector('.vs-home__rail')),card1:L(document.querySelector('.vs-home__rail .vs-card')),brandItem:L(document.querySelector('.vs-brands__item')),postCard:L(document.querySelector('.vs-post-card')),docW:document.documentElement.scrollWidth})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected before the fix: `title: 16`, `rail: -8`, `card1: 0`, `brandItem: 16`, `postCard: 16`, `docW: 390`.

- [ ] **Step 2: Stop zeroing the section's horizontal padding below 576px**

In `design/vibe_shop/css/components.css`, replace:

```css
.vs-home__section {
	padding: var(--vs-space-8) 0 0;
	overflow-x: clip;
	overflow-y: visible;
}
```

with:

```css
/* The horizontal zero is scoped, not unconditional. Below 576px the section    */
/* keeps the 16px .container already grants, so the headings, the two static    */
/* grids and the rail's first card all measure from one box - the rail's        */
/* -8/+8 pull then lands its first card exactly on the title, which is what     */
/* that pull was written for. Above 576px the container is centred and wide     */
/* and the zero is still what the rail is calibrated against.                   */

.vs-home__section {
	padding-top: var(--vs-space-8);
	padding-bottom: 0;
	overflow-x: clip;
	overflow-y: visible;
}

@media (min-width: 576px) {
	.vs-home__section {
		padding-left: 0;
		padding-right: 0;
	}
}
```

- [ ] **Step 3: Delete the four compensating rules Task 6 added**

Inside the `@media (max-width: 575px)` block, delete both of these rules **together with their comment blocks**:

```css
	/* The rails bleed past the edge on purpose - a card peeking off-screen is what  */
	/* says the row scrolls. A heading cannot scroll, so it should never have taken  */
	/* that treatment. It gets the page gutter back while the rails keep theirs.     */

	.vs-home__head,
	.vs-home__about {
		padding-left: var(--vs-space-4);
		padding-right: var(--vs-space-4);
	}

	/* Same defect, different tag: these two grids sit flush in .vs-home__section  */
	/* exactly like the headings above did, but neither one bleeds on purpose - no */
	/* negative margin, no swiper, nothing documented. Scoped to the home context  */
	/* (not bare .vs-posts / .vs-brands--compact) because both classes are reused  */
	/* on /blog, /author and /brands inside an ordinary .container, which already */
	/* gets its gutter from the rule above - an unscoped fix here would double it. */

	.vs-home__section .vs-posts,
	.vs-home__brands .vs-brands--compact {
		padding-left: var(--vs-space-4);
		padding-right: var(--vs-space-4);
	}
```

**Do not touch the `.container-fluid, .container-less, .container` rule immediately above them** — that is the rule the whole fix now leans on.

The first of those deleted comments claims the rails bleed "so a card peeks off-screen". That is wrong and is being deleted along with it: the rail's own comment at `components.css:7530-7538` says the pull exists so the first and last card line up with the section title.

- [ ] **Step 4: Verify the alignment and the absence of double gutters**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/ --width 390 --height 844 --out /tmp/home-390.png --full \
  --eval "(()=>{const L=e=>e?Math.round(e.getBoundingClientRect().left):null;return JSON.stringify({title:L(document.querySelector('.vs-home__title')),rail:L(document.querySelector('.vs-home__rail')),card1:L(document.querySelector('.vs-home__rail .vs-card')),brandItem:L(document.querySelector('.vs-brands__item')),postCard:L(document.querySelector('.vs-post-card')),about:L(document.querySelector('.vs-home__about_body')),adv:L(document.querySelector('.vs-advantages .block')),docW:document.documentElement.scrollWidth})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `title: 16`, `card1: 16`, `brandItem: 16`, `postCard: 16`, `about: 16`, `adv: 16`, `rail: 8`, and `docW: 390` — no horizontal overflow. **`brandItem` and `postCard` must be 16, not 32** — if they are 32 a compensating rule survived the delete.

- [ ] **Step 5: Verify nothing above 576px moved**

```bash
cd /home/sviat/projects/OkayCMS
for w in 576 768 992 1440; do
  printf "%-6s " "$w"
  node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/ --width $w --height 900 \
    --eval "(()=>{const L=e=>e?Math.round(e.getBoundingClientRect().left):null;const s=document.querySelector('.vs-home__section');return JSON.stringify({secPadL:getComputedStyle(s).paddingLeft,title:L(document.querySelector('.vs-home__title')),card1:L(document.querySelector('.vs-home__rail .vs-card')),docW:document.documentElement.scrollWidth})})()" 2>&1 | sed -n '/evalResult/p'
done
```

Expected: `secPadL: "0px"` at every one of those widths, and `title`/`card1` identical to what the same command returns on `git stash`-ed baseline. **Record both sets of numbers in the report** — "unchanged" is a claim, not an observation, unless the before values are written down.

- [ ] **Step 6: Look at it**

Open `/tmp/home-390.png` and describe what you see. The "Хіти продажу" heading and the first product card underneath it must start on the same vertical line. So must "Бренди" and its first tile, and "Новини" and its first article card.

- [ ] **Step 7: Compiler traps, then commit**

```bash
cd /home/sviat/projects/OkayCMS
grep -nE '^[^/]*[a-z-]+:[^;]*;[^/]*/\*' design/vibe_shop/css/components.css | head
grep -n 'var(--okay-' design/vibe_shop/css/components.css | grep -vE '^[0-9]+:\s*[a-z-]+: var\(--okay-[a-z0-9-]+\);\s*$' | head
tail -40 design/vibe_shop/css/components.css | grep -n 'TOUCH TARGETS' || grep -n 'TOUCH TARGETS' design/vibe_shop/css/components.css
```

The first two must print nothing. The third confirms where the `TOUCH TARGETS` block sits; it must still be the last block in the file.

```bash
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): give the home section one gutter instead of four

The previous pass handed the gutter to four children of .vs-home__section
one at a time. The carousel was the fifth and never got it, so its first
card sat at 0 while the heading above it sat at 16 - breaking the very
alignment the rail's -8/+8 pull exists to create. The section now keeps
the container's padding below 576px and the four compensating rules are
gone."
```

---

### Task 2: Breadcrumbs — one scrolling row that bleeds past the gutter

**Files:**
- Modify: `design/vibe_shop/css/components.css` (the `@media (max-width: 575px)` block)
- Modify: `design/vibe_shop/js/vibe.js` (append one module inside the existing IIFE)

**Interfaces:**
- Consumes: nothing.
- Produces: the CSS classes `is-overflow-start` and `is-overflow-end` on `.vs-crumbs`, set by the JS in Step 3 and consumed by the CSS in Step 2. Both names must match exactly.

- [ ] **Step 1: Record the defect**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/products/divan-redking --width 390 --height 844 \
  --eval "(()=>{const c=document.querySelector('.vs-crumbs');const r=c.getBoundingClientRect();const items=[...c.querySelectorAll('.vs-crumbs__item')].map(e=>Math.round(e.getBoundingClientRect().top));return JSON.stringify({h:Math.round(r.height),rows:[...new Set(items)].length,itemTops:items})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected before the fix: a height well over 44 and more than one distinct `top` value — the trail is wrapping.

- [ ] **Step 2: Make it one scrolling row**

Add to the `@media (max-width: 575px)` block in `design/vibe_shop/css/components.css`:

```css
	/* The trail is the one element on the page that deliberately leaves the        */
	/* gutter. It pulls out by the page padding and puts the same value back        */
	/* inside, so its first crumb starts level with the h1 while the strip itself   */
	/* reaches the physical screen edge - a word cut at that edge is what says the  */
	/* row scrolls. Three wrapped 44px rows became one; the 44px stays, because     */
	/* that is the touch target, and the wrapping was the defect.                   */

	.vs-crumbs {
		flex-wrap: nowrap;
		margin-left: calc(var(--vs-space-4) * -1);
		margin-right: calc(var(--vs-space-4) * -1);
		padding-left: var(--vs-space-4);
		padding-right: var(--vs-space-4);
		overflow-x: auto;
		overflow-y: hidden;
		scrollbar-width: none;
	}

	.vs-crumbs::-webkit-scrollbar {
		display: none;
	}

	.vs-crumbs__item {
		flex: none;
	}

	/* The fade follows the scroll position instead of being painted once. A        */
	/* static mask would dim the last crumb's tail even when there is nothing       */
	/* further to scroll to, which reads as truncated text rather than as more      */
	/* content. vibe.js sets these two classes.                                     */

	.vs-crumbs.is-overflow-start {
		mask-image: linear-gradient(to right, transparent 0, #000 var(--vs-space-6));
	}

	.vs-crumbs.is-overflow-end {
		mask-image: linear-gradient(to left, transparent 0, #000 var(--vs-space-6));
	}

	.vs-crumbs.is-overflow-start.is-overflow-end {
		mask-image: linear-gradient(to right, transparent 0, #000 var(--vs-space-6), #000 calc(100% - var(--vs-space-6)), transparent 100%);
	}
```

- [ ] **Step 3: Add the scroller module to `vibe.js`**

Append inside the existing IIFE in `design/vibe_shop/js/vibe.js`, just before its closing `}());`:

```js
    /* The breadcrumb trail is a single scrolling row below 576px. Two things
       need script. It opens scrolled to its end, because where the shopper
       actually is matters more than where the tree starts, and dragging
       right-to-left then walks back up. And the edge fade has to follow the
       scroll rather than be painted once - a fixed mask dims the last crumb's
       tail even when there is nothing further to reach.

       scrollWidth is read again on load and on resize: web fonts land after
       this footer script runs and change every crumb's width. */
    (function () {
        var trail = document.querySelector('.vs-crumbs');
        if (!trail) return;

        function syncFade() {
            var max = trail.scrollWidth - trail.clientWidth;
            var scrollable = max > 1;
            trail.classList.toggle('is-overflow-start', scrollable && trail.scrollLeft > 1);
            trail.classList.toggle('is-overflow-end', scrollable && trail.scrollLeft < max - 1);
        }

        function toEnd() {
            trail.scrollLeft = trail.scrollWidth;
            syncFade();
        }

        toEnd();
        window.addEventListener('load', toEnd);
        trail.addEventListener('scroll', syncFade, {passive: true});
        window.addEventListener('resize', syncFade);
    }());
```

- [ ] **Step 4: Verify one row, the scroll, and the fade**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/products/divan-redking --width 390 --height 844 --out /tmp/crumbs-390.png \
  --eval "(async()=>{const c=document.querySelector('.vs-crumbs');const tops=[...c.querySelectorAll('.vs-crumbs__item')].map(e=>Math.round(e.getBoundingClientRect().top));const first=c.querySelector('.vs-crumbs__item');const atEnd={cls:c.className,scrollLeft:Math.round(c.scrollLeft),max:Math.round(c.scrollWidth-c.clientWidth)};c.scrollLeft=0;await new Promise(r=>setTimeout(r,120));const atStart={cls:c.className,firstLeft:Math.round(first.getBoundingClientRect().left)};return JSON.stringify({h:Math.round(c.getBoundingClientRect().height),rows:[...new Set(tops)].length,atEnd,atStart,docW:document.documentElement.scrollWidth})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `h: 44`, `rows: 1`, `atEnd.scrollLeft` equal to `atEnd.max` and the class list containing `is-overflow-start` but **not** `is-overflow-end`; `atStart.cls` containing `is-overflow-end` but **not** `is-overflow-start`; `atStart.firstLeft: 16`; `docW: 390`.

- [ ] **Step 5: Verify a short trail gets no fade at all**

A page whose trail fits must not be masked.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/cart --width 390 --height 844 \
  --eval "(()=>{const c=document.querySelector('.vs-crumbs');if(!c)return 'no trail';return JSON.stringify({cls:c.className,overflowing:c.scrollWidth>c.clientWidth+1})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `overflowing: false` and neither `is-overflow-*` class present.

- [ ] **Step 6: Verify the page still scrolls vertically under a finger on the strip**

A horizontal scroller inside a vertically scrolling page can swallow diagonal gestures. This has to be tried, not reasoned about.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/products/divan-redking --width 390 --height 844 \
  --eval "(()=>{const c=document.querySelector('.vs-crumbs');const s=getComputedStyle(c);return JSON.stringify({touchAction:s.touchAction,overscroll:s.overscrollBehavior,overflowY:s.overflowY})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `touchAction: "auto"` — the strip must not declare `touch-action: pan-x`, which is what would trap a vertical swipe. If any of these three values would block a vertical pan, say so in the report rather than shipping it.

- [ ] **Step 7: Verify desktop is untouched**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/products/divan-redking --width 1440 --height 900 \
  --eval "(()=>{const c=document.querySelector('.vs-crumbs');const s=getComputedStyle(c);return JSON.stringify({wrap:s.flexWrap,overflowX:s.overflowX,mask:s.maskImage,h:Math.round(c.getBoundingClientRect().height)})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `wrap: "wrap"`, `overflowX: "visible"`, `mask: "none"`.

- [ ] **Step 8: Look at it, check the console, commit**

Open `/tmp/crumbs-390.png`. The trail must be a single row whose text runs to the right screen edge, with the h1 below it. Confirm the harness reported no console errors.

```bash
cd /home/sviat/projects/OkayCMS
grep -nE '^[^/]*[a-z-]+:[^;]*;[^/]*/\*' design/vibe_shop/css/components.css | head
grep -n 'var(--okay-' design/vibe_shop/css/components.css | grep -vE '^[0-9]+:\s*[a-z-]+: var\(--okay-[a-z0-9-]+\);\s*$' | head
git add design/vibe_shop/css/components.css design/vibe_shop/js/vibe.js
git commit -m "fix(vibe_shop): put the breadcrumb trail on one scrolling row

It wrapped to three 44px rows on a phone and spent about 132px of the
first screen. It is now a single row that bleeds past the page gutter and
scrolls, opening at its end so the shopper's own position is what shows.
The markup and the schema.org list are untouched."
```

---

### Task 3: One field pattern in the shop, not two

**Files:**
- Modify: `design/vibe_shop/css/components.css` (`.form__group` / `.form__input` / `.form__placeholder` near `:9963-10015`, and the `.vs-option__extra .form__input` rules near `:6460-6474`)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing later tasks depend on. Task 4 edits a neighbouring rule — do not do both in one commit.

- [ ] **Step 1: Record the two patterns**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 --out /tmp/fields-before.png --full \
  --eval "(()=>{const one=e=>e?{h:Math.round(e.getBoundingClientRect().height),pad:getComputedStyle(e).padding}:null;return JSON.stringify({theme:one(document.querySelector('.vs-field')),legacy:one(document.querySelector('.vs-option__extra .form__input')),legacyCount:document.querySelectorAll('.vs-option__extra .form__input').length})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected before the fix: `theme.h: 44`, `legacy.h: 56` with `pad: "22px 12px 6px"`.

- [ ] **Step 2: Establish what the autocomplete is positioned against**

`NovaposhtaCost` ships a city autocomplete. `.form__group` is about to become a flex container, and if the dropdown is absolutely positioned against it that is a change of containing-block behaviour worth knowing about **before** the edit, not after.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 \
  --eval "(async()=>{const i=document.querySelector('.city_novaposhta_for_door');if(!i)return 'no city input';i.focus();i.value='Ки';i.dispatchEvent(new Event('input',{bubbles:true}));i.dispatchEvent(new KeyboardEvent('keyup',{bubbles:true}));await new Promise(r=>setTimeout(r,1500));const dd=document.querySelector('.autocomplete-suggestions');return JSON.stringify({found:!!dd,pos:dd?getComputedStyle(dd).position:null,parent:dd?dd.parentElement.className.slice(0,40):null,top:dd?Math.round(dd.getBoundingClientRect().top):null})})()" 2>&1 | sed -n '/evalResult/p'
```

Record the answer verbatim in the report whatever it is, including "no city input" or `found: false`. Step 6 repeats this command and compares.

- [ ] **Step 3: Turn the pinned caption into a label above the field**

In `design/vibe_shop/css/components.css`, replace this comment and the three rules it introduces:

```css
/* .form__placeholder is a <span> that FOLLOWS its input in the DOM, which the */
/* legacy sheet floated over the field and animated on focus. It is pinned      */
/* instead: the field is tall enough to hold a caption line above the value and */
/* the caption never moves, so it reads the same whether or not okay.js has     */
/* toggled .filled - and a shopper filling the form never watches text slide.   */
```

with:

```css
/* .form__placeholder is a <span> that FOLLOWS its input in the DOM. The legacy */
/* sheet floated it over the field; this layer used to pin it inside a 56px     */
/* box. It is now a plain caption above a 44px field - the same shape           */
/* .vs-form__label and .vs-field already have - so the checkout stops showing   */
/* two different kinds of input on one screen. The span keeps its DOM position  */
/* and is lifted visually by `order`, which is what keeps the                   */
/* `.form__input.error ~ .form__placeholder` selector working.                  */
```

Then change `.form__group` from:

```css
.form__group {
	position: relative;
	margin-bottom: var(--vs-space-3);
}
```

to:

```css
.form__group {
	position: relative;
	display: flex;
	flex-direction: column;
	margin-bottom: var(--vs-space-3);
}
```

Change `.form__input, .form__textarea` from:

```css
	min-height: 56px;
	padding: 22px var(--vs-space-3) 6px;
```

to:

```css
	min-height: 44px;
	padding: 0 var(--vs-space-3);
```

leaving every other declaration in that rule exactly as it is.

`.form__textarea` immediately below already re-declares `min-height: 96px`; add its own padding so a multi-line box does not sit on a single centred line:

```css
.form__textarea {
	min-height: 96px;
	padding: var(--vs-space-3);
	line-height: var(--vs-leading-body);
	resize: vertical;
}
```

Change `.form__placeholder` from:

```css
.form__placeholder {
	position: absolute;
	top: 8px;
	left: var(--vs-space-3);
	z-index: 1;
	color: var(--vs-text-muted);
	font-size: var(--vs-text-xs);
	line-height: 1.2;
	white-space: nowrap;
	text-overflow: ellipsis;
	pointer-events: none;
}
```

to:

```css
.form__placeholder {
	order: -1;
	margin-bottom: var(--vs-space-2);
	color: var(--vs-text-muted);
	font-size: var(--vs-text-sm);
	line-height: var(--vs-leading-body);
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
	pointer-events: none;
}
```

The size and colour are `.vs-form__label`'s, not new values — that rule is `margin-bottom: var(--vs-space-2); color: var(--vs-text-muted); font-size: var(--vs-text-sm);`.

- [ ] **Step 4: Remove the rule that has never worked, and match the focus colour**

`components.css:6460` sets `height: 44px` against a base `min-height: 56px`. Those are different properties, so specificity never had anything to resolve — `min-height` simply won and the declaration has been dead since it was written. The base now supplies 44px, so the whole sizing rule goes.

Delete:

```css
/* Payment and delivery modules render their own markup in here and this layer  */
/* does not own it. These rules only lift the stock form controls to the same    */
/* height, radius and border as the rest of the page.                            */

.vs-option__extra .form__input,
.vs-option__extra .form__input_captcha {
	height: 44px;
	border-color: var(--vs-border);
	border-radius: var(--vs-radius-sm);
	background-color: var(--vs-n-25);
	font-size: var(--vs-text-base);
}
```

and replace the hover/focus rule that follows it:

```css
.vs-option__extra .form__input:hover,
.vs-option__extra .form__input:focus,
.vs-option__extra .form__input_captcha:hover,
.vs-option__extra .form__input_captcha:focus {
	border-color: var(--vs-border-strong);
	background-color: var(--vs-surface);
}
```

with a pair that matches what `.vs-field` does — `--vs-border-strong` on hover, `--vs-ink` on focus:

```css
/* Same two states .vs-field uses, so a delivery field and a contact field       */
/* respond identically. The single combined rule this replaces gave focus the    */
/* hover colour, which made the cart the only place in the shop where a focused  */
/* field looked like a hovered one.                                              */

.vs-option__extra .form__input:hover,
.vs-option__extra .form__input_captcha:hover {
	border-color: var(--vs-border-strong);
	background-color: var(--vs-surface);
}

.vs-option__extra .form__input:focus,
.vs-option__extra .form__input_captcha:focus {
	border-color: var(--vs-ink);
	background-color: var(--vs-surface);
}
```

**`.form__input_captcha` has its own base rule near `:10087`.** Read it before deleting the sizing rule above and confirm the captcha input is not left unstyled. If it is, say so and keep exactly the declarations it needs.

- [ ] **Step 5: Verify the two patterns became one**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 --out /tmp/fields-after.png --full \
  --eval "(()=>{const one=e=>{const r=e.getBoundingClientRect();return {h:Math.round(r.height),y:Math.round(r.top)}};const t=document.querySelector('.vs-field');const l=document.querySelector('.vs-option__extra .form__input');const cap=l.parentElement.querySelector('.form__placeholder');return JSON.stringify({theme:one(t),legacy:one(l),captionAboveField:cap.getBoundingClientRect().bottom<=l.getBoundingClientRect().top+1,legacyHeights:[...document.querySelectorAll('.vs-option__extra .form__input')].map(e=>Math.round(e.getBoundingClientRect().height))})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `theme.h: 44`, `legacy.h: 44`, `captionAboveField: true`, and every entry in `legacyHeights` equal to 44.

- [ ] **Step 6: Re-run the autocomplete probe and compare**

Run Step 2's command again unchanged. The dropdown must still be found and must still land under its input. Put both results side by side in the report.

- [ ] **Step 7: Verify the error caption still turns red**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 \
  --eval "(()=>{const i=document.querySelector('.vs-option__extra .form__input');i.classList.add('error');const cap=i.parentElement.querySelector('.form__placeholder');return JSON.stringify({capColor:getComputedStyle(cap).color,inputBorder:getComputedStyle(i).borderTopColor})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `capColor` is the `--vs-sale-600` red, proving `.form__input.error ~ .form__placeholder` still matches after the visual reorder.

- [ ] **Step 8: Open the FastOrder lightbox and look at it**

The same markup drives `FastOrder`, which is included on every page and lifted into a fancybox from the rocket button on a product card. It changes too.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/all-products --width 390 --height 844 --out /tmp/fastorder-390.png \
  --eval "(async()=>{const b=document.querySelector('.vs-card__fast, .fn_fast_order, [class*=fast]');if(!b)return 'no fast order trigger found';b.click();await new Promise(r=>setTimeout(r,1200));const f=document.querySelector('.fancybox-content .form__input, .fancybox-slide .form__input');return JSON.stringify({trigger:b.className.slice(0,40),field:f?{h:Math.round(f.getBoundingClientRect().height),pad:getComputedStyle(f).padding}:null})})()" 2>&1 | sed -n '/evalResult/p'
```

If the selector guess does not find the trigger, find the real one in `design/vibe_shop/html/product_list.tpl` rather than skipping the step. **Open `/tmp/fastorder-390.png` and describe it.** A caption sitting on top of a value, or a lightbox taller than the screen, is exactly what this step exists to catch.

- [ ] **Step 9: Compiler traps and commit**

```bash
cd /home/sviat/projects/OkayCMS
grep -nE '^[^/]*[a-z-]+:[^;]*;[^/]*/\*' design/vibe_shop/css/components.css | head
grep -n 'var(--okay-' design/vibe_shop/css/components.css | grep -vE '^[0-9]+:\s*[a-z-]+: var\(--okay-[a-z0-9-]+\);\s*$' | head
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): give the legacy form markup the theme's field shape

Delivery fields were 56px with the caption pinned inside the box while
every other field on the same screen was 44px with a label above it. The
caption is now a real label and the field is 44px. The override that
tried this before set height against a base min-height and had never
once taken effect."
```

---

### Task 4: The vertical gap a Bootstrap column ate

**Files:**
- Modify: `design/vibe_shop/css/components.css:6452`

**Interfaces:**
- Consumes: nothing.
- Produces: nothing.

- [ ] **Step 1: Record the defect and prove the cause**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 \
  --eval "(()=>{const gs=[...document.querySelectorAll('.vs-option__extra .form__group')].filter(e=>e.getBoundingClientRect().height>0);return JSON.stringify(gs.map(e=>({top:Math.round(e.getBoundingClientRect().top),mb:getComputedStyle(e).marginBottom,parent:e.parentElement.className.slice(0,24),isLastOfParent:e===e.parentElement.lastElementChild})))})()" 2>&1 | sed -n '/evalResult/p'
```

Expected before the fix: the groups whose `parent` is `col-lg-6` / `col-lg-3` report `mb: "0px"` and `isLastOfParent: true`. That is the whole bug — `NovaposhtaCost` puts one `.form__group` per Bootstrap column, so each is the last child *of its column*, and the rule written to kill the trailing gap after the final field fires on all of them. On a desktop the columns sit side by side and the missing vertical gap is invisible.

- [ ] **Step 2: Narrow the rule to the case it was written for**

Replace:

```css
.vs-option__extra .form__group:last-child {
	margin-bottom: 0;
}
```

with:

```css
/* Direct children only. Delivery modules lay their fields out in Bootstrap      */
/* columns, one .form__group per column, so every one of them is the last child  */
/* of its own column. The descendant form of this rule therefore deleted the     */
/* gap between every stacked field on a phone - invisible on a desktop, where    */
/* the columns sit side by side and no vertical gap is wanted anyway.            */

.vs-option__extra > .form__group:last-child {
	margin-bottom: 0;
}
```

- [ ] **Step 3: Verify the gaps came back and the trailing one did not**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 --out /tmp/gaps-390.png --full \
  --eval "(()=>{const gs=[...document.querySelectorAll('.vs-option__extra .form__group')].filter(e=>e.getBoundingClientRect().height>0);const rows=gs.map(e=>{const r=e.getBoundingClientRect();return {top:Math.round(r.top),bottom:Math.round(r.bottom),mb:getComputedStyle(e).marginBottom}});const gaps=[];for(let i=1;i<rows.length;i++)gaps.push(rows[i].top-rows[i-1].bottom);const extra=document.querySelector('.vs-option__extra');return JSON.stringify({rows,gaps,extraPadBottom:getComputedStyle(extra).paddingBottom})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: every entry in `gaps` is 16, none is 0.

- [ ] **Step 4: Verify the desktop three-column row is unchanged**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 1440 --height 900 \
  --eval "(()=>{const gs=[...document.querySelectorAll('.vs-option__extra .form__group')].filter(e=>e.getBoundingClientRect().height>0);return JSON.stringify(gs.map(e=>({top:Math.round(e.getBoundingClientRect().top),left:Math.round(e.getBoundingClientRect().left),mb:getComputedStyle(e).marginBottom})))})()" 2>&1 | sed -n '/evalResult/p'
```

The three column fields must still share one `top` and differ only in `left`. Their `margin-bottom` becoming 16px is expected and harmless — they are side by side, so nothing moves. **If any of them changes `top`, the desktop row has broken and the fix needs a width scope.**

- [ ] **Step 5: Look at it, then commit**

Open `/tmp/gaps-390.png`, scroll to the delivery block, and confirm "Місто", "Вулиця", "Дім" and "Квартира" are evenly separated.

```bash
cd /home/sviat/projects/OkayCMS
grep -nE '^[^/]*[a-z-]+:[^;]*;[^/]*/\*' design/vibe_shop/css/components.css | head
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): restore the gap between stacked delivery fields

Delivery modules put one .form__group per Bootstrap column, so each was
the last child of its own column and a :last-child rule meant for the
final field deleted the gap between all of them. Invisible on a desktop,
where the columns are side by side; on a phone they stack."
```

---

### Task 5: The quantity stepper comes down to 44x122

**Files:**
- Modify: `design/vibe_shop/css/components.css` (`.vs-stepper__btn` near `:4435`, `.vs-stepper__input` near `:4457`)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing.

- [ ] **Step 1: Record the current size**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 \
  --eval "(()=>{const s=document.querySelector('.vs-stepper');const b=s.querySelector('.vs-stepper__btn');const i=s.querySelector('.vs-stepper__input');const m=e=>({w:Math.round(e.getBoundingClientRect().width),h:Math.round(e.getBoundingClientRect().height)});return JSON.stringify({shell:m(s),btn:m(b),input:m(i)})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected before the fix: `shell: {w:134,h:52}`, `btn: {w:44,h:50}`, `input: {w:44,h:50}`.

- [ ] **Step 2: Shrink all three children**

The shell carries a 1px border, so its outer size is the children plus 2 on each axis. 44x122 outside therefore means **42 tall and 40 wide** children, not 44 and 40.

Change `.vs-stepper__btn` from `width: 44px; min-height: 50px;` to:

```css
	width: 40px;
	min-height: 42px;
```

Change `.vs-stepper__input` from `width: 44px; min-height: 50px;` to:

```css
	width: 40px;
	min-height: 42px;
```

Add above `.vs-stepper__btn`, on their own lines:

```css
/* 40x42 children inside a 1px shell come to 44x122 - the owner asked for the    */
/* control to stop dominating the cart row and chose this over a 40x104 variant. */
/* Worth stating plainly: each button is now 40x42, under the 44px floor the     */
/* touch-target pass adopted. Three 44px children plus the border cannot be      */
/* narrower than 134, so the width the owner asked for and that floor cannot     */
/* both hold. It clears WCAG 2.5.8 AA (24x24) with room to spare.                */
```

- [ ] **Step 3: Verify the size and that the arithmetic landed**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 --out /tmp/stepper-390.png \
  --eval "(()=>{const s=document.querySelector('.vs-stepper');const b=s.querySelector('.vs-stepper__btn');const i=s.querySelector('.vs-stepper__input');const m=e=>({w:Math.round(e.getBoundingClientRect().width),h:Math.round(e.getBoundingClientRect().height)});const row=s.closest('[class*=cart]');return JSON.stringify({shell:m(s),btn:m(b),input:m(i),rowRight:row?Math.round(row.getBoundingClientRect().right):null})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `shell: {w:122,h:44}`.

- [ ] **Step 4: Verify the buttons still drive `okay.js`**

The buttons are real `<button>`s and `vibe.js` hands the arithmetic to `okay.js`'s `amount_change` rather than reimplementing the clamp. A size change must not affect that, but confirm it rather than assume.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 \
  --eval "(async()=>{const s=document.querySelector('.vs-stepper');const i=s.querySelector('.vs-stepper__input');const before=i.value;const plus=s.querySelectorAll('.vs-stepper__btn')[1];plus.click();await new Promise(r=>setTimeout(r,1500));return JSON.stringify({before,after:document.querySelector('.vs-stepper__input').value})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `after` is greater than `before`.

- [ ] **Step 5: Verify the focus ring is still visible**

The stepper clips to `overflow: hidden`, which is why its focus ring is drawn with `outline-offset: -2px`. A shorter button gives that ring less room.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost/cart?variant=283&amount=1" --width 390 --height 844 --out /tmp/stepper-focus.png \
  --eval "(async()=>{const b=document.querySelector('.vs-stepper__btn');b.focus();await new Promise(r=>setTimeout(r,200));const s=getComputedStyle(b);return JSON.stringify({outlineWidth:s.outlineWidth,outlineOffset:s.outlineOffset,h:Math.round(b.getBoundingClientRect().height)})})()" 2>&1 | sed -n '/evalResult/p'
```

**Open `/tmp/stepper-focus.png` and confirm all four sides of the ring are visible.** The computed values passing is not the check — the previous version of this ring computed correctly while three of its four sides were clipped away, which is why the negative offset exists at all.

- [ ] **Step 6: Look at it, then commit**

Open `/tmp/stepper-390.png`. The stepper and the line total must sit comfortably on one row with clear space between them.

```bash
cd /home/sviat/projects/OkayCMS
grep -nE '^[^/]*[a-z-]+:[^;]*;[^/]*/\*' design/vibe_shop/css/components.css | head
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): bring the quantity stepper down to 44x122

It was 52x134 and dominated the cart row on a phone. The children are
40x42 inside the 1px shell. Each button is now under the theme's own 44px
floor, which three 44px children plus a border make unreachable at this
width; it still clears the WCAG AA minimum comfortably."
```

---

### Task 6: Less space between the cards, more inside them

**Files:**
- Modify: `design/vibe_shop/css/components.css` (the `@media (max-width: 575px)` block — `.vs-card`'s padding near `:5602`, plus a new grid-gap rule)

**Interfaces:**
- Consumes: nothing.
- Produces: nothing.

- [ ] **Step 1: Record the current geometry and the binding constraint**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/all-products --width 390 --height 844 --out /tmp/cards-before.png --full \
  --eval "(()=>{const cs=[...document.querySelectorAll('.vs-card')].slice(0,4).map(e=>{const r=e.getBoundingClientRect();return {x:Math.round(r.left),y:Math.round(r.top),w:Math.round(r.width),h:Math.round(r.height),pad:getComputedStyle(e).padding}});const g=document.querySelector('.vs-catalogue__grid');return JSON.stringify({cards:cs,gap:getComputedStyle(g).gap,colGap:cs.length>1?cs[1].x-(cs[0].x+cs[0].w):null})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected before the fix: `gap: "16px"`, card `w: 171`, `pad: "8px"`.

- [ ] **Step 2: Measure the widest real discount row in the database**

This is the constraint that decides whether the change fits, and it must come from the data rather than from the previous spec's single sample of ~145px.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/all-products --width 390 --height 844 \
  --eval "(()=>{const rows=[...document.querySelectorAll('.vs-card__price_second')].map(e=>{const kids=[...e.children].filter(c=>c.getBoundingClientRect().width>0);const w=kids.reduce((a,c)=>a+c.getBoundingClientRect().width,0)+(kids.length>1?(kids.length-1)*parseFloat(getComputedStyle(e).columnGap||0):0);return {w:Math.round(w),text:e.textContent.replace(/\s+/g,' ').trim().slice(0,40)}}).filter(r=>r.w>0);rows.sort((a,b)=>b.w-a.w);return JSON.stringify({widest:rows.slice(0,5),count:rows.length})})()" 2>&1 | sed -n '/evalResult/p'
```

Run the same command against `/catalog/*` category pages carrying discounted stock if `/all-products` yields few discount rows. **Record the widest number found.** The card's content box after Step 3 will be 151px. If the widest real row exceeds it, **reduce the card padding rather than the grid gap** — the gap is what the owner objected to — and say in the report what you shipped and why.

- [ ] **Step 3: Tighten the grid and open up the card**

Inside the `@media (max-width: 575px)` block, change `.vs-card`'s padding from `var(--vs-space-2)` to `var(--vs-space-3)`:

```css
	.vs-card {
		padding: var(--vs-space-3);
	}
```

and add, in the same block:

```css
/* The plates had 16px between them and 8px inside them - more air outside the   */
/* card than in it, which is what the owner saw. The first responsive pass cut   */
/* the padding to 8 to buy vertical space; that trade is being reversed here     */
/* with the owner's decision, for portrait phones only. The short-viewport block */
/* at the end of this file keeps 8px, because a landscape phone is starved of    */
/* height in a way a portrait one is not, and it is later in the file so it      */
/* still wins there.                                                             */

	.vs-catalogue__grid,
	.vs-related__grid {
		gap: var(--vs-space-2);
	}
```

- [ ] **Step 4: Verify the geometry and that nothing wraps**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/all-products --width 390 --height 844 --out /tmp/cards-after.png --full \
  --eval "(()=>{const cs=[...document.querySelectorAll('.vs-card')].slice(0,4).map(e=>{const r=e.getBoundingClientRect();return {x:Math.round(r.left),y:Math.round(r.top),w:Math.round(r.width),h:Math.round(r.height),pad:getComputedStyle(e).padding}});const names=[...document.querySelectorAll('.vs-card__name')].slice(0,6).map(e=>Math.round(e.getBoundingClientRect().height));const second=[...document.querySelectorAll('.vs-card__price_second')].slice(0,6).map(e=>Math.round(e.getBoundingClientRect().height));return JSON.stringify({cards:cs,colGap:cs[1].x-(cs[0].x+cs[0].w),rowGap:cs[2].y-(cs[0].y+cs[0].h),nameHeights:names,secondTierHeights:second,docW:document.documentElement.scrollWidth})})()" 2>&1 | sed -n '/evalResult/p'
```

Expected: `colGap: 8`, `rowGap: 8`, card `w: 175`, `pad: "12px"`, `docW: 390`.

**Every entry in `nameHeights` must be identical, and every entry in `secondTierHeights` must be identical.** Those two constant heights are what the previous pass built; a discount row that wrapped to two lines in the narrower card would show up here as a taller entry, and that is the failure this task risks.

- [ ] **Step 5: Verify landscape and the tablet breakpoint did not move**

```bash
cd /home/sviat/projects/OkayCMS
for wh in "844 390" "768 1024" "1440 900"; do
  set -- $wh
  printf "%-10s " "$1x$2"
  node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url http://localhost/all-products --width $1 --height $2 \
    --eval "(()=>{const c=document.querySelector('.vs-card');const g=document.querySelector('.vs-catalogue__grid');return JSON.stringify({pad:getComputedStyle(c).padding,gap:getComputedStyle(g).gap,w:Math.round(c.getBoundingClientRect().width)})})()" 2>&1 | sed -n '/evalResult/p'
done
```

Expected: `844x390` keeps `pad: "8px"` (the short-viewport block still wins there); `768x1024` and `1440x900` keep `pad: "12px"` and `gap: "16px"`.

- [ ] **Step 6: Look at both screenshots side by side, then commit**

Open `/tmp/cards-before.png` and `/tmp/cards-after.png`. The card content must no longer sit hard against the card's own border, and the channel between the two columns must be visibly narrower than the space inside a card.

```bash
cd /home/sviat/projects/OkayCMS
grep -nE '^[^/]*[a-z-]+:[^;]*;[^/]*/\*' design/vibe_shop/css/components.css | head
grep -n 'var(--okay-' design/vibe_shop/css/components.css | grep -vE '^[0-9]+:\s*[a-z-]+: var\(--okay-[a-z0-9-]+\);\s*$' | head
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): tighten the card grid and open up the card

Portrait phones had 16px between the plates and 8px inside them. The gap
is now 8 and the padding 12. This reverses the first responsive pass's
trade of padding for vertical space, at the owner's request, and only in
portrait - the short-viewport block keeps 8px."
```

---

### Task 7: Whole-pass verification and PR update

**Files:**
- Modify: none (verification only, plus the PR body)

**Interfaces:**
- Consumes: every preceding task.
- Produces: the updated PR #5 description.

- [ ] **Step 1: Sweep every touched page at both phone shapes**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
for url in "/" "/all-products" "/products/divan-redking" "/cart?variant=283&amount=1" "/all-posts" "/brands"; do
  for wh in "390 844" "844 390" "320 568"; do
    set -- $wh
    printf "%-34s %-9s " "$url" "$1x$2"
    node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost$url" --width $1 --height $2 \
      --eval "(()=>JSON.stringify({docW:document.documentElement.scrollWidth,vw:innerWidth,overflow:document.documentElement.scrollWidth>innerWidth+1}))()" 2>&1 | sed -n '/evalResult/p'
  done
done
```

Expected: `overflow: false` on all eighteen rows.

- [ ] **Step 2: Confirm every console is clean**

The harness exits 1 on a console error. Re-run Step 1 and confirm no invocation reported one; quote any that did.

- [ ] **Step 3: Capture and look at the full set**

Take full-page screenshots of `/`, `/all-products`, `/products/divan-redking` and the cart at 390x844, and of `/all-products` and the cart at 844x390. **Open every one and describe it.** This is the step that has caught what the numbers missed three times in this project.

- [ ] **Step 4: Confirm the desktop is untouched end to end**

```bash
cd /home/sviat/projects/OkayCMS
for url in "/" "/all-products" "/cart?variant=283&amount=1"; do
  printf "%-34s " "$url"
  node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs --url "http://localhost$url" --width 1440 --height 900 --out "/tmp/desk-$(echo $url | tr '/?&=' '____').png" \
    --eval "(()=>JSON.stringify({docW:document.documentElement.scrollWidth,overflow:document.documentElement.scrollWidth>innerWidth+1}))()" 2>&1 | sed -n '/evalResult/p'
done
```

Open those three and confirm nothing about the desktop layout changed.

- [ ] **Step 5: Update the PR**

Append a section to PR #5's description covering the six items, each with its measured before and after. **No Claude or Anthropic attribution anywhere in the body.**

```bash
cd /home/sviat/projects/OkayCMS
gh pr view 5 --json body -q .body > /tmp/pr5-body.md
# edit /tmp/pr5-body.md, then:
gh pr edit 5 --body-file /tmp/pr5-body.md
```

- [ ] **Step 6: Report anything that did not land**

If any task shipped something other than what this plan specified — a padding value reduced because the discount row did not fit, a fade dropped because it swallowed vertical scrolling — say so plainly here with the number that forced it. A partial result reported honestly is worth more than a clean-sounding one.

---

## Self-Review

**Spec coverage.** Spec item 1 (gutter done once) → Task 1. Item 2 (single-line scrolling trail, bleed, scroll-to-end, dynamic fade, template untouched) → Task 2 Steps 2-3, verified in Steps 4-7. Item 3 (one field pattern, dead `height: 44px` deleted, applies to `FastOrder` too, error selector and autocomplete confirmed) → Task 3 Steps 3-4, verified 5-8. Item 4 (`:last-child` narrowed) → Task 4. Item 5 (44x122) → Task 5. Item 6 (gap 8, padding 12, portrait only, discount row re-measured) → Task 6 Steps 2-3, verified 4-5. The spec's verification section → Task 7 plus the per-task verification steps.

**Placeholder scan.** No step says "add appropriate handling" or "similar to Task N". Every code step carries the literal CSS or JS; every verification step carries the command and the value it must produce. Three steps end in judgement — Task 3 Step 4 (whether `.form__input_captcha` is left unstyled), Task 6 Step 2 (whether the widest real discount row fits), Task 2 Step 6 (whether the strip blocks a vertical pan) — and each names the threshold and instructs the implementer to report rather than decide silently.

**Type consistency.** The two class names the JS writes and the CSS reads, `is-overflow-start` and `is-overflow-end`, appear in Task 2 Step 2 and Step 3 and are checked by name in Step 4. `.vs-card__price_second` is the class Task 1 of the previous pass created and is measured by that exact name in Task 6 Steps 2 and 4. The stepper arithmetic is stated once and used consistently: children 40x42 inside a 1px shell give 122x44, against 44x50 giving 134x52 today.

**One thing worth flagging to the reviewer.** Task 5 ships buttons of 40x42, below the 44px floor this theme adopted in its touch-target pass. That is not an oversight and it is not avoidable: three 44px children plus the shell's border cannot be narrower than 134px, so the width the owner asked for and the 44px floor are mutually exclusive. The owner was shown the 40px button width in the option they chose. It is recorded in a comment in the stylesheet as well as here.
