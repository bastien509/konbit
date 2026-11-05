# Get Work Event

Source: /#/typescript/x-redirect/JTI0ZSUyRldvcmslMjUyMGV2ZW50cyUyRmdldFdvcmtFdmVudA

Returns the agent's review of an event submitted earlier, with the current status of every payout plan it created.

```ts
async getWorkEvent(
  eventId: number,
  requestOptions?: RequestOptions
): Promise<ApiResponse<WorkEventResult>>
```


# Authentication

This endpoint requires [konbitKey]($h/__auth_CustomHeaderAuthentication)


# Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `eventId` | `number` | Query, Required | The `event_id` returned by submitWorkEvent.<br><br>**Constraints**: `>= 1` |
| `requestOptions` | `RequestOptions \| undefined` | Optional | Pass additional request options. |


# Response Type

**200**: The event and its plans.

This method returns an `ApiResponse` instance. The `result` property of this instance returns the response data which is of type [`WorkEventResult`](/llms-pages/typescript/models/structures/work-event-result.md).


# Example Usage

```ts
const eventId = 12;

try {
  const response = await workEventsController.getWorkEvent(eventId);

  // Extracting fully parsed response body.
  console.log(response.result);

  // Extracting response status code.
  console.log(response.statusCode);
  // Extracting response headers.
  console.log(response.headers);
  // Extracting response body of type `string | Stream`
  console.log(response.body);
} catch (error) {
  if (error instanceof ApiError) {
    // Extracting response error status code.
    console.log(error.statusCode);
    // Extracting response error headers.
    console.log(error.headers);
    // Extracting response error body of type `string | Stream`.
    console.log(error.body);
    if (error instanceof ExternalApiError) {
      console.log(error.result);
    }
  }
}
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
| 401 | Missing or wrong X-Konbit-Key. | [`ExternalApiError`](/llms-pages/typescript/models/exceptions/api-error.md) |
| 404 | No such event in this workspace. | [`ExternalApiError`](/llms-pages/typescript/models/exceptions/api-error.md) |



