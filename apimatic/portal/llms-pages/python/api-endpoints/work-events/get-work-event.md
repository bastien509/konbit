# Get Work Event

Source: /#/python/x-redirect/JTI0ZSUyRldvcmslMjUyMGV2ZW50cyUyRmdldFdvcmtFdmVudA

Returns the agent's review of an event submitted earlier, with the current status of every payout plan it created.

```python
def get_work_event(self,
                  event_id)
```


# Authentication

This endpoint requires [konbitKey]($h/__auth_CustomHeaderAuthentication)


# Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `event_id` | `int` | Query, Required | The `event_id` returned by submitWorkEvent.<br><br>**Constraints**: `>= 1` |


# Response Type

**200**: The event and its plans.

[`WorkEventResult`](/llms-pages/python/models/structures/work-event-result.md)


# Example Usage

```python
event_id = 12

result = work_events_controller.get_work_event(event_id)
print(result)
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
| 401 | Missing or wrong X-Konbit-Key. | [`ApiErrorException`](/llms-pages/python/models/exceptions/api-error.md) |
| 404 | No such event in this workspace. | [`ApiErrorException`](/llms-pages/python/models/exceptions/api-error.md) |



