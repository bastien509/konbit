# Submit Work Event

Source: /#/http/x-redirect/JTI0ZSUyRldvcmslMjUyMGV2ZW50cyUyRnN1Ym1pdFdvcmtFdmVudA

Stores the evidence, asks the Konbit agent (Claude) to review it, and returns the
review together with any payout plans it created. A plan's `decision` tells you
what the signed mandate allowed: `auto` (paid with PayPal Payouts), `ask`
(waiting for the freelancer's approval) or `block` (refused by policy).

```http
POST /hook.php
```


# Authentication

This endpoint requires [konbitKey]($h/__auth_CustomHeaderAuthentication)


# Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `body` | [`Work Event Input`](/llms-pages/http/models/structures/work-event-input.md) | Body, Required | The evidence to review. Only `text` is required. |


# Response Type

**201**: The agent reviewed the evidence.

[`Work Event Result`](/llms-pages/http/models/structures/work-event-result.md)


# Example Usage

```bash
curl -X POST \
  --url 'https://petiht.com/freelanceragent/hook.php'  \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -H 'X-Konbit-Key: X-Konbit-Key' \
  --data-raw '{
  "source": "github",
  "title": "Pull request #42 merged",
  "text": "PR #42 \"Landing page\" was merged and deployed. Client: Landing page is live and looks great, thank you Jean!"
}'
```


# Example Response *(as JSON)*

```json
{
  "event_id": 12,
  "status": "done",
  "risk": "low",
  "summary": "PR #42 was merged and the client confirmed the landing page is live. I proposed Jean's $250.00 milestone.",
  "plans": [
    {
      "plan_id": 7,
      "title": "Jean Pierre · Landing page live",
      "total_cents": 25000,
      "decision": "ask",
      "status": "needs_approval"
    }
  ]
}
```


# Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | The body is not valid JSON. | [`ApiError_ErrorException`](/llms-pages/http/models/exceptions/api-error-error.md) |
| 401 | Missing or wrong X-Konbit-Key. | [`ApiError_ErrorException`](/llms-pages/http/models/exceptions/api-error-error.md) |
| 413 | The body is larger than 50 KB. | [`ApiError_ErrorException`](/llms-pages/http/models/exceptions/api-error-error.md) |
| 422 | The evidence was rejected (for example, too short) or could not be reviewed. | [`ApiError_ErrorException`](/llms-pages/http/models/exceptions/api-error-error.md) |
| 429 | Rate limited (20 events per hour per workspace). | [`ApiError_ErrorException`](/llms-pages/http/models/exceptions/api-error-error.md) |



