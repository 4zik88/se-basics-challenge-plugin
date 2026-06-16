# NAASE SE Basics Challenge — WordPress Plugin

A WordPress plugin implementing the NAASE Sales Engineering Basics Challenge: a 12-question
timed quiz with random question selection, scoring + tiers, a contact form, downloadable
badge, social sharing with OpenGraph, a public leaderboard and a Zapier completion webhook.

The visible experience follows the Figma design. The plugin lives in `naase-challenge/`.

## Features

- **Shortcodes:** `[naase_challenge]` (the full experience) and `[naase_leaderboard]`.
- **Question bank** (custom DB table) with admin CRUD + CSV/JSON import & export.
- **12 random questions** per attempt, random order, 1 point each, server-side scoring.
- **Tiers:** 0–5 Explorer · 6–8 Builder · 9–10 Ready · 11–12 Ace.
- **Timer** (server-authoritative) with a **1-hour timeout** → session-revive screen.
- **Save rules:** 0 answers → nothing stored; ≥1 answer then leaves/times out → partial
  (internal); 12 answered → score/tier/time stored; form submit adds user data.
- **Result page** at `/naase-result/{token}/` with OG/Twitter tags + downloadable badge (GD).
- **Leaderboard** (best result per email; sort: score desc, time asc, newest date desc) with
  pagination; only participants who opted in appear.
- **Admin** editable texts (title, description, 4 features, post-completion, share, privacy),
  Zapier webhook URL, and a Leaderboard/Responses screen to edit or delete entries.
- **Anti-spam:** honeypot + min-time check + WP nonce on the contact form.

## Local development (Docker + ngrok)

Requires Docker Desktop running.

```bash
./bin/setup.sh
```

This brings up WordPress (http://localhost:8080) + MariaDB, installs WP, activates the plugin,
creates the `/challenge/` and `/leaderboard/` pages, and seeds demo questions.

- Site: http://localhost:8080
- Admin: http://localhost:8080/wp-admin (`admin` / `admin123`)
- Challenge: http://localhost:8080/challenge/
- Leaderboard: http://localhost:8080/leaderboard/

### Demo over the internet

```bash
ngrok http 8080
```

The container derives its site URL from the request host, so the ngrok URL works without any
config changes (HTTPS behind the tunnel is handled too).

## Deployment

The plugin uses standard `$wpdb`/`dbDelta`, so it runs on any normal MySQL/MariaDB WordPress.
Copy `naase-challenge/` into `wp-content/plugins/`, activate, set pretty permalinks, then add
the shortcodes to your pages and configure texts + the Zapier webhook under **NAASE Challenge**.
