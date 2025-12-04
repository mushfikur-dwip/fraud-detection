# Fraud Detection Plugin - সমস্যা সমাধান / Troubleshooting

## সমস্যা: প্লাগিন অর্ডার ডিটেক্ট করছে না

### ধাপ ১: প্লাগিন পুনরায় চালু করুন

1. WordPress Admin এ যান
2. `Plugins` > `Installed Plugins` এ যান
3. `Fraud Detection & Duplicate Order Filter` প্লাগিন **Deactivate** করুন
4. তারপর আবার **Activate** করুন
5. এটি database tables পুনরায় তৈরি করবে

### ধাপ ২: Test Page দিয়ে চেক করুন

1. WordPress Admin এ যান
2. `WooCommerce` > `FD Test` এ যান
3. এখানে দেখবেন:
   - প্লাগিন লোড হয়েছে কিনা
   - Database tables তৈরি হয়েছে কিনা
   - Settings সঠিক আছে কিনা
   - Order logs আছে কিনা

### ধাপ ৩: Settings চেক করুন

1. `WooCommerce` > `Fraud Detection` এ যান
2. `Settings` tab এ যান
3. নিচের settings গুলো চেক করুন:

```
✅ Enable Fraud Detection: YES (checked)
✅ Check Phone Number: YES (checked)
✅ Daily Order Limit: 3 (বা আপনার পছন্দমত)
✅ Normalize Phone Numbers: YES (checked)
✅ Check Device Fingerprint: YES (checked)
✅ Device Order Limit: 5
```

### ধাপ ৪: Debug Log চেক করুন

1. WordPress debug mode চালু করুন। `wp-config.php` ফাইলে যোগ করুন:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

2. একটি test order দিন
3. `/wp-content/debug.log` ফাইল চেক করুন
4. এখানে দেখবেন:

```
Fraud Detection: validate_checkout() called
Fraud Detection: enabled = yes
Fraud Detection: Phone=01711111111, Email=test@example.com
Fraud Detection: Normalized phone=1711111111
Fraud Detection: IP=127.0.0.1
Fraud Detection: Checking daily phone limit for 1711111111
Fraud Detection: Found 2 orders today for phone 1711111111 (limit=3)
```

### ধাপ ৫: Database Tables চেক করুন

phpMyAdmin বা database tool দিয়ে চেক করুন:

1. Table: `wp_fraud_order_logs` আছে কিনা
2. কিছু records আছে কিনা
3. SQL Query চালান:

```sql
SELECT * FROM wp_fraud_order_logs ORDER BY date_created DESC LIMIT 10;
```

### সাধারণ সমস্যা এবং সমাধান

#### সমস্যা ১: Validation কাজ করছে না

**কারণ:** Hook সঠিকভাবে registered হয়নি

**সমাধান:**

1. প্লাগিন deactivate এবং activate করুন
2. Settings এ গিয়ে "Enable Fraud Detection" checked করুন
3. অন্য cache plugin থাকলে cache clear করুন

#### সমস্যা ২: Order log হচ্ছে না

**কারণ:** Database table নেই বা permission issue

**সমাধান:**

1. Test page থেকে check করুন table আছে কিনা
2. না থাকলে plugin deactivate/activate করুন
3. Database user এর CREATE TABLE permission আছে কিনা চেক করুন

#### সমস্যা ৩: Phone normalization কাজ করছে না

**কারণ:** Function properly load হয়নি

**সমাধান:**

1. `includes/helpers.php` file আছে কিনা চেক করুন
2. Settings এ "Normalize Phone Numbers" checked করুন
3. Test করার জন্য:
   - `+880 1711-111111` দিলে normalize হয়ে `1711111111` হবে
   - `01711111111` দিলে normalize হয়ে `1711111111` হবে

#### সমস্যা ৪: Device fingerprinting কাজ করছে না

**কারণ:** JavaScript file load হচ্ছে না

**সমাধান:**

1. Checkout page এ browser console open করুন (F12)
2. `fingerprint.js` file load হয়েছে কিনা দেখুন
3. Console এ কোন error আছে কিনা চেক করুন
4. Cookie `fraud_detection_device_id` set হয়েছে কিনা দেখুন

### পুরোপুরি Reset করার জন্য

যদি কিছুতেই কাজ না করে, পুরোপুরি reset করুন:

1. Plugin deactivate করুন
2. Database থেকে tables মুছে দিন:

```sql
DROP TABLE IF EXISTS wp_fraud_blacklist;
DROP TABLE IF EXISTS wp_fraud_whitelist;
DROP TABLE IF EXISTS wp_fraud_order_logs;
```

3. Options মুছে দিন:

```sql
DELETE FROM wp_options WHERE option_name LIKE 'fraud_detection_%';
```

4. Plugin আবার activate করুন

### সাহায্য দরকার?

Debug log এর output পাঠান:

- `/wp-content/debug.log` file এর শেষ 50 lines
- Test page এর screenshot
- Settings page এর screenshot

## পরীক্ষা করার জন্য

1. প্রথম order: `01711111111` দিয়ে order দিন → Success
2. দ্বিতীয় order: একই নাম্বার দিয়ে order দিন → Success
3. তৃতীয় order: একই নাম্বার দিয়ে order দিন → Success
4. চতুর্থ order: একই নাম্বার দিয়ে order দিন → **BLOCKED** ✓

এই message দেখাবে:

> "You have reached the maximum number of orders allowed per day from this phone number."

যদি এভাবে কাজ না করে, তাহলে debug log চেক করুন এবং সমস্যা খুঁজুন।
