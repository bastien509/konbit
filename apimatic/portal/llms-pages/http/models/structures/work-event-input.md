# Work Event Input

Source: /#/http/x-redirect/JTI0bSUyRldvcmtFdmVudElucHV0

Work evidence to send to the agent.


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `source` | [`Evidence Source`](/llms-pages/http/models/enumerations/evidence-source.md) | Optional | Where the evidence came from.<br><br>**Default**: `api` |
| `title` | `String` | Optional | A short title, such as an email subject.<br><br>**Constraints**: *Maximum Length*: `160` |
| `text` | `String` | Required | The raw evidence. It is treated as untrusted data.<br><br>**Constraints**: *Minimum Length*: `10`, *Maximum Length*: `6000` |


# Example (as JSON)

```json
{
  "source": "github",
  "title": "Pull request #42 merged",
  "text": "PR #42 \"Landing page\" was merged and deployed."
}
```



