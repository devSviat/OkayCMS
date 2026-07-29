# vibe_shop Product Card and Translucent Chrome — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make neighbouring catalogue cards line up row for row, take the SKU off the card, and give the header and sticky buy bar a translucent blur that never costs contrast or frames.

**Architecture:** The card's price block gains a second tier that is always rendered, so its height no longer depends on whether a discount exists. The discount badge moves onto that tier, beside the number it modifies. The SKU is commented out following the file's existing convention. The translucent chrome is a single `@supports` block deriving its colour from the shop owner's admin-set surface via `color-mix`.

**Tech Stack:** Smarty 4 templates; CSS in `design/vibe_shop/css/components.css`; `okay.js` owns the `hidden-xs-up` toggles and is not modified; verification via `puppeteer-core` driven directly.

**Spec:** `docs/superpowers/specs/2026-07-29-vibe-shop-product-card-design.md`
**Branch:** `feature/vibe-shop-responsive` (PR #5, open — this work updates it)

## Global Constraints

- **Theme only.** Every change lands in `design/vibe_shop/`. Do not edit core, `design/okay_shop/`, or any module.
- **`okay.js` and `vibe.js` are not modified.** They own the `hidden-xs-up` toggles and the `fn_*` hooks; the markup adapts to them, never the reverse.
- **`fn_*` classes and `data-*` attributes are a JavaScript contract.** `fn_price`, `fn_old_price`, `fn_discount_label`, `fn_sku`, `fn_variants`, `fn_variant`, `fn_is_stock`, `fn_not_preorder`, `fn_product`, `fn_transfer` must all survive with their current names.
- **Three silent CSS compiler traps** in `Okay\Core\TemplateConfig\CssConfig`, a line-by-line text pass that errors on none: (a) a comment sharing a line with a declaration deletes that declaration — comments own their whole line; (b) `var(--okay-*)` substitutes only as `property: var(--okay-x);`, one call per line, no fallback; (c) a selector may break across lines only immediately after a comma.
- **`--vs-surface` is `var(--okay-boxed-color)`** — the shop owner's admin-set colour, substituted into the bundle at compile time. Never hard-code a colour where the theme reads that token.
- **The `TOUCH TARGETS` block stays last in `components.css`.**
- **Touch targets ≥44 px.**
- **WCAG 2.1 AA: body text ≥4.5:1**, verified with a checker, not estimated.
- **Comment language: Ukrainian or English only, never Russian.** This theme's comments are English.
- **Clear both caches after every edit** — templates change here: `rm -f compiled/vibe_shop/*.php cache/css/*`
- **Commit messages carry no `Co-Authored-By` and no Claude/Anthropic attribution.**
- **`.superpowers/sdd/` is git-ignored scratch.** Stage only `design/vibe_shop/**`.
- **Evidence before claims.** Every task states a measured number before and after.

## File Structure

| File | Responsibility |
| --- | --- |
| `design/vibe_shop/html/product_list.tpl` | Modify. The price block gains an always-rendered second tier holding the old price and the discount badge; the SKU block becomes a commented restore-note. |
| `design/vibe_shop/css/components.css` | Modify. The second tier's reserved height; the discount badge's new context; the translucent `@supports` block. |

---

### Task 1: A price block whose height does not depend on a discount

The 28 px that makes neighbouring cards look ragged.

**Files:**
- Modify: `design/vibe_shop/html/product_list.tpl` (the `.vs-card__price` div and the `.vs-card__badges` div)
- Modify: `design/vibe_shop/css/components.css:2630-2668`

**Interfaces:**
- Produces: a new class `.vs-card__price_second`, the always-rendered second tier. Task 2 does not touch it; Task 4 measures it.

- [ ] **Step 1: Record the misalignment**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/card-t1-before.png \
  --eval "(()=>{const cs=[...document.querySelectorAll('.vs-card')].slice(0,2);const rel=(c,s)=>{const e=c.querySelector(s);if(!e)return null;const r=e.getBoundingClientRect(),cr=c.getBoundingClientRect();return {y:Math.round(r.top-cr.top),h:Math.round(r.height)}};return JSON.stringify(cs.map(c=>({cardH:Math.round(c.getBoundingClientRect().height),name:rel(c,'.vs-card__name'),price:rel(c,'.vs-card__price'),stock:rel(c,'.vs-stock'),actions:rel(c,'.vs-card__actions')})))})()"
```

Expected: two cards of equal height whose `price.h` differs — 26 on the card without an old price, 54 on the one with it — and whose `stock.y` differs by 28.

- [ ] **Step 2: Restructure the price block in the template**

In `design/vibe_shop/html/product_list.tpl`, replace the `.vs-card__price` div:

```smarty
            <div class="vs-card__price">
                <span class="vs-card__price_current{if $product->variant->compare_price} price--red{/if}"><span class="fn_price">{$product->variant->price|convert}</span>&nbsp;<span class="vs-card__currency">{$currency->sign|escape}</span></span>

                {* The second tier is rendered whether or not this product has a
                   discount, and that is the point: okay.js hides its children by
                   toggling hidden-xs-up, but the tier itself keeps its line, so the
                   block is the same height on every card and the availability line
                   below lands at the same y in neighbouring cards. Before this, a
                   card with an old price was 28px taller through the middle. *}
                <span class="vs-card__price_second">
                    <span class="vs-card__price_old{if !$product->variant->compare_price} hidden-xs-up{/if}"><span class="fn_old_price">{$product->variant->compare_price|convert}</span>&nbsp;<span class="vs-card__currency">{$currency->sign|escape}</span></span>
                    <span class="fn_discount_label vs-badge vs-badge--sale{if !($product->variant->price>0 && $product->variant->compare_price>0 && $product->variant->compare_price>$product->variant->price)} hidden-xs-up{/if}">{if $product->variant->price>0 && $product->variant->compare_price>0 && $product->variant->compare_price>$product->variant->price}{round((($product->variant->price-$product->variant->compare_price)/$product->variant->compare_price)*100)}&nbsp;%{/if}</span>
                </span>
            </div>
```

The `fn_discount_label` span is **moved here verbatim** from `.vs-card__badges` — same classes, same content, same `hidden-xs-up` condition. Delete it from the badges div, leaving the `featured` and `special` badges there.

Two things this must not break, both verified against `okay.js`:

- `okay.js:75-80` toggles `hidden-xs-up` on `cprice.parent()`, where `cprice` is `.fn_old_price`. Its parent is still `.vs-card__price_old`, unchanged by this nesting.
- `okay.js:81-95` reaches the badge with `parent.find(".fn_discount_label")`, a descendant search from `.fn_product`, so its new depth is irrelevant.

- [ ] **Step 3: Reserve the tier's height in CSS**

In `components.css`, after the `.vs-card__price` rule at `:2630`, add:

```css
/* The reserved second tier. Its min-height is one line of the old price's own   */
/* size, so the number comes from the type scale rather than from a measurement  */
/* that would rot the first time a token moves.                                  */

.vs-card__price_second {
	display: flex;
	align-items: baseline;
	gap: var(--vs-space-2);
	min-height: calc(var(--vs-text-sm) * var(--vs-leading-body));
}
```

`--vs-text-sm` is `0.8125rem` and `--vs-leading-body` is `1.55` (`tokens.css:126,133`), giving 1.259rem — about 20 px, which is exactly what the old price measures today.

Then change `.vs-card__price` itself from a wrapping row to a column, since the tiers are now explicit:

```css
.vs-card__price {
	display: flex;
	align-items: baseline;
	flex-direction: column;
	gap: var(--vs-space-1);
	margin-top: var(--vs-space-3);
	font-variant-numeric: tabular-nums;
}
```

`flex-wrap: wrap` goes — nothing wraps any more — and the gap drops from `--vs-space-2` to `--vs-space-1`, because a 4 px step between two tiers of one block reads as one unit where 8 px read as two.

- [ ] **Step 4: Give the discount badge its inline form**

The badge was a corner pill over a photograph; on the price tier it sits beside text. Add:

```css
/* On the price tier rather than over the photograph, so the percentage sits    */
/* next to the number it modifies. Smaller and tighter than the corner badges,  */
/* which have to read against arbitrary shop photography.                       */

.vs-card__price_second .vs-badge--sale {
	min-height: 0;
	padding: 0 var(--vs-space-1-5);
	font-size: var(--vs-text-xs);
}
```

- [ ] **Step 5: Clear caches and verify the alignment**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
```

Re-run Step 1's command with `--out /tmp/card-t1-after.png`.

Expected: both cards report the **same** `price.h`, and `stock.y` matches between them. That equality is this task's deliverable — if the two `stock.y` values still differ, the task has not succeeded regardless of what else improved.

- [ ] **Step 6: Confirm the badge still toggles**

The discount badge must still appear and disappear as `okay.js` reacts to a variant change. Check it through the script rather than by reading CSS:

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 \
  --eval "(()=>{const c=[...document.querySelectorAll('.vs-card')].find(x=>x.querySelector('.fn_discount_label:not(.hidden-xs-up)'));if(!c)return JSON.stringify({note:'no discounted card in view'});const b=c.querySelector('.fn_discount_label');const o=c.querySelector('.vs-card__price_old');return JSON.stringify({badgeText:b.textContent.trim(),badgeParent:b.parentElement.className,oldParent:o.parentElement.className,oldHidden:o.classList.contains('hidden-xs-up')})})()"
```

Expected: `badgeParent` and `oldParent` are both `vs-card__price_second`, and the badge carries its percentage.

- [ ] **Step 7: Compiler traps**

```bash
cd /home/sviat/projects/OkayCMS
git diff -U0 -- design/vibe_shop/css/components.css | grep '^+' | grep '/\*' | grep -v '^+[[:space:]]*/\*' || echo "trap 1 clear"
git diff -U0 -- design/vibe_shop/css/components.css | grep '^+' | grep 'var(--okay-' || echo "trap 2 clear"
git diff -U0 -- design/vibe_shop/css/components.css | grep '^+' | grep -E '^\+\s*\.[a-z0-9_-]+\s*$' || echo "trap 3 clear"
```

- [ ] **Step 8: Look at it**

Open `/tmp/card-t1-after.png`. The availability lines in a row must sit level, the discount must read as part of the price rather than as a sticker, and the corner must be one badge lighter.

- [ ] **Step 9: Commit**

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/html/product_list.tpl design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): give the card price a constant height

The block was 26px without an old price and 54px with one, so the
availability line sat 28px apart in neighbouring cards. The second tier
is now always rendered, and the discount moves onto it beside the number
it modifies."
```

---

### Task 2: The SKU leaves the card, commented rather than deleted

**Files:**
- Modify: `design/vibe_shop/html/product_list.tpl` (the `.vs-card__sku` div)

**Interfaces:**
- Consumes: nothing from Task 1.

- [ ] **Step 1: Record the height it costs**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 \
  --eval "(()=>{const c=document.querySelector('.vs-card');const s=c.querySelector('.vs-card__sku');const r=s.getBoundingClientRect();const cs=getComputedStyle(s);return JSON.stringify({cardH:Math.round(c.getBoundingClientRect().height),skuH:Math.round(r.height),skuMarginTop:cs.marginTop,text:s.textContent.replace(/\\s+/g,' ').trim()})})()"
```

Expected: `skuH: 19`, `skuMarginTop: "4px"` — 23 px per card.

- [ ] **Step 2: Comment it out with a restore note**

In `design/vibe_shop/html/product_list.tpl`, replace the SKU div with a Smarty comment. The file already uses this shape for the comparison control, so follow it:

```smarty
            {* The article number is off the card by the shop owner's decision: in a
               grid it costs 23px on every card for a value most shoppers do not scan.
               Delete this comment wrapper to bring it back - nothing else has to
               change. fn_sku is the hook okay.js writes into when a variant changes
               (okay.js:59), and it reaches it with .find(), so its absence is a
               no-op rather than an error; vibe.js:470 guards with `if (sku)` before
               adding it to the live announcement. The product page states the SKU in
               full either way. *}
            {*
            <div class="vs-card__sku{if !$product->variant->sku} hidden-xs-up{/if}">
                <span data-language="product_sku">{$lang->product_sku}:</span>
                <span class="fn_sku">{$product->variant->sku|escape}</span>
            </div>
            *}
```

Leave `.vs-card__sku` in `components.css:2580`. It is three declarations, it is what the block needs when restored, and deleting it would make the restore note a lie.

- [ ] **Step 3: Clear caches and verify**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/card-t2-after.png \
  --eval "(()=>{const c=document.querySelector('.vs-card');return JSON.stringify({cardH:Math.round(c.getBoundingClientRect().height),skuPresent:!!c.querySelector('.vs-card__sku'),skuHookPresent:!!c.querySelector('.fn_sku')})})()"
```

Expected: `skuPresent: false`, `skuHookPresent: false`, and `cardH` reduced by about 23 from Step 1.

- [ ] **Step 4: Confirm no script throws**

The harness reports console errors and exits non-zero on them. Load a catalogue page and a page where a variant changes, and confirm the console is clean:

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/card-t2-console.png
echo "exit: $?"
```

Expected: `consoleErrorCount: 0`, exit 0. `okay.js` runs its variant handler on load, so a broken `.fn_sku` reference would surface here.

- [ ] **Step 5: Commit**

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/html/product_list.tpl
git commit -m "fix(vibe_shop): take the article number off the catalogue card

23px on every card for a value most shoppers do not scan in a grid. Left
commented with a restore note, the way the comparison control already is,
because a parts shop may want it back."
```

---

### Task 3: Translucent header and buy bar

**Files:**
- Modify: `design/vibe_shop/css/components.css`

**Interfaces:**
- Consumes: nothing from Tasks 1 and 2.

- [ ] **Step 1: Record the opaque baseline**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 \
  --eval "(()=>{const h=document.querySelector('.vs-header__main');const cs=getComputedStyle(h);return JSON.stringify({bg:cs.backgroundColor,backdrop:cs.backdropFilter,webkit:cs.webkitBackdropFilter,supportsBlur:CSS.supports('backdrop-filter','blur(12px)')||CSS.supports('-webkit-backdrop-filter','blur(12px)'),supportsMix:CSS.supports('color','color-mix(in srgb, white 80%, transparent)')})})()"
```

Expected: an opaque `bg`, `backdrop: "none"`, and both `supports*` true in this Chrome build. If `supportsMix` is false, stop and report — the whole approach depends on it.

- [ ] **Step 2: Add the block**

In `components.css`, near the `.vs-header__main` rule, add:

```css
/* Translucent chrome. The blur is the visible part but the translucency is the  */
/* actual change: backdrop-filter does nothing behind an opaque background, and  */
/* both of these were fully opaque.                                              */
/*                                                                              */
/* The colour cannot be a literal rgba. --vs-surface is var(--okay-boxed-color), */
/* the shade the shop owner sets in the admin panel, so the translucent value is */
/* mixed from whatever they chose. By the time the browser sees it the token     */
/* holds a plain colour, so color-mix resolves normally.                         */
/*                                                                              */
/* Both properties live inside @supports together on purpose. A browser without  */
/* backdrop-filter must keep the opaque surface: giving it the translucency      */
/* alone would be a see-through bar with no blur, worse contrast than today.     */

@supports (backdrop-filter: blur(12px)) or (-webkit-backdrop-filter: blur(12px)) {
	.vs-header__main,
	.vs-sticky-buy {
		background-color: color-mix(in srgb, var(--vs-surface) 82%, transparent);
		-webkit-backdrop-filter: blur(12px);
		backdrop-filter: blur(12px);
	}
}
```

Note the two-line selector breaks immediately after the comma, which is the only break the compiler allows.

- [ ] **Step 3: Clear caches and confirm it applies**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/chrome-after.png \
  --eval "(()=>{const h=document.querySelector('.vs-header__main');const cs=getComputedStyle(h);return JSON.stringify({bg:cs.backgroundColor,backdrop:cs.backdropFilter||cs.webkitBackdropFilter})})()"
```

Expected: `bg` now carries an alpha below 1, and `backdrop` reports a blur.

- [ ] **Step 4: Measure contrast over a dark photograph — this decides the shipped number**

Scroll the catalogue so a dark product image sits under the sticky header, then sample the actual rendered pixels behind the header text and compute the ratio against the text colour. Do not estimate it from the alpha value.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/chrome-contrast.png \
  --eval "(async()=>{scrollTo(0,600);await new Promise(r=>setTimeout(r,700));const h=document.querySelector('.vs-header__main');const t=h.querySelector('a,button,span');return JSON.stringify({headerRect:h.getBoundingClientRect().toJSON(),textColor:getComputedStyle(t).color,note:'sample the screenshot pixels under this rect'})})()"
```

Then read `/tmp/chrome-contrast.png`, sample pixels inside the header band where text sits, and compute the WCAG contrast ratio against the reported text colour. **If any sampled ratio for body-sized text is below 4.5:1, raise the opacity above 82 % and repeat until it clears.** Record the final percentage and the worst ratio you measured.

- [ ] **Step 5: Check it does not cost frames**

`backdrop-filter` on an element that repaints every scroll frame is expensive on a phone.

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 \
  --eval "(async()=>{const t0=performance.now();let frames=0;let last=t0;const step=()=>{frames++;last=performance.now();if(last-t0<2000)requestAnimationFrame(step)};requestAnimationFrame(step);const iv=setInterval(()=>scrollBy(0,40),16);await new Promise(r=>setTimeout(r,2100));clearInterval(iv);return JSON.stringify({seconds:((last-t0)/1000).toFixed(2),frames,fps:Math.round(frames/((last-t0)/1000))})})()"
```

Record the fps. **If it is materially below 60, report it and recommend dropping the effect** rather than shipping it slow — a smooth opaque header beats a janky translucent one on the device this project exists to serve. Do not decide unilaterally; report the number.

- [ ] **Step 6: Compiler traps, then look at it**

Run the three greps from Task 1 Step 7. Then open `/tmp/chrome-after.png` and `/tmp/chrome-contrast.png` and describe what you see at both 390×844 and 844×390 — whether the blur reads as intentional or as a wash, and whether the header text stays crisp over busy photography.

- [ ] **Step 7: Commit**

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/css/components.css
git commit -m "feat(vibe_shop): make the header and buy bar translucent

The translucency is mixed from the owner's admin-set surface colour, and
ships only inside @supports alongside the blur - without it a browser
would get a see-through bar and worse contrast than an opaque one."
```

---

### Task 4: Whole-card verification and close-out

Nothing new is built. Each earlier task verified its own change; this is the first look at them together.

**Files:**
- No code changes. If you believe one is needed, report it rather than making it.

- [ ] **Step 1: Alignment across a full row, both viewports**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/card-final-390.png \
  --eval "(()=>{const cs=[...document.querySelectorAll('.vs-card')].slice(0,6);const rel=(c,s)=>{const e=c.querySelector(s);if(!e)return null;const r=e.getBoundingClientRect(),cr=c.getBoundingClientRect();return Math.round(r.top-cr.top)};return JSON.stringify(cs.map(c=>({h:Math.round(c.getBoundingClientRect().height),name:rel(c,'.vs-card__name'),price:rel(c,'.vs-card__price'),second:rel(c,'.vs-card__price_second'),stock:rel(c,'.vs-stock'),actions:rel(c,'.vs-card__actions'),discounted:!!c.querySelector('.fn_discount_label:not(.hidden-xs-up)')})))})()"
```

Repeat at 844×390. Expected: within each row, every offset matches across cards regardless of `discounted`. Report the full table.

- [ ] **Step 2: Card height before and after the whole plan**

Compare against the pre-plan baseline of 412 px at 390×844, and state the new figure for a discounted and a non-discounted card.

- [ ] **Step 3: Confirm the desktop catalogue is unaffected**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 1440 --height 900 --out /tmp/card-final-desktop.png \
  --eval "(()=>{const c=document.querySelector('.vs-card');const h=document.querySelector('.vs-header__main');return JSON.stringify({cardH:Math.round(c.getBoundingClientRect().height),cols:getComputedStyle(document.querySelector('.vs-catalogue__grid')).gridTemplateColumns.split(' ').length,headerBg:getComputedStyle(h).backgroundColor})})()"
```

The card changes are width-independent by design, so desktop cards change height too — that is expected. What must not change is the column count. Report both.

- [ ] **Step 4: Look at every screenshot**

390×844, 844×390 and 1440×900. Describe what you see. Two tasks in the earlier passes on this theme met every numeric criterion while the page was visibly broken, and only looking caught them.

- [ ] **Step 5: Branch-wide trap sweep and tests**

```bash
cd /home/sviat/projects/OkayCMS
git diff master...HEAD -- design/vibe_shop/css/components.css | grep '^+' > /tmp/added.txt
grep '/\*' /tmp/added.txt | grep -v '^+[[:space:]]*/\*' || echo "trap 1 clear"
grep 'var(--okay-' /tmp/added.txt || echo "trap 2 clear"
grep -E '^\+\s*\.[a-z0-9_-]+\s*$' /tmp/added.txt || echo "trap 3 clear"
cd dev && docker compose exec -T php85 php vendor/bin/phpunit 2>&1 | tail -5
```

Expected: three clear lines and `OK (505 tests)`.

- [ ] **Step 6: Close out**

Append a close-out block to the ledger recording the measured alignment table, the final card heights, the contrast percentage and worst ratio from Task 3, the scroll fps, and any deferred minors. Then update PR #5's description with a section covering this pass, keeping the existing Ukrainian structure and adding no Claude or Anthropic attribution.

---

### Task 5: Restore the card title's two-line clamp

**Execute this before Task 4** — Task 4 is the verification pass and must see the finished state.
This task was added after Task 1 revealed that the price block was the smaller half of the
alignment problem.

**Files:**
- Modify: `design/vibe_shop/css/components.css:10395-10404`

**Interfaces:**
- Consumes: nothing. Independent of Tasks 1-3.

- [ ] **Step 1: Record the variance**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/clamp-before.png \
  --eval "(()=>{const ns=[...document.querySelectorAll('.vs-card__name')].slice(0,6);const cs=getComputedStyle(ns[0]);return JSON.stringify({display:cs.display,clamp:cs.webkitLineClamp,heights:ns.map(n=>Math.round(n.getBoundingClientRect().height))})})()"
```

Expected: `display: "flex"`, `clamp: "2"` — the clamp declared but inert — and heights varying
across roughly 44, 44, 81, 60, 101, 60.

- [ ] **Step 2: Drop the two declarations that kill the clamp**

At `components.css:10399-10404` the `TOUCH TARGETS` block carries:

```css
	.vs-card__name,
	.vs-post-card__link {
		display: flex;
		align-items: flex-start;
		min-height: 44px;
	}
```

Remove `display: flex;` and `align-items: flex-start;`, keeping `min-height: 44px`:

```css
	.vs-card__name,
	.vs-post-card__link {
		min-height: 44px;
	}
```

`display: -webkit-box` from the base rule at `:2561` then applies again and the clamp works.
`min-height` alone still holds the 44 px touch floor — `-webkit-box` honours it.

Replace the comment above the rule, which currently explains the flex-column reasoning that no
longer applies:

```css
/* Card titles. Two lines of 20px leading come to 40.3px, four short of the     */
/* floor, so min-height carries the difference. It must NOT become a flex box   */
/* to get there: -webkit-line-clamp only works on display: -webkit-box, which   */
/* the base rule sets, and display: flex here silently disabled the clamp -     */
/* titles ran to five lines and card heights varied by 57px. The .vs-cart__name */
/* rule below states the same constraint and got it right.                      */
```

- [ ] **Step 3: Clear caches and verify the clamp is live**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
```

Re-run Step 1's command with `--out /tmp/clamp-after.png`.

Expected: `display: "-webkit-box"` and every height 44. A height above 44 means the clamp is
still not applying.

- [ ] **Step 4: Confirm the touch target survives**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 \
  --eval "(()=>{const ns=[...document.querySelectorAll('.vs-card__name')].slice(0,6);return JSON.stringify(ns.map(n=>({h:Math.round(n.getBoundingClientRect().height),minH:getComputedStyle(n).minHeight})))})()"
```

Expected: every `h` at least 44 and every `minH` `"44px"`.

- [ ] **Step 5: Check the blog card, which shares the rule**

`.vs-post-card__link` is in the same selector and loses the same two declarations. Its own base
rule may or may not set `-webkit-box`; check rather than assume.

```bash
cd /home/sviat/projects/OkayCMS
grep -n 'vs-post-card__link' design/vibe_shop/css/components.css
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-posts --width 390 --height 844 --out /tmp/clamp-blog.png \
  --eval "(()=>{const ls=[...document.querySelectorAll('.vs-post-card__link')];return JSON.stringify(ls.map(l=>({h:Math.round(l.getBoundingClientRect().height),display:getComputedStyle(l).display,clamp:getComputedStyle(l).webkitLineClamp})))})()"
```

Report what you find. If the blog title has no clamp of its own, removing `display: flex` simply
returns it to normal flow — say so and show the measured heights before and after. If it looks
worse, stop and report rather than inventing a clamp for it.

- [ ] **Step 6: Alignment across six cards**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 390 --height 844 --out /tmp/clamp-rows.png \
  --eval "(()=>{const cs=[...document.querySelectorAll('.vs-card')].slice(0,6);const rel=(c,s)=>{const e=c.querySelector(s);if(!e)return null;const r=e.getBoundingClientRect(),cr=c.getBoundingClientRect();return Math.round(r.top-cr.top)};return JSON.stringify(cs.map(c=>({h:Math.round(c.getBoundingClientRect().height),price:rel(c,'.vs-card__price'),stock:rel(c,'.vs-stock'),actions:rel(c,'.vs-card__actions')})))})()"
```

Expected: all six cards report the same `price`, `stock` and `actions` offsets, and the same
height. This is the task's deliverable — with Task 1 already in, nothing above the price should
vary any more.

- [ ] **Step 7: Compiler traps**

```bash
cd /home/sviat/projects/OkayCMS
git diff -U0 -- design/vibe_shop/css/components.css | grep '^+' | grep '/\*' | grep -v '^+[[:space:]]*/\*' || echo "trap 1 clear"
git diff -U0 -- design/vibe_shop/css/components.css | grep '^+' | grep 'var(--okay-' || echo "trap 2 clear"
git diff -U0 -- design/vibe_shop/css/components.css | grep '^+' | grep -E '^\+\s*\.[a-z0-9_-]+\s*$' || echo "trap 3 clear"
```

Note the two-line selector `.vs-card__name,` / `.vs-post-card__link` breaks immediately after a
comma, which is the only break the compiler allows — it is unchanged, but trap 3's grep will see
it in context.

- [ ] **Step 8: Look at it**

Open `/tmp/clamp-after.png` and `/tmp/clamp-rows.png`. Every card in a row must be the same
height with every element level. Confirm no title is cut mid-word in a way that loses meaning —
a two-line clamp on a long product name is a deliberate trade, but it should still read.

- [ ] **Step 9: Commit**

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): restore the card title's two-line clamp

The touch-target pass set display: flex on .vs-card__name, which
silently disabled -webkit-line-clamp - titles ran to five lines and card
heights varied by 57px. min-height alone holds the 44px floor, which is
what the .vs-cart__name rule below already says."
```

---

## Self-Review

**Spec coverage.** Spec item 1 (reserve the old-price tier, not inline) → Task 1 Steps 2-3. Item 2 (discount moves onto the tier) → Task 1 Steps 2 and 4. Item 3 (SKU commented, not deleted) → Task 2. Item 4 (translucent chrome, `color-mix` from `--vs-surface`, `@supports` gating both properties, measured contrast, measured performance) → Task 3 Steps 2-5. The spec's verification section → Task 1 Steps 5-6, Task 2 Steps 3-4, Task 3 Steps 4-5, and Task 4 throughout.

**Placeholder scan.** No step says "add appropriate handling" or "similar to Task N". Every code step carries the actual markup or CSS; every verification step carries the command and the value it must produce. Task 3 Steps 4 and 5 end in judgement — the shipped opacity and whether the effect ships at all — and both are bounded by an explicit threshold (4.5:1, and materially below 60 fps) plus an instruction to report rather than decide alone.

**Type consistency.** The one new class, `.vs-card__price_second`, is introduced in Task 1 Step 2, styled in Step 3, used as a selector prefix in Step 4, and measured by name in Task 4 Step 1. The `fn_*` hooks named in the constraints — `fn_price`, `fn_old_price`, `fn_discount_label`, `fn_sku` — are the exact strings in the template and in the `okay.js` line references (`:59`, `:75-80`, `:81-95`).

**One thing worth flagging to the reviewer.** Task 1 changes `.vs-card__price` from `flex-wrap: wrap` to `flex-direction: column`. Any product whose current price is long enough to have relied on wrapping will now overflow its line instead. The catalogue's widest price at 390 px measures well inside the 162 px body, but a shop with longer numbers or a multi-character currency could differ — Task 4 Step 1 samples six cards rather than two partly to catch it.
