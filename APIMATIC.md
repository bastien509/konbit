# Konbit + APIMatic

Konbit's work-event webhook (`hook.php`) is described in `openapi.yaml` (OpenAPI 3.0.3; it passes Redocly's validator).
APIMatic turns that spec into typed SDKs and a developer portal, so Zapier steps, GitHub Actions or a freelancer's
own scripts can send proof of work to Konbit in a few lines.

The APIMatic project folder is `apimatic/` (`apimatic/src/spec/openapi.yaml` is a copy of `openapi.yaml`).

## Generate the SDKs and the portal

You need a free APIMatic account (https://www.apimatic.io). Then, from the repository root:

```bash
npx @apimatic/cli@1.5.0 auth login
npx @apimatic/cli@1.5.0 api validate --file=apimatic/src/spec/openapi.yaml
npx @apimatic/cli@1.5.0 sdk generate --language=typescript --input=apimatic --destination=apimatic/sdk/typescript
npx @apimatic/cli@1.5.0 sdk generate --language=php --input=apimatic --destination=apimatic/sdk/php
npx @apimatic/cli@1.5.0 sdk generate --language=python --input=apimatic --destination=apimatic/sdk/python
npx @apimatic/cli@1.5.0 portal generate --input=apimatic --destination=apimatic/portal
```

## Using a generated SDK

Each SDK wraps two calls, authenticated with the workspace key in the `X-Konbit-Key` header:
- `submitWorkEvent`: send evidence (`source`, `title`, `text`) and get back the agent's review and any payout plans.
- `getWorkEvent`: read the result for an `event_id` later.

The key comes from Konbit → Mandate → Work-event webhook and is shown once.
