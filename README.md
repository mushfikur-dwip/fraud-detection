# Fraud Detection & Duplicate Order Filter

A professional WordPress/WooCommerce plugin that prevents fraudulent orders by detecting duplicate phone numbers and email addresses, implementing daily order limits, and managing blacklist/whitelist entries.

## Features

- **Duplicate Detection**: Prevents duplicate orders from the same phone number or email address
- **Daily Order Limits**: Set maximum number of orders per day from the same phone number
- **Blacklist Management**: Block specific phone numbers, emails, or IP addresses permanently or temporarily
- **Whitelist Management**: Allow trusted customers to bypass fraud detection rules
- **Order Tracking**: Comprehensive logging of all orders with fraud detection information
- **Admin Notifications**: Email alerts when orders are blocked or limits are reached
- **Phone Number Normalization**: Automatically standardizes phone numbers for consistent matching
- **CSV Import**: Bulk import blacklist/whitelist entries from CSV files
- **Configurable Messages**: Customize error messages displayed to customers

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the `fraud-detection` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Fraud Detection to configure settings
4. Configure your daily order limits and other options

## Configuration

### General Settings

- **Enable Fraud Detection**: Turn the system on/off
- **Daily Order Limit**: Maximum orders per phone number per day (default: 3)
- **Check Phone Numbers**: Enable duplicate phone detection
- **Check Email Addresses**: Enable duplicate email detection
- **Normalize Phone Numbers**: Remove spaces, dashes, and country codes
- **Block Message**: Message shown when order is blocked
- **Limit Message**: Message shown when daily limit is reached

### Blacklist Management

Add entries manually or import via CSV:

- Phone numbers (e.g., 01712345678)
- Email addresses (e.g., user@example.com)
- IP addresses (e.g., 192.168.1.1)
- Set permanent blocks for repeat offenders
- Add reasons for blocking

### Whitelist Management

Add trusted customers who can bypass fraud checks:

- Phone numbers
- Email addresses
- Choose whether to bypass daily limits
- Add notes for reference

### Order Logs

View all order attempts including:

- Successful orders
- Blocked orders with reasons
- Customer information (email, phone, IP)
- Date and time stamps

## CSV Import Format

### Blacklist CSV Format

```csv
type,value,reason,is_permanent
phone,01712345678,Fraudulent activity,yes
email,fraud@example.com,Chargeback,no
ip,192.168.1.1,Suspicious behavior,yes
```

### Whitelist CSV Format

```csv
type,value,notes,bypass_limit
phone,01611111111,VIP customer,yes
email,vip@example.com,Regular buyer,yes
```

## How It Works

1. **Checkout Validation**: When a customer places an order, the plugin checks:

   - If phone/email is in whitelist (allows order)
   - If phone/email/IP is in blacklist (blocks order)
   - Daily order count for the phone number (blocks if limit exceeded)

2. **Order Tracking**: Every order attempt is logged with:

   - Customer details (email, phone, IP)
   - Order information
   - Block status and reason

3. **Notifications**: Admins receive email alerts for:
   - Blocked orders
   - Daily limit reached
   - Suspicious activity

## Hooks and Filters

### Actions

```php
// Before fraud detection validation
do_action( 'fraud_detection_before_validate', $billing_email, $billing_phone );

// After order is blocked
do_action( 'fraud_detection_order_blocked', $reason, $customer_data );

// After order is logged
do_action( 'fraud_detection_order_logged', $order_id, $log_data );
```

### Filters

```php
// Modify daily order limit
$limit = apply_filters( 'fraud_detection_daily_limit', $limit, $phone );

// Modify block message
$message = apply_filters( 'fraud_detection_block_message', $message, $reason );

// Modify normalization
$normalized = apply_filters( 'fraud_detection_normalize_phone', $normalized, $original );
```

## Database Tables

The plugin creates three custom tables:

1. **wp_fraud_blacklist**: Stores blacklisted entries
2. **wp_fraud_whitelist**: Stores whitelisted entries
3. **wp_fraud_order_logs**: Stores all order attempts

## Uninstallation

When uninstalling the plugin:

1. Deactivate the plugin
2. Delete from Plugins menu
3. All settings and database tables can be preserved or removed

## Support

For support, feature requests, or bug reports:

- Email: support@yourwebsite.com
- Documentation: https://yourwebsite.com/docs

## Changelog

### Version 1.0.0

- Initial release
- Duplicate phone/email detection
- Daily order limits
- Blacklist/whitelist management
- Order tracking and logging
- Admin notifications
- CSV import functionality

## License

GPL v2 or later

## Credits

Developed by Mushfikur Rahman
