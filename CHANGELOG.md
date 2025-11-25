# Changelog

All notable changes to Woo Conditional Payments will be documented in this file.

## [1.0.1] - 2025-11-25

### Fixed
- Fixed issue where payment method visibility was only checked against the user's primary role
- Now properly checks all user roles when determining payment method visibility
- Payment methods will be shown if the user has ANY of the allowed roles
- Payment methods will be hidden if the user has ANY of the restricted roles

### Changed
- Updated `get_current_user_role()` method to `get_current_user_roles()` to return all user roles
- Modified role checking logic to use `array_intersect()` for proper multi-role support

## [1.0.0] - 2025-11-25

### Added
- Initial release
- Control WooCommerce payment methods visibility based on user roles
- Option to show payment methods only to specific user roles
- Option to hide payment methods from specific user roles
- Support for guest (non-logged-in) users
- Integration with WooCommerce payment gateway settings