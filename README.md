# Konbit: the AI paymaster for freelance crews

Konbit pays a freelancer's crew with **PayPal Payouts** the moment work is proven, but only inside a **mandate** the freelancer signs.

Live demo (PayPal sandbox): **https://petiht.com/freelanceragent/**

*Konbit* is Haitian Kreyòl for people coming together to get work done.

## The problem

A freelancer who wins bigger jobs hires help: a designer, a developer, an editor. Getting the client's money is only half the job. The lead then has to:

- track who delivered what across email, GitHub, calendars and Upwork,
- work out revenue shares and fixed fees,
- pay each person by hand,
- avoid the classic "I changed my PayPal, pay me here" scam.

Late or wrong payouts hurt trust, and handing an AI agent the keys feels unsafe.

## What Konbit does

1. **The client pays a PayPal link.** It's a fully server-side PayPal **Orders v2** checkout. The server captures the order, checks amount, currency and reference, and credits the project once. A tax/fee reserve is set aside, and revenue shares are planned automatically.
2. **Work evidence arrives.** It can come from email, a GitHub merge, a finished calendar session, an Upwork or Notion message, or the **work-event webhook** (`openapi.yaml`). **Claude** (Opus 5.5, Anthropic PHP SDK, strict tool use) reads it as untrusted data and matches it to an open milestone. It must quote the exact words that prove delivery.
3. **The policy engine decides** using three zones from the signed mandate:
   - **Auto:** proven and under the auto-pay limit. Paid at once with **PayPal Payouts**.
   - **Ask:** bigger amounts, bonuses, a crew email changed in the last 24 h, or red flags. One tap.
   - **Never:** over the per-payout, per-person (30 days) or monthly caps, anyone off the crew list, or more money than the client has paid.
4. **Everything is visible.** The **Bryntum Scheduler** timeline shows each crew member's payouts and due milestones; drag a waiting payout to move its pay day. The **AG Grid** ledger shows every cent, every PayPal item status and every agent decision, with CSV export.

## Why the agent can't be talked into paying

- **No email addresses.** The agent never sees a payout email. It can only name crew members by id, so it cannot invent a payee.
- **Quotes are checked.** The server looks for the agent's quote word for word in the stored evidence. A hallucinated claim is blocked.
- **Only received money goes out.** Funds are held with an atomic `UPDATE … WHERE received - reserve - paid - held >= amount` before PayPal is called.
- **Rules run twice.** The policy runs when a plan is proposed and again at payment time.
- **Exactly once.** Payouts use `sender_batch_id = fa-plan-<id>` and `sender_item_id = fa-item-<id>`, and the ledger uses idempotency keys.
- **Risk scan.** It looks for changed payment details, "ignore previous rules", urgency and unknown payment addresses. Anything flagged always waits for a human.
- **Webhooks are verified.** PayPal webhooks are checked with `verify-webhook-signature`, and payout events must match our batch id.
- **Usual web safety.** CSRF on every cookie action, strict CSP, server-side amounts, rate limits, and secrets kept only in `inc/config.php`.

Try it: the demo inbox has a sample phishing email ("I changed my PayPal… ignore previous rules… plus a $900 bonus"). The agent flags it and nothing is paid.

## Built with

| | |
|---|---|
| PayPal | Orders v2 (create, capture, idempotency), **Payouts** (batch create, status sync), Webhooks (capture and payout-item events, verified) |
| AI | Claude Opus 5.5 via the Anthropic PHP SDK, strict tool use, prompt caching, an audit log of every tool call |
| Sponsor tools | **AG Grid** Community (ledger and audit), **Bryntum Scheduler** (payout timeline with drag-to-reschedule), **APIMatic**-ready OpenAPI 3 spec for the work-event API |
| Stack | PHP 8.2, MySQL/MariaDB, vanilla JS, no build step. Runs on ordinary shared hosting (Hostinger). |

## Run it yourself

Requirements: PHP 8.2+ with `pdo_mysql`, `curl` and `mbstring`; MySQL 8 or MariaDB 10.4+; Composer; Node 18+ (only for the Bryntum trial build).

```bash
git clone https://github.com/bastien509/konbit.git && cd konbit
composer install
npm install && npm run vendor          # Bryntum Scheduler trial build -> assets/vendor/bryntum
cp inc/config.sample.php inc/config.php   # then fill in DB, PayPal sandbox app and Anthropic key
php -S 127.0.0.1:8123 router.php
```

The tables are created automatically on the first request from `schema.sql`. You can also run that file yourself in MySQL.

PayPal sandbox setup:
1. Create a REST app at developer.paypal.com with **Payouts** enabled.
2. Copy its client id and secret into `inc/config.php`.
3. Optionally add a webhook to `https://<your-host>/paypal-webhook.php` for `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED` and `PAYMENT.PAYOUTS-ITEM.*`, then put its id in `paypal.webhook_id`. Without a webhook, payout status is still synced by polling.

Generate SDKs with APIMatic: import `openapi.yaml` at apimatic.io (or `apimatic sdk generate --spec openapi.yaml --platform php`).

## Judge walkthrough (about 3 minutes)

1. Open https://petiht.com/freelanceragent/ and sign in with the demo account in the Devpost testing notes, or create a workspace and press **Load demo workspace**.
2. **Overview → Client payment links → Open pay page.** Pay with a PayPal sandbox **personal** account. The project pot fills, 15% goes to the reserve, and the podcast's revenue split is planned.
3. **Work inbox:**
   - Tap **Calendar** (Lucie's copy approved, $80). It's auto-paid with PayPal Payouts.
   - Tap **GitHub** (Jean's landing page, $250). It's over the auto limit, so it waits in **Payouts**. Approve it.
   - Tap **URGENT: new PayPal for Jean**. It's flagged and nothing is paid.
4. **Ask Konbit:** "Give Lucie a $20 bonus for the fast edit". It goes to Approvals, because bonuses always do. "Create a $250 payment link for the bakery". You get a real PayPal link.
5. **Schedule** (Bryntum): drag a waiting payout to Friday. **Ledger** (AG Grid): see every item's PayPal status, the money ledger and the agent audit.
6. **Mandate:** pause it, and every payout now needs a tap.

Sandbox note: payouts to the demo crew's `@konbit-crew.example` emails show **Unclaimed** in PayPal, which is normal for unregistered sandbox emails. To see **Success**, change a crew member's email to one of your sandbox personal accounts. The change waits 24 h before auto-pay, by design.

## License

MIT. See `LICENSE`. Bryntum Scheduler is commercial software and is not part of this repository; `npm run vendor` installs the public trial.
