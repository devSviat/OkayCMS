# release-migrations/pending/

Core-специфічні DB-міграції для наступного релізу форку. Кожен файл —
`{fork-version}_{опис}.up.sql`, ідемпотентний (`CREATE TABLE IF NOT EXISTS`,
перевірка через `INFORMATION_SCHEMA` перед `ALTER`).

Реліз-пайплайн (`ok release:build-package`, `.github/workflows/release.yml`)
бере все, що лежить тут на момент тегування, кладе в `migrations/` пакету
релізу — і **не чистить цю директорію автоматично**. Після успішного
релізу видаліть застосовані файли звідси окремим комітом (щоб наступний
реліз не переслав їх повторно).

Формат і механізм застосування на боці CMS — див.
`docs/superpowers/specs/2026-08-30-core-self-updater-design.md`, §7.
