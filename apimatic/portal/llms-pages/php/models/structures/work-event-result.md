# Work Event Result

Source: /#/php/x-redirect/JTI0bSUyRldvcmtFdmVudFJlc3VsdA

The agent's review of one piece of evidence.


# Class Name

`WorkEventResult`


# Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `eventId` | `int` | Required | Event identifier. | getEventId(): int | setEventId(int eventId): void |
| `status` | [`string(EventStatusEnum)`](/llms-pages/php/models/enumerations/event-status.md) | Required | Review result: done = payout proposed, ignored = no action, flagged = suspicious. | getStatus(): string | setStatus(string status): void |
| `risk` | [`?string(RiskLevelEnum)`](/llms-pages/php/models/enumerations/risk-level.md) | Optional | How risky the agent and the risk scan judged the evidence. | getRisk(): ?string | setRisk(?string risk): void |
| `summary` | `?string` | Optional | The agent's one or two sentence summary. | getSummary(): ?string | setSummary(?string summary): void |
| `plans` | [`?(PayoutPlan[])`](/llms-pages/php/models/structures/payout-plan.md) | Optional | Payout plans created from this evidence. | getPlans(): ?array | setPlans(?array plans): void |


# Example

```php
use KonbitWorkEventsAPILib\Models\Builders\WorkEventResultBuilder;
use KonbitWorkEventsAPILib\Models\EventStatusEnum;
use KonbitWorkEventsAPILib\Models\RiskLevelEnum;

$workEventResult = WorkEventResultBuilder::init(
    12,
    EventStatusEnum::DONE
)
    ->risk(RiskLevelEnum::LOW)
    ->summary('PR #42 was merged and the client confirmed the landing page is live.')
    ->plans(
        []
    )
    ->build();
```



