# Zapier Integration — NAASE SE Basics Challenge

This plugin fires a webhook to Zapier once a participant **completes the quiz and
submits the contact form**. Use this document to set up the Zap and map the fields.

## 1. Trigger type

In Zapier, create a Zap with:

- **App:** Webhooks by Zapier
- **Event:** **Catch Hook** (not "Catch Raw Hook")

Zapier generates a unique URL, e.g.:

```
https://hooks.zapier.com/hooks/catch/123456/abcdef/
```

## 2. Connect the webhook to the plugin

Paste that URL into the plugin:

1. WordPress admin → **NAASE Challenge → Settings**
2. Field **Zapier Webhook URL** → paste the Catch Hook URL
3. **Save**

## 3. Request details

| Property | Value |
|---|---|
| Method | `POST` |
| Content-Type | `application/json; charset=utf-8` |
| When it fires | Once, after the quiz is completed **and** the contact form is submitted |
| Delivery | Fire-and-forget (non-blocking, no retry — see Notes) |

## 4. Payload

The body is a JSON object with these fields:

```json
{
  "token": "abc123",
  "first_name": "John",
  "last_name": "Doe",
  "email": "john@example.com",
  "score": 9,
  "total": 12,
  "tier": "SE Basics Ready",
  "duration_seconds": 312,
  "duration_text": "5 minutes 12 seconds",
  "questions": [
    {
      "number": 1,
      "question": "What is the primary goal of a discovery call?",
      "selected_letter": "B",
      "selected_answer": "Understand the customer's problems and goals",
      "correct_letter": "B",
      "correct_answer": "Understand the customer's problems and goals",
      "is_correct": true
    },
    {
      "number": 2,
      "question": "Which artifact captures success criteria for a POC?",
      "selected_letter": "A",
      "selected_answer": "A feature list",
      "correct_letter": "C",
      "correct_answer": "A mutual evaluation plan",
      "is_correct": false
    }
  ],
  "answers_text": "1. What is the primary goal of a discovery call?\n   Your answer: Understand the customer's problems and goals ✓\n\n2. Which artifact captures success criteria for a POC?\n   Your answer: A feature list ✗\n   Correct answer: A mutual evaluation plan",
  "join_leaderboard": true,
  "membership_interest": false,
  "linkedin": "https://linkedin.com/in/johndoe",
  "started_at": "2026-06-17 10:00:00",
  "finished_at": "2026-06-17 10:05:12",
  "result_url": "https://your-site.example/naase-result/abc123/",
  "badge_url": "https://your-site.example/wp-content/plugins/naase-challenge/assets/badges/ready.png",
  "site": "https://your-site.example/"
}
```

### Field reference

| Field | Type | Notes |
|---|---|---|
| `token` | string | Unique attempt ID; also used in `result_url` |
| `first_name` | string | |
| `last_name` | string | |
| `email` | string | |
| `score` | integer | Correct answers (0–12) |
| `total` | integer | Total questions (always 12) |
| `tier` | string | One of: `SE Basics Explorer` (0–5), `SE Basics Builder` (6–8), `SE Basics Ready` (9–10), `SE Basics Ace` (11–12) |
| `duration_seconds` | integer | Server-measured time to complete |
| `duration_text` | string | Human-readable duration |
| `questions` | array | Per-question breakdown, in the order the participant answered. One object per question (see below). Full texts are included, so you don't need a copy of the question bank in Zapier. No bank IDs are exposed — only the running number 1..12. |
| `questions[].number` | integer | Position as the participant answered it (1..12) — the same numbering they see in their email |
| `questions[].question` | string | Full question text |
| `questions[].selected_letter` | string | Letter the participant chose (`A`–`D`), or `""` if unanswered |
| `questions[].selected_answer` | string | Full text of the chosen answer (`""` if unanswered) |
| `questions[].correct_letter` | string | Letter of the correct answer (`A`–`D`) |
| `questions[].correct_answer` | string | Full text of the correct answer |
| `questions[].is_correct` | boolean | Whether the chosen answer was correct |
| `answers_text` | string | Ready-to-paste plain-text block of the whole breakdown (numbered 1..12, full texts, `✓`/`✗`). Use this when you just want to drop the results into an email body without mapping line items. |
| `join_leaderboard` | boolean | User opted into the public leaderboard |
| `membership_interest` | boolean | User is interested in NAASE membership |
| `linkedin` | string | LinkedIn URL, or empty string `""` |
| `started_at` | string | `YYYY-MM-DD HH:MM:SS`, UTC |
| `finished_at` | string | `YYYY-MM-DD HH:MM:SS`, UTC |
| `result_url` | string | Public shareable result page |
| `badge_url` | string | PNG badge for the achieved tier |
| `site` | string | Site home URL |

## 5. Setting up the Zap (step by step)

1. Create the Zap → **Catch Hook** → copy the generated webhook URL.
2. Paste the URL into the plugin Settings (section 2) and save.
3. In Zapier, click **Test trigger**, then **complete the quiz once on the live
   site** so a real payload arrives. Zapier will auto-detect all fields from that
   sample.
4. Build the Action step (Google Sheets, CRM, email, Mailchimp, etc.) and map the
   fields above.
   - For a quick email, map `answers_text` straight into the body — it already
     contains every question, the participant's answer and the correct one.
   - For per-question formatting, the `questions` array comes through as Zapier
     **line items** (`questions number`, `questions question`, `questions
     selected_answer`, …) which you can loop over or send to a line-item-aware action.

## Notes

- The webhook is sent **non-blocking** with no automatic retry. If Zapier is
  unreachable at the moment of submission, that single event is lost. This is fine
  for typical lead capture but worth knowing.
- To generate a test sample, **complete the quiz and submit the form** — the Catch
  Hook only receives a real `POST` from the plugin. There is no separate "send test"
  button in the plugin.
- Timestamps are in **UTC**.
