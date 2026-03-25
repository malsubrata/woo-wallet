# TeraWallet: Admin Documentation

## 1. Installation & Setup
1. **Requirements**: WordPress 6.4+, WooCommerce active.
2. **Steps**:
   - Upload `woo-wallet` to `/wp-content/plugins/`.
   - Activate via the WordPress Plugins menu.
   - Upon activation, a hidden **Wallet Topup** product is automatically created (do not delete).
3. **Menu**: Find the **TeraWallet** menu in your WordPress sidebar.

## 2. Admin Dashboard Overview
- **Users**: View all customers and their current balances.
- **Actions**: Configure rewards for registrations, visits, and reviews.
- **Transactions**: Central log for all wallet activities (credits/debits).
- **Settings**: Global configuration (limits, gateways, logic).
- **Export**: Generate CSV reports of transaction data.

## 3. Configuration Guide
### General Settings
- **Top-up Limits**: Set minimum and maximum recharge amounts.
- **Allowed Gateways**: Choose which gateways (e.g., Stripe, PayPal) allow users to add balance.
- **Partial Payment**: Allow users to split payments between wallet balance and other methods.
- **Transfer Fees**: Charge users for sending money to other users (Fixed or Percentage).

### Cashback & Rewards
- **Global Reward Rules**: Apply cashback based on the total cart, specific products, or product categories.
- **Status Triggers**: Select the WooCommerce order status (e.g., 'Completed') that triggers the reward.
- **Incentivized Actions**: Reward customers for:
    - New Account Registration.
    - Product Reviews.
    - Daily Visits.
    - Referrals.

## 4. Wallet Core Logic (Technical)
TeraWallet uses a double-entry ledger architecture for maximum reliability:
- **Database Tables**:
    - `{prefix}woo_wallet_transactions`: Every credit/debit is a discrete record.
    - `{prefix}woo_wallet_transaction_meta`: Extended data (Order IDs, notes).
- **Integrity**: Every transaction is protected by **MySQL Session-Level Locking** to prevent double-spending or race conditions.
- **Balance Verification**: The balance is calculated on-the-fly using `SUM(credit - debit)` from the transactions table, then cached in user meta for performance.

## 5. Security & Permissions
- **Capability**: Admins must have `manage_woocommerce` to change settings.
- **Account Locking**: Admins can "Lock" a user's wallet via the User Profile page, which disables all wallet usage and rewards for that customer.

## 6. Troubleshooting
- **Balance Out of Sync**: Recalculate or clear the user cache via the TeraWallet user list to refresh the metadata balance from the transaction ledger.
- **Missing Payments**: Check the order status. If an order status changes (e.g., to 'Refunded' or 'Cancelled'), the system automatically reverses the related wallet credit/debit.
- **Hidden Top-up Product**: Ensure the "Wallet Topup" product remains published and private.
