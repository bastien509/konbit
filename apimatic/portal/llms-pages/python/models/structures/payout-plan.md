# Payout Plan

Source: /#/python/x-redirect/JTI0bSUyRlBheW91dFBsYW4

A proposed or completed PayPal payout.


# Class Name

`PayoutPlan`


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `plan_id` | `int` | Required | Plan identifier. |
| `title` | `str` | Required | Who is paid and for what. |
| `total_cents` | `int` | Required | Total in US cents. |
| `decision` | [`PlanDecisionEnum`](/llms-pages/python/models/enumerations/plan-decision.md) | Required | What the signed mandate decided for a payout plan. |
| `status` | [`PlanStatusEnum`](/llms-pages/python/models/enumerations/plan-status.md) | Required | The payout plan's current status. |


# Example

```python
from konbitworkeventsapi.models.payout_plan import PayoutPlan
from konbitworkeventsapi.models.plan_decision_enum import PlanDecisionEnum
from konbitworkeventsapi.models.plan_status_enum import PlanStatusEnum

payout_plan = PayoutPlan(
    plan_id=7,
    title='Jean Pierre · Landing page live',
    total_cents=25000,
    decision=PlanDecisionEnum.ASK,
    status=PlanStatusEnum.NEEDS_APPROVAL
)
```



