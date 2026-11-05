# Payout Plan

Source: /#/http/x-redirect/JTI0bSUyRlBheW91dFBsYW4

A proposed or completed PayPal payout.


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `plan_id` | `Number` | Required | Plan identifier. |
| `title` | `String` | Required | Who is paid and for what. |
| `total_cents` | `Number` | Required | Total in US cents. |
| `decision` | [`Plan Decision`](/llms-pages/http/models/enumerations/plan-decision.md) | Required | What the signed mandate decided for a payout plan. |
| `status` | [`Plan Status`](/llms-pages/http/models/enumerations/plan-status.md) | Required | The payout plan's current status. |


# Example (as JSON)

```json
{
  "plan_id": 7,
  "title": "Jean Pierre · Landing page live",
  "total_cents": 25000,
  "decision": "ask",
  "status": "needs_approval"
}
```



