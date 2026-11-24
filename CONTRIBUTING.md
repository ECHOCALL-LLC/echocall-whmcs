# Contributing

Thanks for helping. Issues and pull requests are welcome.

## Before you open a pull request

- Keep the module working on WHMCS 8.13 LTS, 9.0 and 9.1 and on PHP 8.2 to 8.4. CI checks the syntax on every supported PHP version.
- Keep the guard against direct access at the top of every PHP file, and `declare(strict_types=1);` in every file outside `lang/`.
- Escape every value in `templates/clientarea/main.tpl` with `|escape`. The client area uses no inline scripts.
- Check ids and URLs that come from the browser before they reach the API, and keep the token check on every client action.
- Every user-facing string lives in `lang/`. Add new strings to `english.php`, `german.php` and `french.php`.
- The module adds no dependencies and no build step. Please keep it that way.
- Run the checks from the README before you push.

## Versions

A release changes the version in every place `.github/scripts/check-versions.sh` lists and adds a section at the top of `CHANGELOG.md`. Pull requests leave the version alone; the maintainers set it when they release.

Describe your change under `## [Unreleased]` in `CHANGELOG.md` if you like, in plain words a WHMCS admin understands.

## Security problems

Please do not open a public issue. See [SECURITY.md](SECURITY.md).

## License

By contributing you agree that your contribution is licensed under the Apache License 2.0, like the rest of the module.
