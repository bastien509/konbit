
# Payout Plan

A proposed or completed PayPal payout.

## Structure

`PayoutPlan`

## Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `planId` | `int` | Required | Plan identifier. | getPlanId(): int | setPlanId(int planId): void |
| `title` | `string` | Required | Who is paid and for what. | getTitle(): string | setTitle(string title): void |
| `totalCents` | `int` | Required | Total in US cents. | getTotalCents(): int | setTotalCents(int totalCents): void |
| `decision` | [`string(PlanDecisionEnum)`](../../doc/models/plan-decision-enum.md) | Required | What the signed mandate decided for a payout plan. | getDecision(): string | setDecision(string decision): void |
| `status` | [`string(PlanStatusEnum)`](../../doc/models/plan-status-enum.md) | Required | The payout plan's current status. | getStatus(): string | setStatus(string status): void |

## Example

```php
use KonbitWorkEventsAPILib\Models\Builders\PayoutPlanBuilder;
use KonbitWorkEventsAPILib\Models\PlanDecisionEnum;
use KonbitWorkEventsAPILib\Models\PlanStatusEnum;

$payoutPlan = PayoutPlanBuilder::init(
    7,
    'Jean Pierre · Landing page live',
    25000,
    PlanDecisionEnum::ASK,
    PlanStatusEnum::NEEDS_APPROVAL
)->build();
```

