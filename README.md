OkayCMS v4.5.2
----------------------

OkayCMS is a PHP CMS for creating backend and frontend applications.

 - Homepage:        [https://okay-cms.com](https://okay-cms.com)
 - Documentation:   [https://github.com/OkayCMS/OkayCMS/tree/master/docs](https://github.com/OkayCMS/OkayCMS/tree/master/docs)
 - Repository:      [https://github.com/OkayCMS/OkayCMS](https://github.com/OkayCMS/OkayCMS)

OkayCMS is released under the LGPL license.

Copyright 2015-2024 OkayCMS

About this fork
----------------------

Three things this fork adds on top of upstream OkayCMS v4.5.2:

 - **PHP 8.4 and 8.5.** `composer.json` requires `php ^8.4`; CI runs the test suite on both 8.4 and
   8.5, and PHPStan on 8.5. The local Docker environment ships a single `php85` service - the
   legacy PHP 7.4 one is gone.
 - **`vibe_shop`, a new storefront theme.** A full redesign of the shop front, responsive on phones
   and tablets in both orientations. It is the theme this fork ships enabled; the stock `okay_shop`
   is untouched and can be selected back at any time in the admin panel under Design.
 - **A security hardening pass.** CSRF protection and POST-only mutations on the storefront,
   Argon2id password hashing, `HttpOnly` cookies, path-traversal fixes in the admin theme editors
   and the file manager, and more. It changes behaviour a custom theme or module can depend on -
   [`docs/UPGRADE-security.md`](docs/UPGRADE-security.md) states what breaks and what to do about
   it. No database migrations.

The dependency work that came with PHP 8.5 support also cleared advisories the fork had inherited:
`composer audit` reported 11 across four packages on the pre-fork lock file and reports 2 today. Six
of the eleven were in Smarty 3.1.40 - the template engine that runs on every request - including PHP
code injection (CVE-2024-35226, CVE-2022-29221) and a sandbox escape (CVE-2021-29454). The two that
remain are in `maximebf/debugbar`, a `require-dev` package that never ships to production. The
"Залежності" section of the same document has the package-by-package list.
