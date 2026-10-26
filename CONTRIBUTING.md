# Contributing to Pennova AI

Thanks for helping! Small PRs are easiest to review.

## Dev setup
- PHP 7.4+ and WordPress 5.8+. Easiest: [LocalWP](https://localwp.com) or `npx @wordpress/env start`.
- Symlink/copy this folder into `wp-content/plugins/pennova-ai` and activate it.
- Use a throw-away AI key with a low spend limit.

## Layout
```
pennova-ai.php        bootstrap
includes/             class-settings, -providers, -generator, -images, -youtube,
                      -frontend, -queue, -notify, -admin
assets/               admin.css, admin.js, logos
docs/                 extra guides
```

## Common contributions
- **New AI provider**: add an entry to `AIAP_Providers::presets()` (`format` = `openai` or `anthropic`, `base`, default `model`).
- **New alert channel**: add a method in `AIAP_Notify` and wire it in `dispatch()` + a settings card.
- **Translations**: wrap strings in `__()` with text domain `pennova-ai` (help wanted).

## Rules
- Follow WordPress coding standards; escape output (`esc_html`, `esc_attr`, `esc_url`), sanitise input, check nonce **and** capability in every AJAX handler.
- Never log or echo API keys. Secrets go through `AIAP_Settings::SECRETS`.
- Lint before pushing: `find . -name '*.php' -print0 | xargs -0 -n1 php -l` and `node --check assets/admin.js`.
- Describe what you tested (PHP/WP version, provider) in the PR.

By contributing you agree your work is licensed GPL-2.0-or-later.
