
# Event Status Enum

Review result: done = payout proposed, ignored = no action, flagged = suspicious.

## Enumeration

`EventStatusEnum`

## Fields

| Name |
|  --- |
| `NEW_` |
| `ANALYZING` |
| `DONE` |
| `IGNORED` |
| `FLAGGED` |
| `ERROR` |

## Example

```php
use KonbitWorkEventsAPILib\Models\EventStatusEnum;

$eventStatus = EventStatusEnum::FLAGGED;
```

