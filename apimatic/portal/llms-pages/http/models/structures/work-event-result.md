# Work Event Result

Source: /#/http/x-redirect/JTI0bSUyRldvcmtFdmVudFJlc3VsdA

The agent's review of one piece of evidence.


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `event_id` | `Number` | Required | Event identifier. |
| `status` | [`Event Status`](/llms-pages/http/models/enumerations/event-status.md) | Required | Review result: done = payout proposed, ignored = no action, flagged = suspicious. |
| `risk` | [`Risk Level`](/llms-pages/http/models/enumerations/risk-level.md) | Optional | How risky the agent and the risk scan judged the evidence. |
| `summary` | `String` | Optional | The agent's one or two sentence summary. |
| `plans` | [`array<Payout Plan>`](/llms-pages/http/models/structures/payout-plan.md) | Optional | Payout plans created from this evidence. |


# Example (as JSON)

```json
{
  "event_id": 12,
  "status": "done",
  "risk": "low",
  "summary": "PR #42 was merged and the client confirmed the landing page is live.",
  "plans": []
}
```



