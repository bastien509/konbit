
# Work Event Input

Work evidence to send to the agent.

## Structure

`WorkEventInput`

## Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `source` | [`EvidenceSourceEnum`](../../doc/models/evidence-source-enum.md) | Optional | Where the evidence came from.<br><br>**Default**: `"api"` |
| `title` | `str` | Optional | A short title, such as an email subject.<br><br>**Constraints**: *Maximum Length*: `160` |
| `text` | `str` | Required | The raw evidence. It is treated as untrusted data.<br><br>**Constraints**: *Minimum Length*: `10`, *Maximum Length*: `6000` |

## Example

```python
from konbitworkeventsapi.models.evidence_source_enum import EvidenceSourceEnum
from konbitworkeventsapi.models.work_event_input import WorkEventInput

work_event_input = WorkEventInput(
    text='PR #42 "Landing page" was merged and deployed.',
    source=EvidenceSourceEnum.GITHUB,
    title='Pull request #42 merged'
)
```

