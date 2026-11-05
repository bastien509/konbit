
# External Api Error

An error response.

## Structure

`ExternalApiError`

## Fields

| Name | Type | Tags | Description |
|  --- | --- | --- | --- |
| `error` | `string` | Required | A short machine-readable error code. |
| `message` | `string \| undefined` | Optional | A human-readable explanation, when available. |

## Example

```ts
try {
  // make the API call
} catch (error) {
  if (error instanceof ExternalApiError) {
    console.log(error.result);
  }
}
```

