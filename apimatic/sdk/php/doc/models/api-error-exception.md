
# Api Error Exception

An error response.

## Structure

`ApiErrorException`

## Fields

| Name | Type | Tags | Description | Getter | Setter |
|  --- | --- | --- | --- | --- | --- |
| `error` | `string` | Required | A short machine-readable error code. | getError(): string | setError(string error): void |
| `message` | `?string` | Optional | A human-readable explanation, when available. | getMessage(): ?string | setMessage(?string message): void |

## Example

```php
try {
    // make the API call
} catch (ApiErrorException $exp) {
    echo 'Caught ApiErrorException:', $exp;
} catch (ApiException $exp) {
    echo 'Caught ApiException:', $exp;
}
```

