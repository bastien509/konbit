
# Custom Header Signature



Documentation for accessing and setting credentials for konbitKey.

## Auth Credentials

| Name | Type | Description | Setter | Getter |
|  --- | --- | --- | --- | --- |
| X-Konbit-Key | `string` | Workspace key from Mandate → Work-event webhook. It is shown once when created. | `xKonbitKey` | `getXKonbitKey()` |



**Note:** Auth credentials can be set using `CustomHeaderAuthenticationCredentialsBuilder::init()` in `customHeaderAuthenticationCredentials` method in the client builder and accessed through `getCustomHeaderAuthenticationCredentials` method in the client instance.

## Usage Example

### Client Initialization

You must provide credentials in the client as shown in the following code snippet.

```php
use KonbitWorkEventsAPILib\Authentication\CustomHeaderAuthenticationCredentialsBuilder;
use KonbitWorkEventsAPILib\KonbitWorkEventsAPIClientBuilder;

$client = KonbitWorkEventsAPIClientBuilder::init()
    ->customHeaderAuthenticationCredentials(
        CustomHeaderAuthenticationCredentialsBuilder::init(
            'X-Konbit-Key'
        )
    )
    ->build();
```


