# Fraud Detection Plugin

## Development Guide

### Plugin Structure

```
fraud-detection/
├── admin/
│   ├── class-admin-settings.php    # Admin interface
│   ├── css/
│   │   └── admin.css               # Admin styles
│   └── js/
│       └── admin.js                # Admin JavaScript
├── includes/
│   ├── class-database.php          # Database handler
│   ├── class-fraud-detector.php    # Core fraud detection logic
│   ├── class-list-manager.php      # Blacklist/whitelist manager
│   ├── class-order-tracker.php     # Order tracking
│   └── helpers.php                 # Helper functions
├── fraud-detection.php             # Main plugin file
├── uninstall.php                   # Cleanup on uninstall
├── README.md                       # Documentation
└── readme.txt                      # WordPress.org readme
```

### Database Schema

#### wp_fraud_blacklist

- id (bigint)
- entry_type (varchar) - phone, email, ip
- entry_value (varchar)
- is_permanent (tinyint)
- reason (text)
- added_by (bigint)
- date_added (datetime)
- date_modified (datetime)

#### wp_fraud_whitelist

- id (bigint)
- entry_type (varchar) - phone, email
- entry_value (varchar)
- bypass_daily_limit (tinyint)
- notes (text)
- added_by (bigint)
- date_added (datetime)
- date_modified (datetime)

#### wp_fraud_order_logs

- id (bigint)
- order_id (bigint)
- customer_email (varchar)
- customer_phone (varchar)
- customer_phone_normalized (varchar)
- customer_ip (varchar)
- order_total (decimal)
- is_blocked (tinyint)
- block_reason (text)
- date_created (datetime)

### Key Functions

#### Fraud Detection Flow

1. Customer submits checkout form
2. `Fraud_Detection_Detector::validate_checkout()` is called
3. Check if customer is whitelisted (bypass all checks)
4. Check if customer is blacklisted (block order)
5. Check daily order limit for phone number
6. If passed, allow order; if failed, show error message
7. `Fraud_Detection_Order_Tracker::track_order()` logs the order

#### Phone Normalization

```php
fraud_detection_normalize_phone( '+880 1712-345678' )
// Returns: 1712345678
```

### Hooks

#### Actions

```php
// Custom validation before fraud detection
add_action( 'fraud_detection_before_validate', function( $email, $phone ) {
    // Your code
}, 10, 2 );

// After order is blocked
add_action( 'fraud_detection_order_blocked', function( $reason, $data ) {
    // Your code
}, 10, 2 );
```

#### Filters

```php
// Modify daily limit
add_filter( 'fraud_detection_daily_limit', function( $limit, $phone ) {
    return $limit;
}, 10, 2 );

// Modify block message
add_filter( 'fraud_detection_block_message', function( $message, $reason ) {
    return $message;
}, 10, 2 );
```

### Testing Checklist

- [ ] Install plugin on WooCommerce site
- [ ] Configure daily limit (e.g., 3 orders)
- [ ] Place test order with phone number
- [ ] Try placing 4th order with same phone (should block)
- [ ] Add phone to whitelist
- [ ] Try placing order again (should allow)
- [ ] Add phone to blacklist
- [ ] Try placing order (should block)
- [ ] Test CSV import
- [ ] Check order logs
- [ ] Test admin notifications

### CSV Import Examples

**Blacklist CSV:**

```csv
type,value,reason,is_permanent
phone,01712345678,Fraudulent orders,yes
email,fraud@test.com,Chargeback,yes
ip,192.168.1.100,Suspicious,no
```

**Whitelist CSV:**

```csv
type,value,notes,bypass_limit
phone,01611111111,VIP customer,yes
email,vip@customer.com,Regular buyer,yes
```

### Customization Examples

#### Change Phone Normalization Pattern

Edit `includes/helpers.php`:

```php
function fraud_detection_normalize_phone( $phone ) {
    // Custom normalization logic
    $normalized = preg_replace( '/[^0-9]/', '', $phone );
    // Add your custom patterns
    return $normalized;
}
```

#### Add Custom Validation

```php
add_action( 'woocommerce_checkout_process', function() {
    // Your custom validation
    if ( some_condition ) {
        wc_add_notice( 'Custom error message', 'error' );
    }
}, 5 ); // Priority 5 runs before fraud detection (priority 10)
```

### Performance Optimization

1. Database indexes are created on frequently queried columns
2. Logs are automatically cleaned based on retention setting
3. Phone normalization is cached when possible
4. Use `fraud_detection_enabled` option to quickly disable plugin

### Security

- All inputs are sanitized
- Nonce verification on all forms
- Capability checks (manage_woocommerce)
- Prepared SQL statements
- No direct file access

### Troubleshooting

**Issue: Plugin not blocking orders**

- Check if plugin is enabled in settings
- Verify WooCommerce is active
- Check if phone normalization is enabled
- Review order logs for details

**Issue: False positives**

- Add customers to whitelist
- Increase daily limit
- Disable phone normalization if needed

**Issue: CSV import not working**

- Check CSV format matches examples
- Ensure file is UTF-8 encoded
- Verify proper column order

### Support Bangladesh Phone Formats

The plugin automatically handles:

- +880 1712345678
- 880 1712345678
- 01712345678
- 01712-345678
- (0171) 2345678

All normalize to: 1712345678

### Future Enhancements

- [ ] REST API endpoints
- [ ] Advanced analytics dashboard
- [ ] Automatic blacklist based on patterns
- [ ] Integration with payment gateways
- [ ] Multi-site support
- [ ] Export reports to PDF
- [ ] SMS notifications
- [ ] Geolocation blocking

### Contributing

1. Fork the repository
2. Create feature branch
3. Make changes
4. Test thoroughly
5. Submit pull request

### License

GPL v2 or later
