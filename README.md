# Oracle Arena

Oracle Arena is a small rock-paper-scissors game built to demonstrate the
complete PHPAML stack without handwritten JavaScript.

The application includes a reactive five-round duel, a public player name,
SQLite persistence, a JSON API and a leaderboard.

## Stack

- PHPAML View for the interface
- PHPAML Engine for reactive state and browser interactions
- PHPAML Data with SQLite for persistence
- PHPAML Framework for routing, sessions, CSRF protection and security headers

## Run locally

PHP 8.2 or newer and AML are required.

```bash
cp .env.example .env
aml install
aml data:migrate
aml serve
```

Open the address printed by AML, choose a public player name and complete the
five rounds. Finished games appear on `/leaderboard`.

## Test

```bash
aml test
```

The suite verifies View rendering, reactive declarations, API validation,
server-side outcome calculation, persistence and leaderboard responses.

## Security

Only `public/` should be exposed by the web server. Never commit `.env` or the
SQLite database stored under `runtime/storage/`. Game outcomes are calculated
again by the server instead of trusting the browser.

## Licence

MIT — see [LICENSE](LICENSE).
