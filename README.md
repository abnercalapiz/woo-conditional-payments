# Woo Conditional Payments

A WordPress plugin that allows you to control WooCommerce payment method visibility based on user roles.

## Description

Woo Conditional Payments gives you fine-grained control over which payment methods are displayed to customers based on their user role. This is perfect for:

- Offering special payment methods to VIP customers
- Restricting certain payment options to wholesale buyers
- Hiding specific payment methods from guest users
- Creating role-based payment workflows

## Features

- **Role-Based Visibility**: Control which user roles can see each payment method
- **Role-Based Hiding**: Hide payment methods from specific user roles
- **Default Visibility**: All payment methods are visible by default (no configuration required)
- **Guest User Support**: Include or exclude non-logged-in users
- **Easy Configuration**: Integrated directly into WooCommerce payment settings
- **Flexible Logic**: Combine "show to" and "hide from" rules for precise control
- **Lightweight**: Minimal performance impact
- **Secure**: Built with WordPress security best practices

## Requirements

- WordPress 5.0 or higher
- WooCommerce 3.0 or higher
- PHP 7.2 or higher

## Installation

1. Upload the `woo-conditional-payments` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce > Settings > Payments
4. Configure visibility for each payment method as needed

## Configuration

1. Go to **WooCommerce > Settings > Payments**
2. Click on any payment method to edit its settings
3. Configure visibility using these fields:
   - **"Visible to User Roles"**: Select roles that CAN see this payment method
   - **"Hide from User Roles"**: Select roles that CANNOT see this payment method
4. Leave fields empty to use default behavior (visible to all)
5. Save changes

### Available Options

- All WordPress user roles (Administrator, Editor, Author, Contributor, Subscriber, etc.)
- Custom roles created by other plugins
- Guest (non-logged-in users)

### Priority Rules

- **Hide rules take priority**: If a role is selected in both "Visible to" and "Hide from", the payment method will be hidden
- **Empty "Visible to"**: Payment method is visible to everyone (unless hidden by "Hide from" rules)
- **Empty "Hide from"**: No roles are explicitly blocked

## Usage Examples

### Example 1: Premium Payment Method
Make a "Net 30 Terms" payment option available only to wholesale customers:
1. Edit the payment method settings
2. Select only "Wholesale Customer" role
3. Save changes

### Example 2: Restrict PayPal to Logged-in Users
1. Edit PayPal payment settings
2. Select all roles except "Guest"
3. Save changes

### Example 3: VIP-Only Payment Method
1. Create a VIP user role (using a role manager plugin)
2. Edit the special payment method
3. Select only "VIP" role in "Visible to User Roles"
4. Save changes

### Example 4: Hide COD from Administrators
1. Edit Cash on Delivery payment method
2. Select "Administrator" in "Hide from User Roles"
3. Save changes

### Example 5: Complex Rules
Make a payment method visible only to wholesale customers but hide it from guests:
1. Edit the payment method
2. In "Visible to User Roles": Select "Wholesale Customer"
3. In "Hide from User Roles": Select "Guest"
4. Save changes

## Security Features

- **Direct Access Prevention**: Files cannot be accessed directly
- **Data Sanitization**: All user inputs are properly sanitized
- **Capability Checks**: Uses WooCommerce's built-in permission system
- **Secure Output**: All output is properly escaped
- **Nonce Protection**: Handled by WooCommerce's settings API

## Hooks and Filters

### Filters

- `woocommerce_available_payment_gateways` - Main filter for controlling payment method visibility
- `woocommerce_settings_api_form_fields_{gateway_id}` - Adds role selection field to payment settings

### Actions

- `plugins_loaded` - Main plugin initialization
- `woocommerce_init` - Adds role fields to payment methods

## Frequently Asked Questions

### Q: What happens if I don't select any roles?
A: The payment method will be visible to everyone (default behavior).

### Q: Can I use this with custom user roles?
A: Yes, the plugin automatically detects all registered user roles, including custom ones.

### Q: Will this work with all payment gateways?
A: Yes, it works with all payment methods that properly integrate with WooCommerce.

### Q: Is the plugin multisite compatible?
A: Yes, the plugin works on both single site and multisite installations.

### Q: How does it handle users with multiple roles?
A: The plugin checks the user's primary role (first role in the array).

## Troubleshooting

### Payment methods not hiding
1. Clear your cache (browser and any caching plugins)
2. Ensure WooCommerce is updated
3. Check that user roles are properly assigned

### Settings not saving
1. Check for JavaScript errors in browser console
2. Ensure proper file permissions
3. Verify no conflicts with other plugins

## Changelog

### Version 1.0.0
- Initial release
- Role-based payment method visibility
- Hide from specific roles feature
- Guest user support
- WooCommerce settings integration
- Priority-based rule system (hide rules override visible rules)

## Support

For support, please create an issue in the plugin repository or contact the plugin author.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed for WooCommerce stores that need flexible payment method management based on user roles.