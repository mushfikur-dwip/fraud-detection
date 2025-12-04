#!/bin/bash
# Quick Test Script for Fraud Detection Plugin

echo "==================================="
echo "Fraud Detection Plugin Quick Test"
echo "==================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test 1: Check if files exist
echo "1. Checking plugin files..."
if [ -f "fraud-detection.php" ]; then
    echo -e "${GREEN}✓${NC} Main plugin file exists"
else
    echo -e "${RED}✗${NC} Main plugin file missing"
fi

if [ -f "includes/class-fraud-detector.php" ]; then
    echo -e "${GREEN}✓${NC} Fraud detector class exists"
else
    echo -e "${RED}✗${NC} Fraud detector class missing"
fi

if [ -f "includes/class-order-tracker.php" ]; then
    echo -e "${GREEN}✓${NC} Order tracker class exists"
else
    echo -e "${RED}✗${NC} Order tracker class missing"
fi

if [ -f "includes/class-database.php" ]; then
    echo -e "${GREEN}✓${NC} Database class exists"
else
    echo -e "${RED}✗${NC} Database class missing"
fi

echo ""
echo "2. Checking for hooks in main file..."
if grep -q "woocommerce_checkout_process" fraud-detection.php; then
    echo -e "${GREEN}✓${NC} woocommerce_checkout_process hook found"
else
    echo -e "${RED}✗${NC} woocommerce_checkout_process hook NOT found"
fi

if grep -q "woocommerce_after_checkout_validation" fraud-detection.php; then
    echo -e "${GREEN}✓${NC} woocommerce_after_checkout_validation hook found"
else
    echo -e "${YELLOW}!${NC} woocommerce_after_checkout_validation hook not found (optional)"
fi

if grep -q "woocommerce_checkout_order_processed" fraud-detection.php; then
    echo -e "${GREEN}✓${NC} woocommerce_checkout_order_processed hook found"
else
    echo -e "${RED}✗${NC} woocommerce_checkout_order_processed hook NOT found"
fi

echo ""
echo "3. Checking validation methods..."
if grep -q "function validate_checkout" includes/class-fraud-detector.php; then
    echo -e "${GREEN}✓${NC} validate_checkout method exists"
else
    echo -e "${RED}✗${NC} validate_checkout method missing"
fi

if grep -q "function check_daily_limit" includes/class-fraud-detector.php; then
    echo -e "${GREEN}✓${NC} check_daily_limit method exists"
else
    echo -e "${RED}✗${NC} check_daily_limit method missing"
fi

if grep -q "function track_order" includes/class-order-tracker.php; then
    echo -e "${GREEN}✓${NC} track_order method exists"
else
    echo -e "${RED}✗${NC} track_order method missing"
fi

echo ""
echo "4. Checking for debug logs..."
if grep -q "error_log.*Fraud Detection" includes/class-fraud-detector.php; then
    echo -e "${GREEN}✓${NC} Debug logging enabled in fraud detector"
else
    echo -e "${YELLOW}!${NC} No debug logging in fraud detector"
fi

if grep -q "error_log.*Fraud Detection" includes/class-order-tracker.php; then
    echo -e "${GREEN}✓${NC} Debug logging enabled in order tracker"
else
    echo -e "${YELLOW}!${NC} No debug logging in order tracker"
fi

echo ""
echo "==================================="
echo "Test complete!"
echo "==================================="
echo ""
echo "Next steps:"
echo "1. Deactivate and reactivate the plugin in WordPress"
echo "2. Go to WooCommerce > FD Test to check database"
echo "3. Enable WP_DEBUG_LOG in wp-config.php"
echo "4. Place a test order and check /wp-content/debug.log"
echo ""
