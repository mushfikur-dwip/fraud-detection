# কেন Order Block হচ্ছে না? সমাধান

## দ্রুত চেক করুন (১ মিনিট)

### ১. Plugin Activate আছে কিনা?

- WP Admin > Plugins এ যান
- "Fraud Detection & Duplicate Order Filter" **Activated** আছে কিনা দেখুন

### ২. Settings চেক করুন

WP Admin > WooCommerce > Fraud Detection > Settings এ যান:

```
✅ Enable Fraud Detection - CHECKED থাকতে হবে
✅ Check Phone Number - CHECKED থাকতে হবে
✅ Daily Order Limit - 3 (বা যেকোনো সংখ্যা 1-10)
```

### ৩. Test করুন

একই phone number দিয়ে একের পর এক 4টি order দিন:

- Order 1: ✓ Success
- Order 2: ✓ Success
- Order 3: ✓ Success
- Order 4: ✗ **BLOCKED** (এই message দেখাবে: "You have reached the maximum number of orders allowed per day from this phone number.")

যদি Order 4 block না হয়, নিচের steps follow করুন।

---

## গভীর সমস্যা সমাধান

### পদক্ষেপ ১: Plugin পুনরায় চালু করুন

এটি database tables এবং default settings reinstall করবে:

1. WP Admin > Plugins
2. "Fraud Detection" plugin **Deactivate** করুন
3. কয়েক সেকেন্ড wait করুন
4. আবার **Activate** করুন

### পদক্ষেপ ২: Debug Mode চালু করুন

`wp-config.php` file খুলুন এবং নিচের code যোগ করুন (PHP opening tag এর পরে):

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );
```

### পদক্ষেপ ৩: Test Order দিন এবং Log চেক করুন

1. Checkout page এ যান
2. একটি test order দিন (যেকোনো phone number দিয়ে)
3. `/wp-content/debug.log` file open করুন
4. নিচের messages খুঁজুন:

#### ✅ সঠিক Output (Plugin কাজ করছে):

```
[05-Dec-2025 12:00:00 UTC] Fraud Detection: validate_checkout() called
[05-Dec-2025 12:00:00 UTC] Fraud Detection: enabled = yes
[05-Dec-2025 12:00:00 UTC] Fraud Detection: Phone=01711111111, Email=test@example.com
[05-Dec-2025 12:00:00 UTC] Fraud Detection: Normalized phone=1711111111
[05-Dec-2025 12:00:00 UTC] Fraud Detection: Checking daily phone limit for 1711111111
[05-Dec-2025 12:00:00 UTC] Fraud Detection: Found 2 orders today for phone 1711111111 (limit=3)
[05-Dec-2025 12:00:01 UTC] Fraud Detection: track_order() called for order #123
```

#### ❌ সমস্যা #1: কোন log নেই

**কারণ:** Plugin এর validation function call হচ্ছে না

**সমাধান:**

1. Plugin deactivate/activate করুন
2. Settings এ গিয়ে "Enable Fraud Detection" check করুন এবং Save করুন
3. Cache clear করুন (যদি cache plugin থাকে)

#### ❌ সমস্যা #2: "enabled = no" দেখাচ্ছে

**সমাধান:** Settings এ গিয়ে "Enable Fraud Detection" checkbox করুন

#### ❌ সমস্যা #3: "Found 0 orders" সবসময় দেখাচ্ছে

**কারণ:** Database table নেই বা order track হচ্ছে না

**সমাধান:**

1. WP Admin > WooCommerce > FD Test page এ যান
2. "Database Tables" section দেখুন
3. যদি red ✗ থাকে, plugin deactivate/activate করুন

### পদক্ষেপ ৪: Database Manual Check

phpMyAdmin open করে এই query চালান:

```sql
-- Check if table exists
SHOW TABLES LIKE 'wp_fraud_order_logs';

-- Check order logs
SELECT * FROM wp_fraud_order_logs
ORDER BY date_created DESC
LIMIT 10;

-- Check today's orders for a specific phone
SELECT COUNT(*) as order_count
FROM wp_fraud_order_logs
WHERE customer_phone_normalized = '1711111111'
AND is_blocked = 0
AND DATE(date_created) = CURDATE();
```

#### Table নেই?

```sql
-- Manually create table (এটি plugin activation automatically করার কথা)
CREATE TABLE IF NOT EXISTS wp_fraud_order_logs (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id bigint(20) UNSIGNED,
    customer_email varchar(100),
    customer_phone varchar(50),
    customer_phone_normalized varchar(50),
    customer_ip varchar(50),
    device_fingerprint varchar(32),
    browser_fingerprint varchar(32),
    device_cookie varchar(255),
    user_agent text,
    device_type varchar(20),
    browser_name varchar(50),
    order_total decimal(10,2),
    is_blocked tinyint(1) NOT NULL DEFAULT 0,
    block_reason text,
    date_created datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY customer_phone_normalized (customer_phone_normalized),
    KEY is_blocked (is_blocked),
    KEY date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### পদক্ষেপ ৫: WooCommerce Checkout Type চেক করুন

আপনি কি **Classic Checkout** না **Block-based Checkout** use করছেন?

#### Check করার জন্য:

1. WP Admin > Appearance > Editor > Templates
2. "Checkout" template খুলে দেখুন
3. যদি blocks (drag & drop) দেখেন = Block Checkout
4. যদি shortcode দেখেন = Classic Checkout

#### Block Checkout এর জন্য:

Plugin এ Block support যোগ করা আছে। কিন্তু যদি কাজ না করে:

1. Classic Checkout page তৈরি করুন: `[woocommerce_checkout]` shortcode দিয়ে
2. অথবা WooCommerce settings থেকে checkout page change করুন

### পদক্ষেপ ৬: Theme/Plugin Conflict Check

কিছু theme বা plugin WooCommerce hooks block করতে পারে।

#### Test করার জন্য:

1. সব plugin disable করুন (শুধু WooCommerce এবং Fraud Detection রাখুন)
2. Default theme (Twenty Twenty-Four) activate করুন
3. Test order দিন
4. কাজ করলে, এক এক করে plugin enable করে দেখুন কোনটি problem করছে

### পদক্ষেপ ৭: Server Requirements

PHP Version check করুন:

```
PHP 7.4 বা তার উপরে হতে হবে
```

WordPress Version:

```
WordPress 5.8+ হতে হবে
```

WooCommerce Version:

```
WooCommerce 5.0+ হতে হবে
```

---

## সাধারণ সমস্যা ও সমাধান

### সমস্যা: Phone normalize হচ্ছে না

**উদাহরণ:** `+880 1711-111111` থেকে `1711111111` হচ্ছে না

**সমাধান:**

1. Settings > "Normalize Phone Numbers" check করুন
2. `includes/helpers.php` file আছে কিনা verify করুন

### সমস্যা: Same phone দিয়ে unlimited order হচ্ছে

**কারণ:** Order tracking কাজ করছে না

**সমাধান:**

1. Debug log দেখুন - `track_order() called` message আছে কিনা
2. Database এ `wp_fraud_order_logs` table check করুন
3. Records insert হচ্ছে কিনা verify করুন

### সমস্যা: Error message show হচ্ছে না

**কারণ:** `wc_add_notice()` function কাজ করছে না

**সমাধান:**
Plugin এ `woocommerce_after_checkout_validation` hook যোগ করা আছে যেটি WP_Error object use করে। এটি more reliable।

---

## পুরোপুরি Reset (Last Resort)

সব কিছু ব্যর্থ হলে complete reset করুন:

### ১. Database Cleanup

```sql
DROP TABLE IF EXISTS wp_fraud_blacklist;
DROP TABLE IF EXISTS wp_fraud_whitelist;
DROP TABLE IF EXISTS wp_fraud_order_logs;

DELETE FROM wp_options WHERE option_name LIKE 'fraud_detection_%';
```

### ২. Plugin Reinstall

1. Plugin delete করুন (files সহ)
2. Fresh download/upload করুন
3. Activate করুন

### ৩. Settings Configure

1. WooCommerce > Fraud Detection
2. সব settings manually configure করুন
3. Save করুন

---

## যোগাযোগ

এখনও কাজ না করলে এই information পাঠান:

1. ✅ PHP Version: `<?php echo PHP_VERSION; ?>`
2. ✅ WordPress Version
3. ✅ WooCommerce Version
4. ✅ Active Theme name
5. ✅ Debug log এর last 50 lines (যেখানে "Fraud Detection" আছে)
6. ✅ Database query result: `SELECT COUNT(*) FROM wp_fraud_order_logs`
7. ✅ Checkout type: Classic or Block-based
8. ✅ Screenshot of Settings page

---

## দ্রুত Reference

### Plugin কাজ করছে কিনা এক নজরে দেখুন:

```bash
# Plugin folder এ যান এবং এই command চালান:
./test.sh
```

এটি automatically সব check করবে এবং result দেখাবে।

### Manual Quick Test:

1. ✅ Plugin activated?
2. ✅ Settings enabled?
3. ✅ Daily limit set? (3 recommended)
4. ✅ Database tables exist? (WooCommerce > FD Test)
5. ✅ Debug log shows "validate_checkout() called"?
6. ✅ Debug log shows "track_order() called"?

সব ✅ হলে plugin সঠিকভাবে কাজ করার কথা।
