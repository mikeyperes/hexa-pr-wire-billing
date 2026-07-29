#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"

php "$root/tests/admin-navigation-unit.php"
php "$root/tests/settings-unit.php"
php "$root/tests/customer-pricing-unit.php"
php "$root/tests/managed-cart-security-unit.php"
php "$root/tests/gateway-policy-unit.php"
php "$root/tests/personalized-page-cache-unit.php"
php "$root/tests/portal-provenance-static.php"
php "$root/tests/migration-preflight-unit.php"
php "$root/tests/migration-transaction-unit.php"
php "$root/tests/architecture-static.php"
php "$root/lib/hexa-wordpress-plugin-core/tests/package-integrity.php"

find "$root" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
echo "PASS: All PHP files lint cleanly."
