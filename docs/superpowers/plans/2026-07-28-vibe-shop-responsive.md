# vibe_shop Phone and Tablet Pass — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give `design/vibe_shop` a viewport-height-aware layer so a phone held sideways stops being laid out as a small tablet, and fix the carousel breakpoint bug that crushes product cards from 768 px to 1199 px.

**Architecture:** Width breakpoints (576 / 768 / 992 / 1200) are untouched — `grid.css` hangs its utilities off them. One new layer is appended to `components.css` as two blocks, `@media (max-height: 500px)` and `@media (max-height: 500px) and (min-width: 640px)`, placed after every width block and immediately before the file's final `TOUCH TARGETS` section. Source order is load-bearing: these rules and the `min-width` rules they correct carry equal specificity, so position is what decides them. One JavaScript change fixes a duplicate object key in the Swiper config.

**Tech Stack:** CSS in `design/vibe_shop/css/components.css`; Swiper 5 config in `design/vibe_shop/js/okay.js`; verification via `puppeteer-core` driven directly (the chrome-devtools MCP cannot launch Chrome on this machine).

**Spec:** `docs/superpowers/specs/2026-07-28-vibe-shop-responsive-design.md`
**Branch:** `feature/vibe-shop-responsive` (already checked out; the spec's three commits are on it)

## Global Constraints

- **Theme only.** Every change lands in `design/vibe_shop/`. Do not edit core, `design/okay_shop/`, or any module.
- **Width breakpoints 576 / 768 / 992 / 1200 must not move.**
- **New `@media` blocks go immediately before the `/* TOUCH TARGETS */` banner** (currently `components.css:10016`) and nothing goes after that banner's block. It is last on purpose — it wins on source order at equal weight.
- **CSS compiler traps.** `Okay\Core\TemplateConfig\CssConfig` preprocesses line by line and corrupts three shapes with no error anywhere: a comment sharing a line with a declaration deletes the declaration; `var(--okay-*)` substitutes only as `property: var(--okay-x);` (one call per line, no fallback); a selector may break across lines only immediately after a comma. Every task greps its own diff for all three.
- **Comment language: Ukrainian or English only. Never Russian** — including where a neighbouring core file is Russian. This theme's comments are English; match them.
- **Clear caches after every CSS edit**, or you verify the previous bundle: `rm -f compiled/vibe_shop/*.php cache/css/*`
- **Evidence before claims.** Every task states a measured number before the change and a measured number after. No task is complete on the strength of reading the CSS.
- **Commit messages carry no `Co-Authored-By` and no Claude/Anthropic attribution.**
- **`.superpowers/sdd/` is git-ignored scratch** (`.superpowers/sdd/.gitignore` holds `*`). The harness, the audit JSONs, the screenshots and the ledger live there and are never committed. Only `design/vibe_shop/**` is staged by this plan. Never `git add -f` the workspace.
- **No git worktree.** Nginx serves `/home/sviat/projects/OkayCMS` directly, so a worktree would be invisible to the browser and every measurement in this plan would describe the wrong tree. Work in place on `feature/vibe-shop-responsive`.

## File Structure

| File | Responsibility |
| --- | --- |
| `.superpowers/sdd/2026-07-28-vibe-shop-responsive/audit.mjs` | Create. The measurement harness: one browser session, fills the cart, walks 4 pages × 5 viewports, writes a JSON report and optional screenshots. |
| `.superpowers/sdd/2026-07-28-vibe-shop-responsive/audit-baseline.json` | Create. The before-state, captured once in Task 1 and never regenerated. |
| `.superpowers/sdd/2026-07-28-vibe-shop-responsive/progress.md` | Create. The ledger: one block per task with the measured numbers. |
| `design/vibe_shop/js/okay.js` | Modify `:538-557`. The `.fn_products_slide` Swiper breakpoint ladder. |
| `design/vibe_shop/css/components.css` | Modify. New `SHORT VIEWPORT` section before `:10016`; one declaration added to the existing `≤575` block; the existing `TOUCH TARGETS` block extended in Task 7. |

---

### Task 1: Measurement harness and baseline

Nothing can be verified without numbers, and every later task's red-green cycle is a run of this script. It also has to fill the cart: `shot.mjs` opens a fresh browser per run, so it can never show a cart with more than the one line its own URL added.

**Files:**
- Create: `.superpowers/sdd/2026-07-28-vibe-shop-responsive/audit.mjs`
- Create: `.superpowers/sdd/2026-07-28-vibe-shop-responsive/audit-baseline.json`
- Create: `.superpowers/sdd/2026-07-28-vibe-shop-responsive/progress.md`

**Interfaces:**
- Produces: `node .superpowers/sdd/2026-07-28-vibe-shop-responsive/audit.mjs <outDir> <label> [--shots]`, writing `<outDir>/audit-<label>.json`. Report shape: `{label, consoleErrors: [], results: {"<page>/<viewport>": {vw, vh, scrollWidth, overflowX, docHeight, header, stickyBuy, hero, carouselPerView, catalogueCols, pdpCols, galleryFrameW, tiny: {}, tinyTotal}}}`. Page keys: `home`, `catalogue`, `product`, `cart`. There is no separate checkout page: `CartController` renders the whole checkout form (`.vs-checkout`) inline on `/cart`, and `/order` is the post-purchase confirmation keyed by order hash, which 404s without one. Viewport keys: `phone-portrait`, `phone-landscape`, `tablet-portrait`, `tablet-landscape`, `desktop`. Every later task consumes these exact key names.

- [ ] **Step 1: Confirm the dev environment answers**

```bash
cd /home/sviat/projects/OkayCMS/dev && docker compose ps --format '{{.Service}} {{.State}}'
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/
```

Expected: `mariadb running`, `nginx running`, `php85 running`, and `200`. If nginx is down, `docker compose up -d` first. Note the site answers on `http://localhost/` — `okaycms.loc` from `dev/.env` has no hosts entry on this machine and will fail with `ERR_NAME_NOT_RESOLVED`.

- [ ] **Step 2: Write the harness**

Create `.superpowers/sdd/2026-07-28-vibe-shop-responsive/audit.mjs`:

```js
/**
 * Responsive audit harness for the vibe_shop phone and tablet pass.
 *
 * One browser session: fills the cart once, then walks every page at every
 * viewport and records geometry. shot.mjs cannot do this - it opens a fresh
 * browser per run, so its cart is always empty or one line long.
 *
 * Usage:
 *   node audit.mjs <outDir> <label> [--shots]
 *
 * Writes <outDir>/audit-<label>.json, prints a compact table, and exits 1 if
 * any page logged a console error or scrolled horizontally.
 */

// Absolute path: ESM ignores NODE_PATH, and puppeteer-core lives in the
// chrome-devtools-mcp plugin's node_modules rather than in this repo. The
// entry is lib/puppeteer/..., NOT lib/esm/puppeteer/... - that path does not
// exist in this build and is the usual first wrong guess.
import puppeteer from '/home/sviat/.claude/plugins/cache/claude-plugins-official/chrome-devtools-mcp/1.6.0/node_modules/puppeteer-core/lib/puppeteer/puppeteer-core.js';

const CHROME = process.env.CHROME_PATH
    || '/home/sviat/.cache/puppeteer/chrome/linux-150.0.7871.24/chrome-linux64/chrome';

const OUT = process.argv[2];
const LABEL = process.argv[3];
const SHOTS = process.argv.includes('--shots');

if (!OUT || !LABEL) {
    console.error('usage: node audit.mjs <outDir> <label> [--shots]');
    process.exit(2);
}

const VIEWPORTS = [
    ['phone-portrait', 390, 844],
    ['phone-landscape', 844, 390],
    ['tablet-portrait', 820, 1180],
    ['tablet-landscape', 1180, 820],
    ['desktop', 1440, 900],
];

const PAGES = [
    ['home', 'http://localhost/'],
    ['catalogue', 'http://localhost/all-products'],
    ['product', 'http://localhost/products/divan-redking'],
    ['cart', 'http://localhost/cart'],
];

// Three lines so the cart and checkout render their real layout rather than
// the empty state.
const CART_VARIANTS = [283, 284, 285];

// Transitions interpolate, so a value read right after a layout change can be
// the old one. Killed before every measurement.
const FREEZE = '*,*::before,*::after{transition:none !important;animation:none !important;scroll-behavior:auto !important}';

function measure() {
    const one = (s) => document.querySelector(s);
    const boxH = (s) => {
        const el = one(s);
        return el ? Math.round(el.getBoundingClientRect().height) : null;
    };
    const boxW = (s) => {
        const el = one(s);
        return el ? Math.round(el.getBoundingClientRect().width) : null;
    };
    const cols = (s) => {
        const el = one(s);
        if (!el) return null;
        const value = getComputedStyle(el).gridTemplateColumns;
        return value === 'none' ? 1 : value.split(' ').filter(Boolean).length;
    };

    const slider = one('.fn_products_slide');
    const slide = slider ? slider.querySelector('.swiper-slide') : null;
    const perView = slider && slide && slide.getBoundingClientRect().width > 0
        ? Math.round((slider.getBoundingClientRect().width / slide.getBoundingClientRect().width) * 10) / 10
        : null;

    // Keyed by tag, first class and size, so the same control at the same size
    // collapses into one row with a count. Task 7 reads these keys directly.
    const tiny = {};
    const selector = 'a,button,input,select,textarea,[role="button"]';
    for (const el of document.querySelectorAll(selector)) {
        const r = el.getBoundingClientRect();
        if (r.width === 0 || r.height === 0) continue;
        if (r.height >= 44 && r.width >= 44) continue;
        const cls = (el.getAttribute('class') || '').trim().split(/\s+/)[0];
        const name = el.tagName.toLowerCase() + (cls ? '.' + cls : '');
        const key = name + ' ' + Math.round(r.width) + 'x' + Math.round(r.height);
        tiny[key] = (tiny[key] || 0) + 1;
    }

    return {
        vw: innerWidth,
        vh: innerHeight,
        scrollWidth: document.documentElement.scrollWidth,
        overflowX: document.documentElement.scrollWidth > innerWidth,
        docHeight: document.documentElement.scrollHeight,
        header: boxH('.vs-header__main'),
        stickyBuy: boxH('.vs-sticky-buy'),
        hero: boxH('.main_banner .banner_group__item'),
        carouselPerView: perView,
        catalogueCols: cols('.vs-catalogue__grid'),
        pdpCols: cols('.vs-pdp__layout'),
        galleryFrameW: boxW('.vs-gallery__frame'),
        tiny,
        tinyTotal: Object.values(tiny).reduce((a, b) => a + b, 0),
    };
}

const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'shell',
    args: ['--no-sandbox', '--disable-dev-shm-usage', '--force-device-scale-factor=1'],
});

const report = {label: LABEL, consoleErrors: [], results: {}};
let current = 'startup';

const page = await browser.newPage();
page.on('console', (m) => {
    if (m.type() === 'error') report.consoleErrors.push(current + ': ' + m.text());
});
page.on('pageerror', (e) => {
    report.consoleErrors.push(current + ': ' + String(e.message || e));
});

await page.setViewport({width: 390, height: 844});
for (const variant of CART_VARIANTS) {
    current = 'cart-fill/' + variant;
    await page.goto('http://localhost/cart?variant=' + variant + '&amount=1', {waitUntil: 'networkidle2'});
}

for (const [vpName, width, height] of VIEWPORTS) {
    await page.setViewport({width, height});
    for (const [pgName, url] of PAGES) {
        current = pgName + '/' + vpName;
        await page.goto(url, {waitUntil: 'networkidle2'});
        await page.addStyleTag({content: FREEZE});
        await new Promise((r) => setTimeout(r, 400));
        report.results[current] = await page.evaluate(measure);
        if (SHOTS) {
            await page.screenshot({path: OUT + '/' + LABEL + '-' + pgName + '-' + vpName + '.png', fullPage: true});
        }
    }
}

await browser.close();

const fs = await import('node:fs');
fs.writeFileSync(OUT + '/audit-' + LABEL + '.json', JSON.stringify(report, null, 2));

const rows = Object.entries(report.results).map(([key, r]) => [
    key.padEnd(28),
    'hdr=' + r.header,
    'sticky=' + r.stickyBuy,
    'hero=' + r.hero,
    'perView=' + r.carouselPerView,
    'catCols=' + r.catalogueCols,
    'pdpCols=' + r.pdpCols,
    'docH=' + r.docHeight,
    'tiny=' + r.tinyTotal,
    r.overflowX ? 'OVERFLOW-X' : '',
].join(' '));
console.log(rows.join('\n'));
console.log('\nconsoleErrors: ' + report.consoleErrors.length);
console.log('written: ' + OUT + '/audit-' + LABEL + '.json');

const overflowed = Object.values(report.results).some((r) => r.overflowX);
if (report.consoleErrors.length || overflowed) process.exit(1);
```

- [ ] **Step 3: Run it to capture the baseline**

```bash
cd /home/sviat/projects/OkayCMS
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
mkdir -p $D
node $D/audit.mjs $D baseline
```

Expected: exit 0, `consoleErrors: 0`, no `OVERFLOW-X` on any row, and these values, which are the numbers every later task moves:

| row | expected |
| --- | --- |
| `home/phone-portrait` | `hdr=61 perView=2` |
| `home/phone-landscape` | `hdr=61 perView=4 hero=362` |
| `home/tablet-portrait` | `hdr=61 perView=4` |
| `catalogue/phone-portrait` | `catCols=2` |
| `catalogue/phone-landscape` | `catCols=3` |
| `product/phone-landscape` | `sticky=73 pdpCols=1` |
| `product/desktop` | `sticky=0 pdpCols=2` |

If `perView` on `home/phone-landscape` is not 4, stop: the Swiper config differs from what the spec measured and the plan needs revisiting before anything is changed.

- [ ] **Step 4: Start the ledger**

Create `.superpowers/sdd/2026-07-28-vibe-shop-responsive/progress.md`:

```markdown
# vibe_shop phone and tablet pass — progress

Spec: `docs/superpowers/specs/2026-07-28-vibe-shop-responsive-design.md`
Plan: `docs/superpowers/plans/2026-07-28-vibe-shop-responsive.md`
Branch: `feature/vibe-shop-responsive`

Baseline captured in `audit-baseline.json`. Re-run any label with:
`node .superpowers/sdd/2026-07-28-vibe-shop-responsive/audit.mjs <dir> <label>`

## Task 1 — harness and baseline

Done. Baseline: home/phone-landscape perView=4 hero=362, product/phone-landscape
sticky=73 pdpCols=1, no horizontal overflow at any viewport, 0 console errors.
```

- [ ] **Step 5: Confirm there is nothing to commit**

```bash
cd /home/sviat/projects/OkayCMS
git status --short
```

Expected: empty. `.superpowers/sdd/` is git-ignored (`.superpowers/sdd/.gitignore` holds `*`), so the harness, the baseline and the ledger are deliberately untracked scratch for this plan. This task ships no commit — its deliverable is the baseline JSON on disk and the ledger's first block. Do not try to force-add the workspace; later tasks depend on it staying untracked.

---

### Task 2: Fix the Swiper breakpoint ladder

Spec item 1. The only change in this plan that affects all widths.

**Files:**
- Modify: `design/vibe_shop/js/okay.js:538-557`

**Interfaces:**
- Consumes: the harness from Task 1.
- Produces: nothing later tasks depend on.

- [ ] **Step 1: See the bug**

```bash
cd /home/sviat/projects/OkayCMS
sed -n '536,558p' design/vibe_shop/js/okay.js
```

Expected: `768:` appears twice in the same object literal. The second wins, so `768: {slidesPerView: 3}` is unreachable and 4 cards per view runs unbroken from 768 to 1199.

- [ ] **Step 2: Confirm the failing number**

```bash
cd /home/sviat/projects/OkayCMS
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp red-swiper | grep -E 'home/(tablet-portrait|phone-landscape|desktop)'
```

Expected: `home/tablet-portrait ... perView=4` and `home/phone-landscape ... perView=4`. That is the defect — at 820 px, 4 cards per view is roughly 190 px each.

- [ ] **Step 3: Replace the ladder**

In `design/vibe_shop/js/okay.js`, replace the `breakpoints` object of the `.fn_products_slide` Swiper (the one immediately under the `/* Carousel products */` comment near line 528):

```js
        breakpoints: {
          320: {
            slidesPerView: 1,
          },
          360: {
            slidesPerView: 2,
          },
          768: {
            slidesPerView: 3,
          },
          992: {
            slidesPerView: 4,
          },
          1200: {
            slidesPerView: 5,
          },
        },
```

The `576` entry is dropped rather than set: it would repeat the 2 already in force from 360. Every step now lands at 230-240 px per card.

Leave the second Swiper (`.fn_comparison_products`, near line 930) alone — comparison is out of scope.

- [ ] **Step 4: Clear caches and verify the numbers moved**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp green-swiper | grep -E 'home/'
```

Expected: `phone-portrait perView=2`, `phone-landscape perView=3`, `tablet-portrait perView=3`, `tablet-landscape perView=4`, `desktop perView=5`. Only the two middle rows move; the ladder's job is to end the 4-up run between 768 and 1199, and 1180 sits in the new `992` tier at a comfortable 295 px per card. Desktop was already 5 in the baseline — the old ladder's `1200: 5` step was never broken. And `consoleErrors: 0` — changing breakpoints re-initialises the carousels, so a Swiper error would surface here.

- [ ] **Step 5: Desktop regression check**

```bash
cd /home/sviat/projects/OkayCMS
python3 -c "
import json
a=json.load(open('.superpowers/sdd/2026-07-28-vibe-shop-responsive/audit-baseline.json'))['results']
b=json.load(open('/tmp/audit-green-swiper.json'))['results']
k='home/desktop'
print({f: (a[k][f], b[k][f]) for f in ('header','catalogueCols','docHeight','overflowX') if a[k][f]!=b[k][f]} or 'desktop unchanged')
"
```

Expected: `desktop unchanged`. `carouselPerView` is excluded from the comparison as a precaution, but it should not move either: 1440 px was already in the `1200` tier before this change.

- [ ] **Step 6: Record and commit**

Append to `progress.md`:

```markdown
## Task 2 — Swiper ladder

Duplicate `768` key removed. perView by viewport: phone-landscape 4→3,
tablet-portrait 4→3. phone-portrait (2), tablet-landscape (4) and desktop (5)
unchanged. 0 console errors. Desktop geometry unchanged.
```

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/js/okay.js
git commit -m "fix(vibe_shop): repair the product carousel breakpoint ladder

The breakpoints object declared 768 twice, so the first entry was dead
and four cards per view ran from 768 to 1199 - about 190px each at 820.
Every step now lands at 230-240px."
```

---

### Task 3: The short-viewport layer

Spec items 2, 3 and 7. Creates the section every later CSS task writes into, so it comes before them.

**Files:**
- Modify: `design/vibe_shop/css/components.css` — insert a new section immediately before the `/* TOUCH TARGETS */` banner

**Interfaces:**
- Consumes: the harness from Task 1.
- Produces: the `SHORT VIEWPORT` section and its `@media (max-height: 500px)` block. Tasks 4 and 5 add rules inside it and inside a second block created here.

- [ ] **Step 1: Find the insertion point**

```bash
cd /home/sviat/projects/OkayCMS
grep -n 'TOUCH TARGETS' design/vibe_shop/css/components.css
```

Expected: one hit around line 10017, wrapped in `/* ===== */` banners at 10016 and 10018. The new section goes immediately **before** line 10016. Nothing may be placed after the `TOUCH TARGETS` block — the file's own comment records that it is last so it wins on source order at equal weight.

- [ ] **Step 2: Confirm the failing numbers**

```bash
cd /home/sviat/projects/OkayCMS
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp red-short | grep -E '(home|cart)/phone-landscape'
```

Expected: `home/phone-landscape ... hero=356` — a banner as tall as the viewport under a 61 px header on a 390 px screen, so the first screen is banner and nothing else. Note `cart/phone-landscape docH=` too; it will be around 2700. `/cart` is the checkout: the contact, delivery, payment and totals panels all render on it.

- [ ] **Step 3: Insert the section**

Insert immediately before the `/* ===== */` banner line that precedes `TOUCH TARGETS`:

```css
/* ============================================================================ */
/* SHORT VIEWPORT                                                               */
/* ============================================================================ */

/* A phone held sideways is 844x390: a tablet's width on a third of a phone's   */
/* height. Every width breakpoint above has already fired and handed it the     */
/* tablet layout, so the correction cannot key off width - it keys off height.  */
/*                                                                             */
/* 500px is the threshold because no tablet reaches it (an iPad sideways is     */
/* 1180x820) and every phone in landscape is under it (844x390, 800x360,        */
/* 736x414). It also catches a short desktop window and a tablet in split view, */
/* which want the same treatment for the same reason.                          */
/*                                                                             */
/* Placed after every width block and before TOUCH TARGETS, which stays last:   */
/* these rules and the min-width rules they correct carry equal specificity, so */
/* source order is what decides them.                                          */

@media (max-height: 500px) {

/* 21/9 from 768px makes the banner 362px at 844px wide. Under a 61px header on */
/* a 390px viewport that is the entire fold. 60vh is 234px there, which leaves  */
/* about 95px of the next section showing - enough to say the page continues.   */

	.main_banner .banner_group__item {
		aspect-ratio: auto;
		height: 60vh;
	}

/* The rhythm is tuned for a viewport at least twice this tall. Section padding */
/* alone costs 48px between every pair of sections on a 390px screen.           */

	.vs-section {
		padding-top: var(--vs-space-5);
	}

	.vs-checkout__body {
		padding: var(--vs-space-4) 0 var(--vs-space-5);
	}

	.vs-pdp__masthead,
	.vs-catalogue__masthead,
	.vs-page__masthead,
	.vs-blog__masthead {
		padding: var(--vs-space-3) 0;
	}
}

/* ------------------------------------------------ short and wide: phone in landscape */

/* 640px keeps an iPhone SE sideways (667x375) in this layer while excluding a  */
/* narrow window that merely happens to be short.                              */

@media (max-height: 500px) and (min-width: 640px) {
	.vs-section {
		padding-top: var(--vs-space-6);
	}
}
```

- [ ] **Step 4: Clear caches and verify**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp green-short | grep -E '(home|cart)/(phone-landscape|phone-portrait|tablet-portrait)'
```

Expected: `home/phone-landscape hero` at roughly 234, down from 356; `home/phone-portrait hero` unchanged at its portrait value, because 844 px of height is nowhere near the threshold; `cart/phone-landscape docH` reduced by about 36 px. No `OVERFLOW-X`, `consoleErrors: 0`.

That 36 px is the honest figure and it is small: `/cart` carries exactly one `.vs-checkout__body` and no `.vs-section` or masthead, so halving its padding is the entire effect the rhythm compression can have there. The seven-screen checkout on a landscape phone is not meaningfully shortened by this task, and the spec already records that restructuring it is out of scope.

- [ ] **Step 5: Grep the diff for the three compiler traps**

```bash
cd /home/sviat/projects/OkayCMS
git diff -U0 -- design/vibe_shop/css/components.css | grep '^+' | grep '/\*' | grep -v '^+[[:space:]]*/\*' || echo "trap 1 clear: no comment shares a line with a declaration"
git diff -U0 design/vibe_shop/css/components.css | grep '^+' | grep 'var(--okay-' || echo "trap 2 clear: no --okay-* reference added"
git diff -U0 design/vibe_shop/css/components.css | grep '^+' | grep -E '^\+\s*\.[a-z-]+[a-z0-9_-]*\s*$' || echo "trap 3 clear: no selector line ends without a comma"
```

Expected: all three print their "clear" line. A hit on trap 1 or 3 means the rule silently will not exist; fix before continuing.

- [ ] **Step 6: Record and commit**

Append to `progress.md`:

```markdown
## Task 3 — short-viewport layer

New SHORT VIEWPORT section before TOUCH TARGETS, two blocks: max-height 500px,
and max-height 500px + min-width 640px. hero 362→234 at phone-landscape,
unchanged in portrait. cart docHeight at phone-landscape reduced. Three
compiler-trap greps clear.
```

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/css/components.css
git commit -m "feat(vibe_shop): add a height-aware layer for phones in landscape

A phone sideways has a tablet's width on a third of the height, so the
width breakpoints hand it the wrong layout. Caps the hero at 60vh and
tightens the vertical rhythm below 500px of viewport height."
```

---

### Task 4: Compact the product card on short viewports

Spec item 4.

**Files:**
- Modify: `design/vibe_shop/css/components.css` — inside the `@media (max-height: 500px)` block created in Task 3

**Interfaces:**
- Consumes: the `@media (max-height: 500px)` block from Task 3.

- [ ] **Step 1: Confirm the failing number**

```bash
cd /home/sviat/projects/OkayCMS
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp red-card >/dev/null
python3 -c "
import json
r=json.load(open('/tmp/audit-red-card.json'))['results']['catalogue/phone-landscape']
print('viewport height', r['vh'], 'header', r['header'], 'catCols', r['catalogueCols'])
"
```

Record the number. Then measure how far down the second row starts:

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 844 --height 390 \
  --eval "(()=>{const c=[...document.querySelectorAll('.vs-card')];const cols=getComputedStyle(document.querySelector('.vs-catalogue__grid')).gridTemplateColumns.split(' ').length;return JSON.stringify({cols,firstRowTop:Math.round(c[0].getBoundingClientRect().top),secondRowTop:Math.round(c[cols].getBoundingClientRect().top),cardH:Math.round(c[0].getBoundingClientRect().height)})})()" \
  2>&1 | grep evalResult
```

Expected: `secondRowTop` well past 390, i.e. the second row of products is entirely below the fold.

- [ ] **Step 2: Add the rules**

Inside the `@media (max-height: 500px)` block from Task 3, after the masthead rule, add:

```css
/* The card's breathing room is tuned for a portrait screen. These are the same */
/* values the 575px block already uses; the plate cap is what actually buys the */
/* second row, since a 1:1 plate in a three-column grid is 250px tall at 844.   */

	.vs-card {
		padding: var(--vs-space-2);
	}

	.vs-card__media-link {
		padding: var(--vs-space-3);
	}

	.vs-card__media {
		max-height: 180px;
	}
```

- [ ] **Step 3: Clear caches and verify**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/all-products --width 844 --height 390 \
  --eval "(()=>{const c=[...document.querySelectorAll('.vs-card')];const cols=getComputedStyle(document.querySelector('.vs-catalogue__grid')).gridTemplateColumns.split(' ').length;return JSON.stringify({cols,secondRowTop:Math.round(c[cols].getBoundingClientRect().top),cardH:Math.round(c[0].getBoundingClientRect().height)})})()" \
  2>&1 | grep evalResult
```

Expected: `cardH` reduced from Step 1 and `secondRowTop` lower than before.

`.vs-card__media` is the right element to cap: it carries `aspect-ratio: 1 / 1` at `components.css:2214`, and `.vs-card__media-link` inside it is `position: absolute; inset: 0`, so the link and the image follow the capped box without a rule of their own. `max-height` beats the aspect ratio, which is the intent — the plate becomes wider than tall rather than square.

- [ ] **Step 4: Portrait and desktop must not move**

```bash
cd /home/sviat/projects/OkayCMS
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp green-card >/dev/null
python3 -c "
import json
a=json.load(open('/tmp/audit-red-card.json'))['results']
b=json.load(open('/tmp/audit-green-card.json'))['results']
for k in ('catalogue/phone-portrait','catalogue/tablet-portrait','catalogue/desktop'):
    d={f:(a[k][f],b[k][f]) for f in ('docHeight','catalogueCols','overflowX') if a[k][f]!=b[k][f]}
    print(k, d or 'unchanged')
"
```

Expected: all three `unchanged`. Portrait viewports are 844 and 1180 tall and cannot match `max-height: 500px`.

- [ ] **Step 5: Grep the diff for the three compiler traps**

Same three commands as Task 3 Step 5. Expected: all clear.

- [ ] **Step 6: Record and commit**

Append the measured `cardH` and `secondRowTop` before and after to `progress.md`, then:

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): compact the product card on short viewports

A 1:1 plate in a three-column grid is 250px tall at 844px wide, so one
row filled a landscape phone. Caps the plate and drops the padding to
the values the 575px block already uses."
```

---

### Task 5: Two-column product page on short and wide

Spec item 5. The largest single win in the plan: 73 px of permanent chrome removed and the gallery uncapped.

**Files:**
- Modify: `design/vibe_shop/css/components.css` — inside the `@media (max-height: 500px) and (min-width: 640px)` block created in Task 3

**Interfaces:**
- Consumes: the short-and-wide block from Task 3; the existing `.vs-pdp__layout` two-column rule at `components.css:5237`.

- [ ] **Step 1: Read the layout being reused**

```bash
cd /home/sviat/projects/OkayCMS
sed -n '5227,5262p' design/vibe_shop/css/components.css
```

Expected: `.vs-pdp__layout` with `grid-template-columns: minmax(0, 760px) minmax(0, 460px)`, `.vs-pdp__gallery` at `grid-column: 1`, `.vs-buybox` at `grid-column: 2; grid-row: 1 / span 2`. This task reuses that shape with tracks sized for 844 px.

- [ ] **Step 2: Confirm the failing numbers**

```bash
cd /home/sviat/projects/OkayCMS
python3 -c "
import json
r=json.load(open('/tmp/audit-green-card.json'))['results']['product/phone-landscape']
print('pdpCols',r['pdpCols'],'stickyBuy',r['stickyBuy'],'header',r['header'],'galleryFrameW',r['galleryFrameW'],'vh',r['vh'])
"
```

Expected: `pdpCols 1 stickyBuy 73 header 61 galleryFrameW 480 vh 390` — one column, 134 px of 390 spent on chrome, and the gallery capped at 480 px while 844 px of width sits unused.

- [ ] **Step 3: Add the rules**

Inside the `@media (max-height: 500px) and (min-width: 640px)` block from Task 3, after the `.vs-section` rule, add:

```css
/* Width is the one thing a phone in landscape has. The 992px layout already    */
/* knows this shape - gallery left, buy box right - so it is reused here with   */
/* tracks that fit 844px rather than 1220px.                                    */

	.vs-pdp__layout {
		display: grid;
		grid-template-columns: minmax(0, 1fr) minmax(0, 320px);
		gap: var(--vs-space-5);
		align-items: start;
	}

	.vs-pdp__gallery {
		grid-column: 1;
		grid-row: 1;
	}

	.vs-buybox {
		grid-column: 2;
		grid-row: 1 / span 2;
	}

/* The 480px cap exists because an uncapped square stage in ONE column is 897px */
/* tall at 991px wide. In two columns the stage is half the page and that       */
/* cannot happen, so the cap only wastes the width it was protecting.           */

	.vs-gallery__frame {
		max-width: none;
		margin: 0;
	}

/* The bar earns its 73px - 19% of a 390px viewport - only while the real CTA   */
/* is off screen. Beside the gallery the CTA is on screen and the bar is a      */
/* duplicate button charging rent.                                             */

	.vs-sticky-buy {
		display: none;
	}

/* The bar owned the bottom edge, so the page reserved space for it. With the   */
/* bar gone that clearance is a dead band at the end of the page.               */

	.vs-pdp:last-child,
	.vs-section:last-child {
		padding-bottom: var(--vs-space-6);
	}
```

- [ ] **Step 4: Clear caches and verify**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp green-pdp --shots >/dev/null
python3 -c "
import json
r=json.load(open('/tmp/audit-green-pdp.json'))['results']
for k in ('product/phone-landscape','product/phone-portrait','product/tablet-portrait','product/desktop'):
    v=r[k]; print(k,'pdpCols',v['pdpCols'],'sticky',v['stickyBuy'],'galleryW',v['galleryFrameW'],'docH',v['docHeight'])
"
```

Expected: `product/phone-landscape pdpCols 2 sticky 0` and `galleryW` well above 480. `product/phone-portrait` and `product/tablet-portrait` must still read `pdpCols 1 sticky 73`. `product/desktop` unchanged at `pdpCols 2 sticky 0`.

- [ ] **Step 5: Look at it**

```bash
cd /home/sviat/projects/OkayCMS
ls /tmp/green-pdp-product-phone-landscape.png
```

Open the screenshot. The buy button must be visible without scrolling, the gallery must not be taller than the viewport, and the two columns must not collide. This is the one place in the plan where a number is not enough — a two-column grid can measure correct and still look wrong.

- [ ] **Step 6: Grep the diff for the three compiler traps**

Same three commands as Task 3 Step 5. Expected: all clear. Note the `.vs-pdp:last-child,` / `.vs-section:last-child` pair — the line break falls immediately after a comma, which is the one break the compiler allows.

- [ ] **Step 7: Record and commit**

Append the before/after of `pdpCols`, `stickyBuy` and `galleryFrameW` to `progress.md`, then:

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/css/components.css
git commit -m "feat(vibe_shop): put the product page in two columns on a landscape phone

At 844x390 the header and the sticky buy bar took 134px of 390 while
844px of width went unused. Reuses the 992px layout with narrower
tracks, which puts the CTA on screen and retires the bar."
```

---

### Task 6: Stack the buy row on narrow phones

Spec item 6. Independent of the short-viewport layer — this one is width-only.

**Files:**
- Modify: `design/vibe_shop/css/components.css` — inside the existing `@media (max-width: 575px)` block

**Interfaces:**
- Consumes: nothing from earlier tasks.

- [ ] **Step 1: Confirm the failing number**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/products/divan-redking --width 390 --height 844 \
  --eval "(()=>{const b=document.querySelector('.vs-buybox__submit:not(.hidden-xs-up)');const q=document.querySelector('.vs-buybox__qty');return JSON.stringify({submitW:Math.round(b.getBoundingClientRect().width),submitTop:Math.round(b.getBoundingClientRect().top),qtyTop:Math.round(q.getBoundingClientRect().top),sameRow:Math.abs(b.getBoundingClientRect().top-q.getBoundingClientRect().top)<20})})()" \
  2>&1 | grep evalResult
```

Expected: `sameRow: true` and `submitW` around 200 — the stepper and the primary CTA share a row and the button is squeezed to half the screen.

- [ ] **Step 2: Find the block**

```bash
cd /home/sviat/projects/OkayCMS
grep -n '@media (max-width: 575px)' design/vibe_shop/css/components.css
```

Expected: two hits (around lines 5432 and 9294). Use the **first** one — it is the block that already carries `.vs-card` and `.vs-card__cta` rules, i.e. the component block rather than the secondary one.

- [ ] **Step 3: Add the rule**

Inside that `@media (max-width: 575px)` block, add:

```css
/* .vs-buybox__buy is a wrapping flex row and .vs-buybox__submit sits on a      */
/* 160px basis, so at 390px it stays beside the stepper at about 200px wide -   */
/* half the screen for the page's primary action. A 100% basis makes it wrap    */
/* onto its own line without touching the row anywhere else.                    */

	.vs-buybox__submit {
		flex: 1 1 100%;
	}
```

- [ ] **Step 4: Clear caches and verify**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/products/divan-redking --width 390 --height 844 \
  --eval "(()=>{const b=document.querySelector('.vs-buybox__submit:not(.hidden-xs-up)');const q=document.querySelector('.vs-buybox__qty');return JSON.stringify({submitW:Math.round(b.getBoundingClientRect().width),sameRow:Math.abs(b.getBoundingClientRect().top-q.getBoundingClientRect().top)<20})})()" \
  2>&1 | grep evalResult
```

Expected: `sameRow: false` and `submitW` roughly the full content width (about 330 at 390 px).

- [ ] **Step 5: 576 px and up must not move**

```bash
cd /home/sviat/projects/OkayCMS
node .superpowers/sdd/2026-07-26-vibe-shop-redesign/shot.mjs \
  --url http://localhost/products/divan-redking --width 576 --height 900 \
  --eval "(()=>{const b=document.querySelector('.vs-buybox__submit:not(.hidden-xs-up)');const q=document.querySelector('.vs-buybox__qty');return JSON.stringify({sameRow:Math.abs(b.getBoundingClientRect().top-q.getBoundingClientRect().top)<20})})()" \
  2>&1 | grep evalResult
```

Expected: `sameRow: true` — the rule stops exactly at the breakpoint.

- [ ] **Step 6: Grep the diff for the three compiler traps, record and commit**

Same three greps as Task 3 Step 5, then append the before/after `submitW` to `progress.md` and:

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): give the add-to-cart button its own row below 576px

Sharing a row with the quantity stepper squeezed the page's primary
action to about 200px at 390px wide."
```

---

### Task 7: Tap targets at landscape and tablet

Spec item 8. `components.css` already ends with a finished touch-target pass, but it measured **375 px portrait only**. This task covers the viewports it never saw, and must not restate its rules.

**Files:**
- Modify: `design/vibe_shop/css/components.css` — extend the existing `TOUCH TARGETS` block only if landscape or tablet surfaces something new

**Interfaces:**
- Consumes: the `tiny` map from the harness, keyed `tag.firstClass WxH`.

- [ ] **Step 1: Read what the previous pass already decided**

```bash
cd /home/sviat/projects/OkayCMS
sed -n '10016,10060p' design/vibe_shop/css/components.css
```

Expected: the `TOUCH TARGETS` banner and its rationale, including three documented exemptions — links inside `.vs-prose` (WCAG 2.5.8 exempts inline links in a sentence), `<label for>` on a text field beside a 44 px input, and `.vs-crumbs__item a` at 34 px, which is the whole width of the word. Anything matching those is already decided; do not raise it and do not re-argue it.

- [ ] **Step 2: List what landscape and tablet surface**

```bash
cd /home/sviat/projects/OkayCMS
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp tiny-scan >/dev/null
python3 -c "
import json
r=json.load(open('/tmp/audit-tiny-scan.json'))['results']
port={k for key,v in r.items() if key.endswith('phone-portrait') for k in v['tiny']}
for vp in ('phone-landscape','tablet-portrait','tablet-landscape'):
    rows={}
    for key,v in r.items():
        if key.endswith(vp):
            for k,n in v['tiny'].items(): rows[k]=rows.get(k,0)+n
    new={k:n for k,n in rows.items() if k.split(' ')[0] not in {p.split(' ')[0] for p in port}}
    print('==',vp,'total',sum(rows.values()),'not seen in portrait:',new or 'none')
"
```

This prints, per viewport, every under-44 control and which of them the portrait pass could not have seen. The right-hand list is the actual work.

- [ ] **Step 3: Decide each one**

For every entry in the "not seen in portrait" lists, classify it:

1. **Covered by an existing exemption** (`.vs-prose` link, `<label for>`, `.vs-crumbs__item a`) — leave it, no rule.
2. **Desktop-only chrome** appearing at `tablet-landscape` because that viewport is 1180 px wide and gets the mouse layout — leave it. The previous pass's own reasoning applies: several targets are correct at 1440 with a mouse.
3. **A genuine touch control** that only landscape or tablet exposes — raise it.

Write the classification into `progress.md` **before** editing CSS. If every entry falls into groups 1 and 2, this task ships with no CSS change and that finding is the deliverable — say so plainly rather than inventing a rule to justify the task.

- [ ] **Step 4: Raise only group 3**

For each group-3 selector, add to the **existing** `TOUCH TARGETS` block, inside the `@media (max-width: 991px)` it already contains if the control is touch-only below 992 px, or in a new `@media (max-height: 500px)` inside that section if landscape is what exposes it:

```css
/* <selector>: measured <W>x<H> at <viewport>. <Why the portrait pass missed    */
/* it, and what the target grows into.>                                        */

	<selector> {
		min-height: 44px;
	}
```

Keep the surrounding block's comment style: a reason per rule, comments on their own lines.

- [ ] **Step 5: Verify the count moved, and that nothing else did**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs /tmp green-tiny >/dev/null
python3 -c "
import json
a=json.load(open('/tmp/audit-tiny-scan.json'))['results']
b=json.load(open('/tmp/audit-green-tiny.json'))['results']
for k in sorted(a):
    if a[k]['tinyTotal']!=b[k]['tinyTotal']: print(k, a[k]['tinyTotal'],'->',b[k]['tinyTotal'])
    if a[k]['docHeight']!=b[k]['docHeight']: print(' docHeight moved at',k,a[k]['docHeight'],'->',b[k]['docHeight'])
"
```

Expected: `tinyTotal` drops only at the viewports the task targeted. A `docHeight` change at a viewport you did not target means a `min-height` leaked into a layout it should not touch — fix before committing.

- [ ] **Step 6: Grep the diff for the three compiler traps, record and commit**

Same three greps as Task 3 Step 5. Then:

```bash
cd /home/sviat/projects/OkayCMS
git add design/vibe_shop/css/components.css
git commit -m "fix(vibe_shop): extend the touch-target pass to landscape and tablet

The existing pass measured 375px portrait only. Covers what the wider
and shorter viewports expose, and records which controls stay small
under the exemptions already documented in that block."
```

---

### Task 8: Full-matrix verification and close-out

Nothing new is built here. This task exists because every earlier task verified its own change in isolation, and interactions between them have not been checked once.

**Files:**
- Modify: `.superpowers/sdd/2026-07-28-vibe-shop-responsive/progress.md`
- Create: `.superpowers/sdd/2026-07-28-vibe-shop-responsive/audit-final.json`

- [ ] **Step 1: Full matrix with screenshots**

```bash
cd /home/sviat/projects/OkayCMS
rm -f compiled/vibe_shop/*.php cache/css/*
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
node $D/audit.mjs $D final --shots
```

Expected: exit 0, `consoleErrors: 0`, no `OVERFLOW-X` on any of the 20 rows.

- [ ] **Step 2: Diff every number against the baseline**

```bash
cd /home/sviat/projects/OkayCMS
D=.superpowers/sdd/2026-07-28-vibe-shop-responsive
python3 -c "
import json
a=json.load(open('$D/audit-baseline.json'))['results']
b=json.load(open('$D/audit-final.json'))['results']
fields=('header','stickyBuy','hero','carouselPerView','catalogueCols','pdpCols','galleryFrameW','docHeight','tinyTotal','overflowX')
for k in sorted(a):
    d={f:(a[k][f],b[k][f]) for f in fields if a[k][f]!=b[k][f]}
    if d: print(k); [print('   ',f,x,'->',y) for f,(x,y) in d.items()]
"
```

Every printed change must be one this plan intended. Anything unexplained is a regression — trace it to a task before closing out.

- [ ] **Step 3: Desktop must show only the carousel step**

The four `*/desktop` rows must be **identical to the baseline in every field**, `carouselPerView` included — Task 2's ladder does not move 1440 px, which was already in the `1200` tier. If `docHeight` moved on desktop, a `max-height` rule is matching a viewport it should not.

- [ ] **Step 4: Look at all twenty screenshots**

```bash
cd /home/sviat/projects/OkayCMS
ls .superpowers/sdd/2026-07-28-vibe-shop-responsive/final-*.png | wc -l
```

Expected: 20. Open at minimum the four landscape and tablet views of home, catalogue and product. Numbers cannot catch overlap, clipped text, or a two-column grid whose columns are correct and ugly.

- [ ] **Step 5: Full-file compiler-trap sweep**

```bash
cd /home/sviat/projects/OkayCMS
git diff master...HEAD -- design/vibe_shop/css/components.css | grep '^+' > /tmp/added.txt
grep '/\*' /tmp/added.txt | grep -v '^+[[:space:]]*/\*' || echo "trap 1 clear"
grep -n 'var(--okay-' /tmp/added.txt || echo "trap 2 clear"
grep -nE '^\+\s*\.[a-z0-9_-]+\s*$' /tmp/added.txt || echo "trap 3 clear"
```

Expected: three "clear" lines across the whole branch, not just the last task's diff.

- [ ] **Step 6: Close the ledger**

Append to `progress.md` a final block: the baseline-to-final table for the rows that changed, the desktop-unchanged confirmation, and the one open item carried from the spec — the URL-bar height behaviour under `max-height: 500px` needs one check on a real phone in landscape, which headless cannot reproduce.

```bash
cd /home/sviat/projects/OkayCMS
git status --short
```

Expected: empty. Like Task 1, this task ships no commit — the workspace is git-ignored, and the branch's code commits all landed in Tasks 2 through 7. The deliverable here is the verification record, and the record that matters to git is already in those commits.

---

## Self-Review

**Spec coverage.** Item 1 → Task 2. Item 2 → Task 3. Item 3 → Task 3. Item 4 → Task 4. Item 5 → Task 5. Item 6 → Task 6. Item 7 (cart and checkout inherit the rhythm compression) → Task 3, verified there via `cart/phone-landscape docHeight` — `/cart` carries the checkout form, so one row covers both. Item 8 → Task 7. The spec's verification section → Task 1 (harness, baseline) and Task 8 (full matrix, desktop guard, trap sweep). The spec's open item → carried into Task 8 Step 6. No spec requirement is unassigned.

**Type consistency.** The harness's report keys — `header`, `stickyBuy`, `hero`, `carouselPerView`, `catalogueCols`, `pdpCols`, `galleryFrameW`, `docHeight`, `tiny`, `tinyTotal`, `overflowX` — are defined in Task 1 Step 2 and every later task reads exactly those names. Page keys (`home`, `catalogue`, `product`, `cart`) and viewport keys (`phone-portrait`, `phone-landscape`, `tablet-portrait`, `tablet-landscape`, `desktop`) are likewise fixed in Task 1 and used verbatim in Tasks 2, 4, 5, 7 and 8.

**Placeholder scan.** No step says "add appropriate handling", "similar to Task N", or "write tests for the above". Every CSS step carries the actual rule, every verification step carries the actual command and the number it must print. Task 7 Step 3 is the one step that asks for judgement rather than a fixed edit; it is bounded by three named classification groups and by an explicit instruction that a no-CSS-change outcome is a legitimate result rather than a failure.

**Element check.** `.vs-card__media` (Task 4) carries `aspect-ratio: 1 / 1` at `components.css:2214`, and `.vs-pdp__layout` (Task 5) is the two-column grid at `components.css:5237` — both verified against the file, not assumed.
