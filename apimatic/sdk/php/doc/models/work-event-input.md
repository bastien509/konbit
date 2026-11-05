
# Work Event Input

Work evidence to send to the agent.

## Structure

`WorkEventInput`

## Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `source` | [`?string(EvidenceSourceEnum)`](../../doc/models/evidence-source-enum.md) | Optional | Where the evidence came from.<br><br>**Default**: `EvidenceSourceEnum::API` | getSource(): ?string | setSource(?string source): void |
| `title` | `?string` | Optional | A short title, such as an email subject.<br><br>**Constraints**: *Maximum Length*: `160` | getTitle(): ?string | setTitle(?string title): void |
| `text` | `string` | Required | The raw evidence. It is treated as untrusted data.<br><br>**Constraints**: *Minimum Length*: `10`, *Maximum Length*: `6000` | getText(): string | setText(string text): void |

## Example

```php
use KonbitWorkEventsAPILib\Models\Builders\WorkEventInputBuilder;
use KonbitWorkEventsAPILib\Models\EvidenceSourceEnum;

$workEventInput = WorkEventInputBuilder::init(
    'PR #42 "Landing page" was merged and deployed.'
)
    ->source(EvidenceSourceEnum::GITHUB)
    ->title('Pull request #42 merged')
    ->build();
```

