=== Fraud Detection & Duplicate Order Filter ===
Contributors: mushfikurrahman
Tags: woocommerce, fraud, duplicate, order limit, blacklist
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Prevent fraudulent WooCommerce orders with duplicate detection, daily order limits, and comprehensive blacklist/whitelist management.

== Description ==

Fraud Detection & Duplicate Order Filter is a powerful WooCommerce plugin that helps you prevent fraudulent orders and manage customer order limits effectively.

= Key Features =

* **Duplicate Phone Detection** - Block orders from duplicate phone numbers
* **Duplicate Email Detection** - Prevent multiple orders from the same email
* **Daily Order Limits** - Set maximum orders per phone number per day
* **Blacklist Management** - Block phone numbers, emails, and IP addresses
* **Whitelist Management** - Allow trusted customers to bypass restrictions
* **Permanent Blocking** - Set permanent blocks for repeat offenders
* **Phone Number Normalization** - Standardize phone formats for accurate detection
* **Order Tracking** - Log all order attempts with detailed information
* **Admin Notifications** - Email alerts for blocked orders
* **CSV Import** - Bulk import blacklist/whitelist entries
* **Customizable Messages** - Configure error messages for customers

= Use Cases =

* Prevent customers from placing excessive orders using the same phone number
* Block known fraudulent phone numbers and emails
* Manage VIP customers with whitelist bypass
* Track suspicious order patterns
* Reduce chargebacks and fraud

= Bangla (বাংলা) Support =

এই প্লাগইনটি সম্পূর্ণভাবে বাংলাদেশী ই-কমার্স ব্যবসার জন্য উপযুক্ত। ডুপ্লিকেট ফোন নাম্বার দিয়ে দৈনিক নির্দিষ্ট সংখ্যক অর্ডার সীমাবদ্ধ করুন এবং ব্ল্যাকলিস্ট/হোয়াইটলিস্ট ম্যানেজমেন্ট করুন।

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/fraud-detection/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Fraud Detection to configure settings
4. Set your daily order limits and configure detection options

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =

Yes, this plugin requires WooCommerce to be installed and active.

= How does phone number normalization work? =

The plugin removes spaces, dashes, parentheses, and country codes from phone numbers to ensure consistent matching. For example, "+880 1712-345678" and "01712345678" will be treated as the same number.

= Can I import bulk blacklist entries? =

Yes, you can import entries via CSV file with format: type, value, reason, is_permanent

= Will whitelisted customers bypass all restrictions? =

Yes, whitelisted customers can bypass both blacklist checks and daily order limits (configurable).

= How long are order logs kept? =

By default, logs are kept for 30 days. You can configure the retention period in settings or set to 0 to keep forever.

= Can I customize the error messages? =

Yes, you can customize both the blacklist block message and daily limit message in the settings.

== Screenshots ==

1. Settings page with daily limit configuration
2. Blacklist management interface
3. Whitelist management interface
4. Order logs with detailed tracking
5. Add to blacklist form
6. CSV import interface

== Changelog ==

= 1.0.0 =
* Initial release
* Duplicate phone/email detection
* Daily order limit per phone number
* Blacklist/whitelist management
* Permanent blocking feature
* Phone number normalization
* Order tracking and logging
* Admin email notifications
* CSV import functionality
* Customizable messages

== Upgrade Notice ==

= 1.0.0 =
Initial release of Fraud Detection plugin.

== Support ==

For support, please visit https://yourwebsite.com/support or email support@yourwebsite.com

== Privacy Policy ==

This plugin stores customer information (email, phone, IP addresses) for fraud detection purposes. This data is stored in your WordPress database and is subject to your site's privacy policy.
