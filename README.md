# TeraWallet (formerly WooWallet)

TeraWallet is the leading wallet system for WooCommerce, providing a seamless digital currency experience for WordPress sites. It allows customers to store funds, earn rewards through various site activities, and use their balance for fast, secure checkouts.

## 🚀 Key Features
- **Digital Ledger**: Secure transaction history for every user.
- **Flexible Top-ups**: Customers can add funds via any WooCommerce gateway.
- **Partial Payments**: Use wallet balance combined with other payment methods.
- **Cashback Engine**: Rewards based on Cart, Product, or Category rules.
- **Incentivized Actions**: Earn balance for Signups, Reviews, Referrals, and Daily Visits.
- **Peer-to-Peer Transfers**: Users can send balance to other registered customers.
- **Marketplace Ready**: Full compatibility with Dokan, WCFM, and WCMarketplace.

## 🛠 Developer Section

### Core Logic & Hooks
The system uses a strict database-first approach with MySQL-level locking to ensure transaction integrity. 

- **Filters**: 
    - `woo_wallet_current_balance`: Modify balance display.
    - `woo_wallet_payment_is_available`: Programmatically toggle the wallet gateway.
    - `woo_wallet_cashback_amount`: Adjust calculated rewards.
- **Actions**:
    - `woo_wallet_transaction_recorded`: Fires after any ledger update.
    - `woo_wallet_payment_processed`: Fires after a successful wallet purchase.
    - `woo_wallet_admin_adjust_balance`: Fires when admins manually edit balance.

### REST API (v3)
TeraWallet extends the WooCommerce REST API under the `wc/v3/wallet` namespace.
- `GET /balance?email={email}`: Retrieve user balance.
- `GET /?email={email}`: Retrieve transaction list.
- `POST /`: Create credit/debit transactions (Admin only).

## 📂 File Structure
- `includes/class-woo-wallet-wallet.php`: Core ledger and balance logic.
- `includes/class-woo-wallet-frontend.php`: Shortcodes and checkout integration.
- `includes/actions/`: Logic for earning balance via site activities.
- `templates/`: UI components (Overridable via theme).

## 📄 License
This project is licensed under the terms found in the `LICENSE` file.
