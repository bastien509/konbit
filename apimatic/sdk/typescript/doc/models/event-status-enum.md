
# Event Status Enum

Review result: done = payout proposed, ignored = no action, flagged = suspicious.

## Enumeration

`EventStatusEnum`

## Fields

| Name |
|  --- |
| `New` |
| `Analyzing` |
| `Done` |
| `Ignored` |
| `Flagged` |
| `Error` |

## Example

```ts
import { EventStatusEnum } from 'konbit-work-events-apilib';

const eventStatus = EventStatusEnum.Flagged;
```

