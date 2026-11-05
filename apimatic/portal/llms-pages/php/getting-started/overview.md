# Overview

Source: /#/php/x-redirect/cGFnZSUzQW92ZXJ2aWV3

Send proof of work (a merged pull request, a client's approval email, a finished calendar session) to your Konbit workspace. Claude reviews it, matches it to an open milestone and proposes a PayPal payout. Your signed mandate then decides: **auto** (paid with PayPal Payouts), **ask** (waits for your approval) or **block**.

Evidence is treated as untrusted text. It can never add a payee or raise a limit.


# Authentication

Send your workspace key in the `X-Konbit-Key` header. Create it in Konbit under **Mandate → Work-event webhook**. It is shown once.


# Try it

The demo runs on the PayPal sandbox: no real money moves. Sign in at [petiht.com/freelanceragent](https://petiht.com/freelanceragent/) with `demo` / `Demo509`.



