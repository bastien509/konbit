# Submit Work Event

Source: /#/python/x-redirect/JTI0ZSUyRldvcmslMjUyMGV2ZW50cyUyRnN1Ym1pdFdvcmtFdmVudA

Stores the evidence, asks the Konbit agent (Claude) to review it, and returns the
review together with any payout plans it created. A plan's `decision` tells you
what the signed mandate allowed: `auto` (paid with PayPal Payouts), `ask`
(waiting for the freelancer's approval) or `block` (refused by policy).

```python
def submit_work_event(self,
                     body)
```


# Authentication

This endpoint requires [konbitKey]($h/__auth_CustomHeaderAuthentication)


# Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `body` | [`WorkEventInput`](/llms-pages/python/models/structures/work-event-input.md) | Body, Required | The evidence to review. Only `text` is required. |


# Response Type

**201**: The agent reviewed the evidence.

[`WorkEventResult`](/llms-pages/python/models/structures/work-event-result.md)


# Example Usage

```python
body = WorkEventInput(
    text='PR #42 "Landing page" was merged and deployed. Client: Landing page is live and looks great, thank you Jean!',
    source=EvidenceSourceEnum.GITHUB,
    title='Pull request #42 merged'
)

result = work_events_controller.submit_work_event(body)
print(result)
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
| 400 | The body is not valid JSON. | [`ApiErrorException`](/llms-pages/python/models/exceptions/api-error.md) |
| 401 | Missing or wrong X-Konbit-Key. | [`ApiErrorException`](/llms-pages/python/models/exceptions/api-error.md) |
| 413 | The body is larger than 50 KB. | [`ApiErrorException`](/llms-pages/python/models/exceptions/api-error.md) |
| 422 | The evidence was rejected (for example, too short) or could not be reviewed. | [`ApiErrorException`](/llms-pages/python/models/exceptions/api-error.md) |
| 429 | Rate limited (20 events per hour per workspace). | [`ApiErrorException`](/llms-pages/python/models/exceptions/api-error.md) |



