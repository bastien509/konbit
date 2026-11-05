# Work Event Result

Source: /#/typescript/x-redirect/JTI0bSUyRldvcmtFdmVudFJlc3VsdA

The agent's review of one piece of evidence.


# Interface Name

`WorkEventResult`


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `eventId` | `number` | Required | Event identifier. |
| `status` | [`EventStatusEnum`](/llms-pages/typescript/models/enumerations/event-status.md) | Required | Review result: done = payout proposed, ignored = no action, flagged = suspicious. |
| `risk` | [`RiskLevelEnum \| undefined`](/llms-pages/typescript/models/enumerations/risk-level.md) | Optional | How risky the agent and the risk scan judged the evidence. |
| `summary` | `string \| null \| undefined` | Optional | The agent's one or two sentence summary. |
| `plans` | [`PayoutPlan[] \| undefined`](/llms-pages/typescript/models/structures/payout-plan.md) | Optional | Payout plans created from this evidence. |


# Example

```ts
import {
  EventStatusEnum,
  RiskLevelEnum,
  WorkEventResult,
} from 'konbit-work-events-apilib';

const workEventResult: WorkEventResult = {
  eventId: 12,
  status: EventStatusEnum.Done,
  risk: RiskLevelEnum.Low,
  summary: 'PR #42 was merged and the client confirmed the landing page is live.',
  plans: [],
};
```



