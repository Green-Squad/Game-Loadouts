# Patched PHP, original application

This proposal replaces PHP 5.6.40 with PHP 8.4.27, which contains the October 2026 PHP2Shell security fixes. It changes the historical runtime and Debian base, while retaining Laravel 4.1.31, the existing Composer lock, controllers, Blade templates, styling, artwork, routes, disposable MySQL 5.7 sidecar and reset behavior. No framework upgrade, new UI or database migration is included.

The Dockerfile uses current GD build options and explicitly installs the original application's extensions. mcrypt 1.0.9 preserves Laravel's existing Rijndael-256 encryption format. This extension and libmcrypt are unmaintained; keeping them is an explicit compatibility compromise, not a modern cryptography design. Replacing the cipher/framework is outside this proposal.

`php84.patch` contains the complete vendor compatibility changes and is applied with zero fuzz after installing the exact locked dependencies. A failed patch stops the build. The changes are:

- Patchwork checks that the removed mbstring overload constant exists before reading it.
- Laravel lets PHP log deprecations instead of turning them into HTTP 500s. Regular warnings still become exceptions. Native PHP Error objects are wrapped for the original exception renderer, retaining the cause and original error log.
- Route and controller dispatch use positional argument lists, preserving PHP 5 behavior instead of treating route keys as PHP 8 named arguments.
- The file-input helper accepts the original empty-string options, without changing templates or previously ignored extra arguments.
- Symfony ignores newer `full_path` upload metadata, retaining basename-only handling for normal and nested file inputs.
- The original LESS compiler uses the empty import-directory values PHP 5 used when metadata is absent.
- CSV trait declarations match their existing static string-validation implementation; parsing stays unchanged.

PHP logs go to stderr and errors are not displayed in responses. Deprecation notices from the retained dependencies remain visible in logs; this is a compatibility port rather than a dependency modernization.

The home controller stops passing two undefined, unused variables to `compact()`, which PHP 8 reports as warnings. Its view data and rendered UI remain the same.

The first-party search existence check uses an explicit null test instead of counting a non-Countable Eloquent model. The normal PHP 5.6 behavior for a found/missing row is preserved.

PR validation builds disposable containers and exercises the original public/admin forms, authentication and remember cookies, uploads/thumbnails, relationships, CSV import, submissions/votes, desktop/mobile rendering, shutdown and replacement reset. Focused PHP regressions cover Unicode encryption, passwords, dates, LESS, and deprecation versus warning handling. PR validation has no deployment step. Only an eventual approved merge into `hand-forged` can invoke its existing release pipeline.

This does not make the old framework, database version or every dependency current or fully audited. It addresses the PHP runtime vulnerability and the compatibility failures found by the regression suite. The live historical demo is unchanged until this proposal is approved and merged.
