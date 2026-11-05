
# Plan Status Enum

The payout plan's current status.

## Enumeration

`PlanStatusEnum`

## Fields

| Name |
|  --- |
| `NeedsApproval` |
| `Blocked` |
| `Scheduled` |
| `Sending` |
| `Sent` |
| `Paid` |
| `Partial` |
| `Failed` |
| `Rejected` |

## Example

```ts
import { PlanStatusEnum } from 'konbit-work-events-apilib';

const planStatus = PlanStatusEnum.Scheduled;
```

