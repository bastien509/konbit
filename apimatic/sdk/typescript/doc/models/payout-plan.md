
# Payout Plan

A proposed or completed PayPal payout.

## Structure

`PayoutPlan`

## Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `planId` | `number` | Required | Plan identifier. |
| `title` | `string` | Required | Who is paid and for what. |
| `totalCents` | `number` | Required | Total in US cents. |
| `decision` | [`PlanDecisionEnum`](../../doc/models/plan-decision-enum.md) | Required | What the signed mandate decided for a payout plan. |
| `status` | [`PlanStatusEnum`](../../doc/models/plan-status-enum.md) | Required | The payout plan's current status. |

## Example

```ts
import {
  PayoutPlan,
  PlanDecisionEnum,
  PlanStatusEnum,
} from 'konbit-work-events-apilib';

const payoutPlan: PayoutPlan = {
  planId: 7,
  title: 'Jean Pierre · Landing page live',
  totalCents: 25000,
  decision: PlanDecisionEnum.Ask,
  status: PlanStatusEnum.NeedsApproval,
};
```

