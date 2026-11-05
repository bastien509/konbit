
# Api Error Exception

An error response.

## Structure

`ApiErrorException`

## Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `error` | `str` | Required | A short machine-readable error code. |
| `message` | `str` | Optional | A human-readable explanation, when available. |

## Example

```python
try:
    # make the API call
except ApiErrorException as e:
    print(e)
except APIException as e:
    print(e)
```

