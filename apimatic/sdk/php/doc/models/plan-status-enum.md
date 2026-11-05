
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

```php
use KonbitWorkEventsAPILib\Models\PlanStatusEnum;

$planStatus = PlanStatusEnum::SCHEDULED;
```

