পুরো সিস্টেম চেক করার জন্য এই পেজে যান:

**WP Admin > WooCommerce > FD Test**

এখানে আপনি দেখতে পারবেন:

### ১. Plugin Status

- ✓ Plugin class loaded
- ✓ Database object loaded
- ✓ Detector object loaded
- ✓ Order tracker object loaded

### ২. Database Tables

- ✓ Blacklist table exists (X records)
- ✓ Whitelist table exists (X records)
- ✓ Order Logs table exists (X records)

### ৩. Recent Order Logs

Table দেখাবে সব recent orders:

- Order ID
- Phone (normalized)
- Email
- IP
- Device Fingerprint
- Blocked? (Yes/No)
- Date

### ৪. Plugin Settings

- fraud_detection_enabled: yes/no
- fraud_detection_daily_limit: 3
- fraud_detection_check_phone: yes/no
- fraud_detection_check_email: yes/no

### ৫. WooCommerce Hooks

- ✓ woocommerce_checkout_process registered
- ✓ woocommerce_checkout_order_processed registered

---

## এই পেজ দেখে বুঝতে পারবেন:

### যদি Order Logs empty থাকে:

➜ **সমস্যা:** Orders track হচ্ছে না
➜ **সমাধান:** Plugin deactivate/activate করুন

### যদি "Found 0 orders" দেখায় কিন্তু আসলে আছে:

➜ **সমস্যা:** Query সঠিকভাবে কাজ করছে না
➜ **সমাধান:** Phone number normalize হচ্ছে কিনা check করুন

### যদি Hooks "NOT registered" দেখায়:

➜ **সমস্যা:** Plugin properly loaded হয়নি
➜ **সমাধান:** Plugin reinstall করুন

### যদি enabled = no দেখায়:

➜ **সমস্যা:** Plugin disabled করা আছে
➜ **সমাধান:** Settings এ গিয়ে enable করুন

---

## Debug করার সহজ উপায়:

### Terminal থেকে:

```bash
cd /path/to/wordpress/wp-content/plugins/fraud-detection
./test.sh
```

### Debug Log দেখার জন্য:

```bash
tail -f /path/to/wordpress/wp-content/debug.log | grep "Fraud Detection"
```

### একটা test order দিন, দেখবেন real-time log:

```
Fraud Detection: validate_checkout() called
Fraud Detection: enabled = yes
Fraud Detection: Phone=01711111111, Email=test@example.com
Fraud Detection: Normalized phone=1711111111
Fraud Detection: Checking daily phone limit for 1711111111
Fraud Detection: Found 2 orders today for phone 1711111111 (limit=3)
```

যদি limit exceed হয়:

```
Fraud Detection: Daily limit check result - count=3, limit=3, exceeded=YES
Fraud Detection: BLOCKING ORDER - Daily phone limit exceeded
```

---

## সবচেয়ে Important:

1. **Plugin Deactivate/Activate করুন** - এটি সব tables এবং settings reset করবে
2. **WooCommerce > FD Test দেখুন** - এখানে সব information পাবেন
3. **Debug.log check করুন** - Real problem এখানে দেখা যাবে
4. **একই phone দিয়ে 4 বার test করুন** - 4র্থ বার block হওয়া উচিত

যদি এখনও কাজ না করে, **WHY-NOT-BLOCKING.md** file খুলে step by step follow করুন।
