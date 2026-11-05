# Work Event Input

Source: /#/typescript/x-redirect/JTI0bSUyRldvcmtFdmVudElucHV0

Work evidence to send to the agent.


# Interface Name

`WorkEventInput`


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `source` | [`EvidenceSourceEnum \| undefined`](/llms-pages/typescript/models/enumerations/evidence-source.md) | Optional | Where the evidence came from.<br><br>**Default**: `EvidenceSourceEnum.Api` |
| `title` | `string \| undefined` | Optional | A short title, such as an email subject.<br><br>**Constraints**: *Maximum Length*: `160` |
| `text` | `string` | Required | The raw evidence. It is treated as untrusted data.<br><br>**Constraints**: *Minimum Length*: `10`, *Maximum Length*: `6000` |


# Example

```ts
import {
  EvidenceSourceEnum,
  WorkEventInput,
} from 'konbit-work-events-apilib';

const workEventInput: WorkEventInput = {
  text: 'PR #42 "Landing page" was merged and deployed.',
  source: EvidenceSourceEnum.Github,
  title: 'Pull request #42 merged',
};
```



