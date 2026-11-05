# Work Event Result

Source: /#/python/x-redirect/JTI0bSUyRldvcmtFdmVudFJlc3VsdA

The agent's review of one piece of evidence.


# Class Name

`WorkEventResult`


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `event_id` | `int` | Required | Event identifier. |
| `status` | [`EventStatusEnum`](/llms-pages/python/models/enumerations/event-status.md) | Required | Review result: done = payout proposed, ignored = no action, flagged = suspicious. |
| `risk` | [`RiskLevelEnum`](/llms-pages/python/models/enumerations/risk-level.md) | Optional | How risky the agent and the risk scan judged the evidence. |
| `summary` | `str` | Optional | The agent's one or two sentence summary. |
| `plans` | [`List[PayoutPlan]`](/llms-pages/python/models/structures/payout-plan.md) | Optional | Payout plans created from this evidence. |


# Example

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



