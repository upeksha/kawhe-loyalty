# QR code and manual scan code improvements

This doc describes how QR codes and manual scan codes were simplified for **faster scanning** and **shorter manual codes**, without breaking existing behaviour.

## What changed

### 1. Shorter tokens (new accounts only)

- **Public token** (stamp QR, card URL): new accounts now get a **16-character** token instead of 40.
- **Redeem token** (redeem QR): new redeem tokens are also **16 characters**.

**Effects:**

- **QR codes** encode less data (`LA:xxxxxxxxxxxxxxxx` or `LR:xxxxxxxxxxxxxxxx` instead of 43 chars), so the QR is **less dense** and typically **scans faster**.
- **Manual scan code** (e.g. on wallet passes) is shorter: 16 chars → displayed as `xxxx-xxxx-xxxx-xxxx` (4 groups) instead of 10 groups for 40 chars.
- **Card URLs** are shorter: `/c/xxxxxxxxxxxxxxxx` instead of `/c/` + 40 chars.

**Backward compatibility:** Existing accounts keep their 40-character tokens. The scanner and all lookups use exact string match; both 16- and 40-character tokens work. No migration or change to existing data.

### 2. QR generation (stamp and redeem on card page)

- **Error correction** set to **L** (low, ~7%): simpler QR pattern, faster to scan in good conditions.
- **Margin** set to **1**: minimal quiet zone, slightly smaller overall code.

Applied in:

- `resources/views/card/show.blade.php` for both stamp QR (`LA:...`) and redeem QR (`LR:...`).

Wallet passes (Apple/Google) still use the same payload format (`LA:` / `LR:` + token); with shorter tokens the barcode content is shorter and the pass QR is simpler without any change in wallet service logic.

## What was not changed

- **Store join QR** (merchant “Scan to join”): still encodes the full join URL; unchanged.
- **Email verification tokens**: still 40 characters (used in links only, not in QR).
- **Wallet auth token**: still 40 characters (security-sensitive, not user-facing).
- **Scanner and API**: no assumptions on token length; they accept any token string and look up by `public_token` or `redeem_token`. All existing flows (stamp, redeem, preview, manual entry) work for both old and new token lengths.

## Configuration

Token length is defined on the model:

- `App\Models\LoyaltyAccount::PUBLIC_TOKEN_LENGTH` (default **16**)
- `App\Models\LoyaltyAccount::REDEEM_TOKEN_LENGTH` (default **16**)

Changing these only affects **new** tokens (new accounts or new redeem tokens). Existing tokens are never modified.

## Summary

| Item              | Before (new)     | After (new)        | Existing data   |
|-------------------|------------------|--------------------|-----------------|
| Stamp QR payload  | `LA:` + 40 chars | `LA:` + 16 chars   | Unchanged       |
| Redeem QR payload | `LR:` + 40 chars | `LR:` + 16 chars   | Unchanged       |
| Manual code       | 10×4-char groups| 4×4-char groups    | Unchanged       |
| QR error correction | Default (often M/H) | L (low)         | N/A (generation) |
| QR margin         | Default          | 1                  | N/A (generation) |

Result: **simpler, faster-to-scan QR codes** and **shorter manual codes** for new cards, with **no breaking changes** for existing cards or flows.
