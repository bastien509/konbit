# Submit Work Event

Source: /#/typescript/x-redirect/JTI0ZSUyRldvcmslMjUyMGV2ZW50cyUyRnN1Ym1pdFdvcmtFdmVudA

Stores the evidence, asks the Konbit agent (Claude) to review it, and returns the
review together with any payout plans it created. A plan's `decision` tells you
what the signed mandate allowed: `auto` (paid with PayPal Payouts), `ask`
(waiting for the freelancer's approval) or `block` (refused by policy).

```ts
async submitWorkEvent(
  body: WorkEventInput,
  requestOptions?: RequestOptions
): Promise<ApiResponse<WorkEventResult>>
```


# Authentication

This endpoint requires [konbitKey]($h/__auth_CustomHeaderAuthentication)


# Parameters

| Parameter | Type | Tags | Description |
|  --- | --- | --- | --- |
| `body` | [`WorkEventInput`](/llms-pages/typescript/models/structures/work-event-input.md) | Body, Required | The evidence to review. Only `text` is required. |
| `requestOptions` | `RequestOptions \| undefined` | Optional | Pass additional request options. |


# Response Type

**201**: The agent reviewed the evidence.

This method returns an `ApiResponse` instance. The `result` property of this instance returns the response data which is of type [`WorkEventResult`](/llms-pages/typescript/models/structures/work-event-result.md).


# Example Usage

```ts
const body: WorkEventInput = {
  text: 'PR #42 "Landing page" was merged and deployed. Client: Landing page is live and looks great, thank you Jean!',
  source: EvidenceSourceEnum.Github,
  title: 'Pull request #42 merged',
};

try {
  const response = await workEventsController.submitWorkEvent(body);

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
| 400 | The body is not valid JSON. | [`ExternalApiError`](/llms-pages/typescript/models/exceptions/api-error.md) |
| 401 | Missing or wrong X-Konbit-Key. | [`ExternalApiError`](/llms-pages/typescript/models/exceptions/api-error.md) |
| 413 | The body is larger than 50 KB. | [`ExternalApiError`](/llms-pages/typescript/models/exceptions/api-error.md) |
| 422 | The evidence was rejected (for example, too short) or could not be reviewed. | [`ExternalApiError`](/llms-pages/typescript/models/exceptions/api-error.md) |
| 429 | Rate limited (20 events per hour per workspace). | [`ExternalApiError`](/llms-pages/typescript/models/exceptions/api-error.md) |



