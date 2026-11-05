# Work Event Input

Source: /#/python/x-redirect/JTI0bSUyRldvcmtFdmVudElucHV0

Work evidence to send to the agent.


# Class Name

`WorkEventInput`


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `source` | [`EvidenceSourceEnum`](/llms-pages/python/models/enumerations/evidence-source.md) | Optional | Where the evidence came from.<br><br>**Default**: `"api"` |
| `title` | `str` | Optional | A short title, such as an email subject.<br><br>**Constraints**: *Maximum Length*: `160` |
| `text` | `str` | Required | The raw evidence. It is treated as untrusted data.<br><br>**Constraints**: *Minimum Length*: `10`, *Maximum Length*: `6000` |


# Example

```python
from konbitworkeventsapi.models.evidence_source_enum import EvidenceSourceEnum
from konbitworkeventsapi.models.work_event_input import WorkEventInput

work_event_input = WorkEventInput(
    text='PR #42 "Landing page" was merged and deployed.',
    source=EvidenceSourceEnum.GITHUB,
    title='Pull request #42 merged'
)
```



