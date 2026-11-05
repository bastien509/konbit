# Plan Status

Source: /#/php/x-redirect/JTI0bSUyRlBsYW5TdGF0dXM

The payout plan's current status.


# Enum Type Name

`PlanStatusEnum`


# Fields

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


# Example

```php
use KonbitWorkEventsAPILib\Models\PlanStatusEnum;

$planStatus = PlanStatusEnum::SCHEDULED;
```



