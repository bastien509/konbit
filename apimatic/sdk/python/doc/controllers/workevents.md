# Workevents

```python
workevents_controller = client.workevents
```

## Class Name

`WorkeventsController`

## Methods

* [Submit Work Event](../../doc/controllers/workevents.md#submit-work-event)
* [Get Work Event](../../doc/controllers/workevents.md#get-work-event)


# Submit Work Event

Stores the evidence, asks the Konbit agent (Claude) to review it, and returns the
review together with any payout plans it created. A plan's `decision` tells you
what the signed mandate allowed: `auto` (paid with PayPal Payouts), `ask`
(waiting for the freelancer's approval) or `block` (refused by policy).

```python
def submit_work_event(self,
                     body)
```

## Authentication

This endpoint requires [konbitKey](../../doc/auth/custom-header-signature.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `body` | [`WorkEventInput`](../../doc/models/work-event-input.md) | Body, Required | The evidence to review. Only `text` is required. |

## Response Type

**201**: The agent reviewed the evidence.

[`WorkEventResult`](../../doc/models/work-event-result.md)

## Example Usage

```python
body = WorkEventInput(
    text='PR #42 "Landing page" was merged and deployed. Client: Landing page is live and looks great, thank you Jean!',
    source=EvidenceSourceEnum.GITHUB,
    title='Pull request #42 merged'
)

result = work_events_controller.submit_work_event(body)
print(result)
```

## Example Response *(as JSON)*

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

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 400 | The body is not valid JSON. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 401 | Missing or wrong X-Konbit-Key. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 413 | The body is larger than 50 KB. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 422 | The evidence was rejected (for example, too short) or could not be reviewed. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 429 | Rate limited (20 events per hour per workspace). | [`ApiErrorException`](../../doc/models/api-error-exception.md) |


# Get Work Event

Returns the agent's review of an event submitted earlier, with the current status of every payout plan it created.

```python
def get_work_event(self,
                  event_id)
```

## Authentication

This endpoint requires [konbitKey](../../doc/auth/custom-header-signature.md)

## Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `event_id` | `int` | Query, Required | The `event_id` returned by submitWorkEvent.<br><br>**Constraints**: `>= 1` |

## Response Type

**200**: The event and its plans.

[`WorkEventResult`](../../doc/models/work-event-result.md)

## Example Usage

```python
event_id = 12

result = work_events_controller.get_work_event(event_id)
print(result)
```

## Example Response *(as JSON)*

```json
{
  "event_id": 12,
  "status": "done",
  "risk": "low",
  "summary": "PR #42 was merged and the client confirmed the landing page is live.",
  "plans": [
    {
      "plan_id": 7,
      "title": "Jean Pierre · Landing page live",
      "total_cents": 25000,
      "decision": "ask",
      "status": "sent"
    }
  ]
}
```

## Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 401 | Missing or wrong X-Konbit-Key. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |
| 404 | No such event in this workspace. | [`ApiErrorException`](../../doc/models/api-error-exception.md) |

