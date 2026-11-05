
# Work Event Result

The agent's review of one piece of evidence.

## Structure

`WorkEventResult`

## Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `event_id` | `int` | Required | Event identifier. |
| `status` | [`EventStatusEnum`](../../doc/models/event-status-enum.md) | Required | Review result: done = payout proposed, ignored = no action, flagged = suspicious. |
| `risk` | [`RiskLevelEnum`](../../doc/models/risk-level-enum.md) | Optional | How risky the agent and the risk scan judged the evidence. |
| `summary` | `str` | Optional | The agent's one or two sentence summary. |
| `plans` | [`List[PayoutPlan]`](../../doc/models/payout-plan.md) | Optional | Payout plans created from this evidence. |

## Example

```python
from konbitworkeventsapi.models.event_status_enum import EventStatusEnum
from konbitworkeventsapi.models.risk_level_enum import RiskLevelEnum
from konbitworkeventsapi.models.work_event_result import WorkEventResult

work_event_result = WorkEventResult(
    event_id=12,
    status=EventStatusEnum.DONE,
    risk=RiskLevelEnum.LOW,
    summary='PR #42 was merged and the client confirmed the landing page is live.',
    plans=[]
)
```

