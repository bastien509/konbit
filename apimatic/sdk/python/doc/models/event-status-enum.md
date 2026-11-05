
# Event Status Enum

Review result: done = payout proposed, ignored = no action, flagged = suspicious.

## Enumeration

`EventStatusEnum`

## Fields

| Name |
|  --- |
| `NEW` |
| `ANALYZING` |
| `DONE` |
| `IGNORED` |
| `FLAGGED` |
| `ERROR` |

## Example

```python
from konbitworkeventsapi.models.event_status_enum import EventStatusEnum

event_status = EventStatusEnum.FLAGGED
```

