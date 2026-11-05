
# Plan Status Enum

The payout plan's current status.

## Enumeration

`PlanStatusEnum`

## Fields

| Name |
|  --- |
| `NEEDS_APPROVAL` |
| `BLOCKED` |
| `SCHEDULED` |
| `SENDING` |
| `SENT` |
| `PAID` |
| `PARTIAL` |
| `FAILED` |
| `REJECTED` |

## Example

```python
from konbitworkeventsapi.models.plan_status_enum import PlanStatusEnum

plan_status = PlanStatusEnum.NEEDS_APPROVAL
```

