
# Custom Header Signature



Documentation for accessing and setting credentials for konbitKey.

## Auth Credentials

| Name | Type | Description | Setter |
|  --- | --- | --- | --- |
| X-Konbit-Key | `string` | Workspace key from Mandate → Work-event webhook. It is shown once when created. | `xKonbitKey` |



**Note:** Auth credentials can be set using `customHeaderAuthenticationCredentials` object in the client.

## Usage Example

### Client Initialization

You must provide credentials in the client as shown in the following code snippet.

```ts
import { Client } from 'konbit-work-events-apilib';

const client = new Client({
  customHeaderAuthenticationCredentials: {
    'X-Konbit-Key': 'X-Konbit-Key'
  },
});
```


