# CoreUpdater: Admin UI (Plan D) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Сторінка «Оновлення» в адмінці: показує встановлену/доступну
версію зі снапшота, нотатки релізу, кнопки «Перевірити зараз» і «Оновити»
(з підтвердженням), живий прогрес за кроками під час оновлення, чесні
стани failed/rolled_back із вказівками. Headless-конвеєр C1/C2 отримує
свій пульт.

**Architecture:** Один новий бекенд-контролер у модулі CoreUpdater за
еталоном `AutoDeployAdmin` (fetch + AJAX-методи того ж контролера,
RESPONSE_JSON), tpl з інлайн-JS (jQuery 4, house-патерн — модулі не мають
механізму окремих admin-JS-файлів), переклади `Backend/lang/{ua,ru,en}.php`.
Уся логіка відображення станів — чиста функція `CoreUpdaterViewModel`
(тестована в CI без БД); контролер і tpl — тонкі (контролери, як і
Console-команди, не юніт-тестяться: `IndexAdmin` тягне ServiceLocator —
той самий пре-рулений патерн).

**Tech Stack:** PHP 8.4/8.5, Smarty tpl адмінки, jQuery 4 + toastr
(глобально доступний), PHPUnit (pure only).

**Spec:** `docs/superpowers/specs/2026-08-30-core-self-updater-design.md`
§10 (макет UX), §8 (поллінг статусу замість довгого HTTP), §11 (explicit
downgrade — НЕ в цьому плані: run(targetVersion) приймає лише latest,
downgrade-UX свідомо відкладено). Живий прогін — Plan E.

## Global Constraints

- PHP ^8.4; українські why-коментарі; без TODO; без російської В КОДІ
  (файл `Backend/lang/ru.php` — легітимний контент перекладу, не коментар).
- CI без БД/мережі: юніт-тести лише для чистого ViewModel.
- **CSRF-пастка адмінки (обовʼязково)**: кожен POST-AJAX несе
  `session_id: '{$smarty.session.id}'` — без нього `Request` мовчки
  обнуляє $_POST (Request.php:426-433, AdminCsrfToken). «Перевірити
  зараз» і «Оновити» — ОБИДВА POST.
- **Контракти C1/C2 (binding, з леджера)**: `fetch()` читає ЛИШЕ
  `UpdateCheckHelper::getSnapshot()` (жодного мережевого виклику при
  рендері — спек §4); «Перевірити зараз» = AJAX POST → `check(true)`;
  запуск = `UpdateRunner::run(null)` (latest); статус =
  `UpdateStatus::load()` + `isStale()`; ключі снапшота
  `installed/latest{forkVersion,publishedAt,notesUrl,notesBody,meta{upstreamBase,minPhp,requiresMigrations}}/updateAvailable/checkedAt/lastError`;
  стан прогону: `step`, `updatedAt`, `error`, `rolledBackMigrations`,
  `migrationsDumpPath`, `backupZipPath`, `requiresManualIntervention`,
  `maintenanceDisabledAfterFailure`, `finalizeWarning`.
- Markdown-парсера у vendor немає: `notesBody` → `{$...|escape|nl2br}` +
  лінк `notesUrl` на GitHub.
- Меню: `extendBackendMenu()` — ключі є ленг-ключами; permission
  обовʼязковий (без нього сторінка порожня мовчки — memory-правило).
- Branch: `feat/coreupdater-admin-ui` від `origin/dev`.

---

## File Structure

- `Okay/Modules/OkayCMS/CoreUpdater/Helpers/CoreUpdaterViewModel.php` —
  pure: снапшот+стан прогону → дані для tpl і status-ендпоінта.
- `.../Backend/Controllers/CoreUpdaterAdmin.php` — fetch + 3 AJAX.
- `.../Backend/design/html/core_updater.tpl` — сторінка + інлайн-JS.
- `.../Backend/lang/ua.php`, `ru.php`, `en.php`.
- `.../Init/Init.php` — реєстрація контролера/permission/меню/main
  controller.
- Tests: `tests/Modules/OkayCMS/CoreUpdater/CoreUpdaterViewModelTest.php`.

---

### Task 1: `CoreUpdaterViewModel` (pure, tested)

**Files:**
- Create: `Okay/Modules/OkayCMS/CoreUpdater/Helpers/CoreUpdaterViewModel.php`
- Create: `tests/Modules/OkayCMS/CoreUpdater/CoreUpdaterViewModelTest.php`

**Interfaces:**
- `CoreUpdaterViewModel::build(?array $snapshot, ?array $runState, int $nowTs): array`
  — статичний, без залежностей. Повертає:
  ```
  [
    'mode' => 'no_data'|'up_to_date'|'update_available'|'running'|'stale_run'|'done'|'failed'|'rolled_back',
    'installed' => ?string, 'latest' => ?array (як у снапшоті),
    'checkedAt' => ?int, 'lastError' => ?string,
    'run' => ?array (step, stepIndex, stepsTotal, error,
             rolledBackMigrations, migrationsDumpPath, backupZipPath,
             requiresManualIntervention, maintenanceDisabledAfterFailure,
             finalizeWarning),
    'canStartUpdate' => bool, 'canCheckNow' => bool,
  ]
  ```
- Правила mode (пріоритет згори вниз):
  1. runState існує і step НЕ термінальний і НЕ stale → `running`
     (canStartUpdate=false, canCheckNow=false).
  2. runState нетермінальний але stale (isStale-логіка: updatedAt+600<now)
     → `stale_run` (canStartUpdate=true — resume дозволений C2-логікою;
     canCheckNow=true).
  3. runState термінальний: `done`/`failed`/`rolled_back` → відповідний
     mode; canStartUpdate = (updateAvailable зі снапшота); показ run-деталей.
  4. без runState: снапшот null/порожній → `no_data`; updateAvailable →
     `update_available`; інакше `up_to_date`.
  - `stepIndex/stepsTotal` — з `UpdateStatus::STEPS` (використати константу
    класу, не копію).
- Tests (мінімум 10): кожен mode; пріоритет running над updateAvailable;
  stale-межа (рівно 600с — звірити зі строгістю isStale: прочитати
  UpdateStatus::isStale і відтворити ту саму нерівність, НЕ вигадувати);
  canStartUpdate у done-без-нового-релізу = false; відсутні ключі
  снапшота не валять (снапшот зі старої версії модуля).

**Кроки:** TDD → повний suite + phpstan → commit
`feat(coreupdater): ViewModel станів сторінки оновлення`.

---

### Task 2: контролер + Init + переклади

**Files:**
- Create: `.../Backend/Controllers/CoreUpdaterAdmin.php`
- Create: `.../Backend/lang/ua.php`, `ru.php`, `en.php`
- Modify: `.../Init/Init.php`

**No PHPUnit test** (IndexAdmin → ServiceLocator, пре-рулений патерн;
контролер тонкий — вся логіка у ViewModel/хелперах C1-C2; живе — Plan E).

**Interfaces:**
- `CoreUpdaterAdmin extends IndexAdmin`:
  - `fetch(UpdateCheckHelper $checkHelper, UpdateStatus $status)`:
    `$vm = CoreUpdaterViewModel::build($checkHelper->getSnapshot(),
    $status->load(), time())` → assign('vm', $vm) + assign STEPS-мапу
    ленг-ключів → fetch('core_updater.tpl'). ЖОДНОГО check() тут.
  - `checkNow(UpdateCheckHelper $checkHelper)` — AJAX POST: перевірити,
    що $_POST непорожній (CSRF-пастка: порожній POST = невалідний токен →
    json {error: 'csrf'}); `$checkHelper->check(true)`; віддати свіжий
    ViewModel::build json-ом (RESPONSE_JSON).
  - `startUpdate(UpdateRunner $runner, UpdateCheckHelper $checkHelper,
    UpdateStatus $status)` — AJAX POST + CSRF-перевірка; guard: ViewModel
    mode мусить дозволяти (canStartUpdate) інакше json {error}; далі
    `ignore_user_abort(true)` вже робить run(); викликати
    `$runner->run(null)` СИНХРОННО в цьому запиті (спек §8: web-запуск з
    ignore_user_abort; поллінг паралельним GET-ом покаже прогрес);
    повернути фінальний стан json-ом (якщо зʼєднання ще живе).
  - `status(UpdateCheckHelper $checkHelper, UpdateStatus $status)` — AJAX
    GET (без CSRF — читання): ViewModel::build json-ом. Це полінг-ендпоінт.
- `Init::init()` додати: `registerBackendController('CoreUpdaterAdmin')`,
  `addBackendControllerPermission('CoreUpdaterAdmin', 'core_updater')`,
  `extendBackendMenu('left_core_updater_title',
  ['left_core_updater_title' => ['CoreUpdaterAdmin']], '<svgId>')` —
  іконку обрати з наявних у svg_icon.tpl (прочитати перелік; природний
  кандидат — той, що виглядає як стрілки-оновлення; якщо нема — додати
  Tabler `refresh` за конвенцією docs/admin-design.md §Іконки).
  `Init::install()` додати `setBackendMainController('CoreUpdaterAdmin')`.
- lang-файли: формат `$lang['key'] = '...';`. Ключі:
  `left_core_updater_title`, `core_updater_meta_title`,
  заголовки/лейбли сторінки (installed/available/based_on/published,
  check_now, update_btn, view_changes, confirm_title/text/yes/no,
  статуси кроків (по одному на кожен зі STEPS + done/failed/rolled_back),
  manual_intervention_text, backup_paths_label, migrations_not_rolled_back,
  stale_run_text, last_check_label, check_failed_label). ua — основна,
  ru/en — переклади того самого набору (повний паритет ключів).

**Кроки:** імплементація (звіряючись із реальним AutoDeployAdmin і
FAQ-прикладом меню) → suite+phpstan (парс) → commit
`feat(coreupdater): адмін-контролер, меню і переклади сторінки оновлення`.

---

### Task 3: tpl + інлайн-JS

**Files:**
- Create: `.../Backend/design/html/core_updater.tpl`

**No PHPUnit test** (tpl; BundledModificationsTest не зачеплений — модифікацій
немає; живий рендер — Plan E).

**Вимоги:**
- Структура за house-патерном (auto_deploy.tpl): `{$meta_title = ...}`,
  row/col, `wrap_heading`, `boxed`-блоки. Токени/класи адмінки
  (btn btn_small btn-info / btn-success тощо) — НЕ власні стилі; дрібний
  `<style>` допустимий лише для progress-списку кроків (немає готового).
- Блоки за станами ViewModel (`{if $vm.mode == ...}`):
  - `up_to_date`: встановлена версія + «Перевірити зараз» + час останньої
    перевірки (+ lastError якщо є).
  - `update_available`: картка версій (Встановлено / Доступно + based on
    upstreamBase з meta, дата, notesUrl-лінк «на GitHub»), notesBody
    `|escape|nl2br` у boxed «Що нового», requiresMigrations-бейдж,
    кнопки «Оновити до X» (btn-success) + «Перевірити зараз» (btn-info).
  - `running`: прогрес-панель — список STEPS з станами
    (зроблено/поточний/очікує — за stepIndex), поллінг.
  - `stale_run`: попередження + кнопка «Продовжити оновлення»
    (та сама startUpdate — C2 resume) + пояснення.
  - `failed`/`rolled_back`: помилка, requiresManualIntervention-блок
    (якщо true — шляхи backupZipPath/migrationsDumpPath,
    rolledBackMigrations перелік, посилання на docs/updates.md),
    maintenanceDisabledAfterFailure/finalizeWarning де доречно;
    кнопка повторної спроби якщо canStartUpdate.
  - `done`: успіх + нова версія (+ finalizeWarning якщо є).
  - `no_data`: «ще не перевірялось» + «Перевірити зараз».
- Confirm перед оновленням: проста інлайн-модалка в tpl (за зразком
  fn_action_modal з index.tpl:492-511 — ті самі класи) АБО
  fancybox-inline за AutoDeploy-прецедентом — обрати одну, зазначити.
  Текст: попередження про maintenance-режим на час оновлення + бекап.
- Інлайн `<script>` (jQuery):
  - «Перевірити зараз»: `$.ajax POST {url controller='OkayCMS.CoreUpdater.CoreUpdaterAdmin@checkNow'}`
    з `session_id:'{$smarty.session.id}'` → перерендер простіше за все
    `location.reload()` після success (toastr.success/error за
    результатом).
  - «Оновити»: confirm-модалка → POST startUpdate (session_id!) БЕЗ
    очікування відповіді як єдиного джерела (запит триватиме хвилини);
    одразу перемкнути UI в running-режим і стартувати поллінг.
  - Поллінг: `setInterval` 3с GET status → оновити прогрес-список
    (крок/лічильник) без перезавантаження; при переході в термінальний
    mode — `clearInterval` + `location.reload()` (сторінка відрендерить
    фінальний стан серверним ViewModel — менше JS-дублювання розмітки).
  - toastr для помилок AJAX (включно з {error:'csrf'} → зрозумілий текст).
- Екранування: УСЕ з снапшота/стану — `|escape` (notesBody, error-рядки,
  шляхи) — це дані з зовнішнього API/файлової системи.

**Кроки:** імплементація → suite (парс tpl не перевіряється CI, але
suite ганяємо як завжди) → commit
`feat(coreupdater): сторінка оновлення в адмінці з прогресом і підтвердженням`.

---

## Self-Review

**Spec coverage:** §10 макет — Task 3 (та сама структура «Встановлено/
Доступно/кнопки»); §8 поллінг статусу окремим запитом замість тримання
довгого HTTP — Task 2 status + Task 3 setInterval; §4 «ніколи не бʼє
GitHub синхронно з рендером» — fetch() лише getSnapshot (binding
constraint). Explicit downgrade §11 — свідомо поза планом (зазначено).

**Placeholder scan:** іконка меню — інструкція обрати з реального
svg_icon.tpl або додати за конвенцією (не placeholder, а верифікована
процедура з docs/admin-design.md). Модалка — вибір з двох названих
реальних прецедентів.

**Type consistency:** ViewModel-ключі Task 1 = споживання в tpl Task 3 і
json-ендпоінтах Task 2 (єдине джерело — build()). STEPS — константа
UpdateStatus, не копія.

**Scope check:** три задачі, UI-шар лише; жодних змін у C1/C2-хелперах;
живі прогони — Plan E.
