# Part A – Code Review of `transfer()`

| # | Category | Problem | Consequence | Fix in this project |
|---|---|---|---|---|
| 1 | Correctness | Money is a JSON float (`10.10`), stored and compared as float. | Binary rounding errors (`0.1+0.2`), cents appear or vanish. | Integer minor units + `Money` value object; float amounts fail validation. |
| 2 | Security | No input validation. `from_id`, `to_id`, `amount` are used raw. | Negative/zero/string amounts. A negative amount steals money from the recipient. | `TransferRequest`: `integer`, `min:1`, upper bound, `exists`, currency regex. |
| 3 | Security (IDOR) | No authorization. Any caller can pass any `from_id`. | Anyone can drain anyone's wallet. | `WalletsPolicy::transfer`, Sanctum ability `wallet:transfer`. |
| 4 | Correctness | `Wallet::find()` can return `null`. | `null->balance` → 500 error instead of 404/422. | `exists` validation + `findOrFail`. |
| 5 | Concurrency | Read-check-write without locks (TOCTOU). Two parallel requests both pass `balance >= amount`. | Overdraft; lost updates because both write stale balances. | Transaction + `SELECT … FOR UPDATE` on the locked row, then check. |
| 6 | Integrity | No DB transaction. `$from->save()` then `$to->save()` are independent. | A crash between saves destroys money. | One `DB::transaction`; everything commits or nothing does. |
| 7 | Correctness | `from == to` is not rejected. Two stale copies of one row are saved. | The second save overwrites the first, so money is created out of nothing. | `different:from_wallet_id` rule and a check in the action. |
| 8 | Reliability | No idempotency. | A client retry after a timeout transfers twice. | `Idempotency-Key` with a unique index and request hash. |
| 9 | Accounting | Mutable balance column plus a single `Transaction` row. No double entry, no immutability, no reversal path. | Cannot audit, reconcile or correct errors. | Double-entry ledger, immutable entries (model + MySQL trigger), reversals. |
| 10 | Reliability | `Mail::send` runs synchronously inside the request, in the money path. | A mail failure returns 500 after money moved, the client retries and money moves twice. A rollback would leave a false email. | Queue the notification only after commit. |
| 11 | Architecture | Business logic in the controller; no Form Request, Action, Policy. | Untestable, not reusable, no separation of concerns. | Thin controller → `TransferMoney` action → `LedgerService`. |
| 12 | API design | `insufficient` returned as 400 with an ad-hoc body. | Wrong status, inconsistent errors, no machine-readable code. | 422 with `{error:{code,message,details}}`. |
| 13 | Correctness | Currency is ignored. | Wallets in different currencies can be mixed. | Currency on wallet, account and entry; mismatch rejected. |
| 14 | Concurrency | No lock ordering (once locks are added). | Opposite transfers A→B and B→A deadlock. | Lock rows in ascending id order; bounded deadlock retry. |
