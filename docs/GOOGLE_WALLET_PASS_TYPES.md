# Google Wallet: Loyalty vs Generic Pass Types

The app can issue Google Wallet passes in two styles, controlled by config.

## Pass types

| Type      | API used              | Style / use case |
|----------|------------------------|------------------|
| **Loyalty** | Loyalty Class / Loyalty Object | Dedicated loyalty card layout (points, secondary points, program name). |
| **Generic** | Generic Class / Generic Object | [Pass builder](https://developers.google.com/wallet/generic/resources/pass-builder)–style cards: card title, header, hero image, logo, hex background, text modules, barcode. |

Both types show stamps (progress), customer name, rewards when applicable, and the same barcode (LA:/LR:) for scanning.

## Switching to Generic (pass-builder style)

To use **Generic** (pass-builder–style) cards:

1. In `.env` set:
   ```env
   GOOGLE_WALLET_PASS_TYPE=generic
   ```
2. Ensure `config/services.php` reads it (it already does under `google_wallet.pass_type`; default is `loyalty`).

When `GOOGLE_WALLET_PASS_TYPE=generic`:

- **Save to Wallet**: The “Add to Google Wallet” flow creates/updates a **Generic** object and the JWT uses `genericObjects` (not `loyaltyObjects`).
- **Sync**: After stamp/reward changes, the app updates the **Generic** object (same as the one used for the save link).

No code changes are required beyond this env setting.

## References

- [Generic pass](https://developers.google.com/wallet/generic) – overview
- [Generic pass builder](https://developers.google.com/wallet/generic/resources/pass-builder) – layout and fields
- [Create Generic object](https://developers.google.com/wallet/generic/use-cases/create) – API usage
