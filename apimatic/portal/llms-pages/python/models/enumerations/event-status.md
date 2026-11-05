# Event Status

Source: /#/python/x-redirect/JTI0bSUyRkV2ZW50U3RhdHVz

Review result: done = payout proposed, ignored = no action, flagged = suspicious.


# Enum Type Name

`EventStatusEnum`


# Fields

| Name |
|  --- |
| `NEW` |
| `ANALYZING` |
| `DONE` |
| `IGNORED` |
| `FLAGGED` |
| `ERROR` |


# Example

```python
from konbitworkeventsapi.models.event_status_enum import EventStatusEnum

event_status = EventStatusEnum.FLAGGED
```



