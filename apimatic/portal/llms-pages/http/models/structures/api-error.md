# Api Error

Source: /#/http/x-redirect/JTI0bSUyRkFwaUVycm9y

An error response.


# Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `error` | `String` | Required | A short machine-readable error code. |
| `message` | `String` | Optional | A human-readable explanation, when available. |


# Example (as JSON)

```json
{
  "error": "unauthorized",
  "message": "Send your workspace key in the X-Konbit-Key header."
}
```



