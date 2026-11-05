# Get Work Event

Source: /#/http/x-redirect/JTI0ZSUyRldvcmslMjUyMGV2ZW50cyUyRmdldFdvcmtFdmVudA

Returns the agent's review of an event submitted earlier, with the current status of every payout plan it created.

```http
GET /hook.php
```


# Authentication

This endpoint requires [konbitKey]($h/__auth_CustomHeaderAuthentication)


# Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `event_id` | `Number` | Query, Required | The `event_id` returned by submitWorkEvent.<br><br>**Constraints**: `>= 1` |


# Response Type

**200**: The event and its plans.

[`Work Event Result`](/llms-pages/http/models/structures/work-event-result.md)


# Example Usage

```bash
curl -X GET -G \
  --url 'https://petiht.com/freelanceragent/hook.php'  \
  -H 'Accept: application/json' \
  -H 'X-Konbit-Key: X-Konbit-Key' \
  -d 'event_id=12'
```


# Example Response *(as JSON)*

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


# Errors

| HTTP Status Code | Error Description | Exception Class |
|  --- | --- | --- |
| 401 | Missing or wrong X-Konbit-Key. | [`ApiError_ErrorException`](/llms-pages/http/models/exceptions/api-error-error.md) |
| 404 | No such event in this workspace. | [`ApiError_ErrorException`](/llms-pages/http/models/exceptions/api-error-error.md) |



