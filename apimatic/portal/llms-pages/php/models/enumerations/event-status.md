# Event Status

Source: /#/php/x-redirect/JTI0bSUyRkV2ZW50U3RhdHVz

Review result: done = payout proposed, ignored = no action, flagged = suspicious.


# Enum Type Name

`EventStatusEnum`


# Fields

| Name |
|  --- |
| `NEW_` |
| `ANALYZING` |
| `DONE` |
| `IGNORED` |
| `FLAGGED` |
| `ERROR` |


# Example

```php
use KonbitWorkEventsAPILib\Models\EventStatusEnum;

$eventStatus = EventStatusEnum::FLAGGED;
```



