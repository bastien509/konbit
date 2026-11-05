
# Custom Header Signature



Documentation for accessing and setting credentials for konbitKey.

## Auth Credentials

| Name | Type | Description | Getter |
|  --- | --- | --- | --- |
| X-Konbit-Key | `str` | Workspace key from Mandate → Work-event webhook. It is shown once when created. | `x_konbit_key` |



**Note:** Auth credentials can be set using `CustomHeaderAuthenticationCredentials` object, passed in as named parameter `custom_header_authentication_credentials` in the client initialization.

## Usage Example

### Client Initialization

You must provide credentials in the client as shown in the following code snippet.

```python
from konbitworkeventsapi.http.auth.custom_header_authentication import CustomHeaderAuthenticationCredentials
from konbitworkeventsapi.konbitworkeventsapi_client import KonbitworkeventsapiClient

client = KonbitworkeventsapiClient(
    custom_header_authentication_credentials=CustomHeaderAuthenticationCredentials(
        x_konbit_key='X-Konbit-Key'
    )
)
```


