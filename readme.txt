=== WooCommerce AI Customer Service ===
Contributors: organicskincare
Tags: woocommerce, chatbot, ai, customer service, lead capture, order tracking
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered customer service chatbot with lead capture using Claude API. Help customers track orders, answer questions, and capture leads.

== Description ==

WooCommerce AI Customer Service adds an intelligent chatbot to your store that can:

* **Capture Leads** - Collect customer name, email, and phone before starting conversations
* **Access Order History** - Automatically retrieve and display customer order information
* **Provide Order Tracking** - Share tracking numbers and delivery status
* **Answer Questions** - Handle product inquiries, shipping questions, and general support
* **Escalate Intelligently** - Direct customers to human support when needed

= Key Features =

* **AI-Powered Responses** - Uses Anthropic's Claude API for natural, helpful conversations
* **Lead Capture Form** - Collects contact information before chat starts
* **WooCommerce Integration** - Pulls order history, tracking, and customer data automatically
* **Admin Dashboard** - View and export captured leads
* **Customizable Widget** - Match your brand colors and messaging
* **Mobile Responsive** - Works perfectly on all devices
* **Rate Limiting** - Prevents abuse with per-session message limits
* **Secure** - Encrypted API key storage, nonce verification, input sanitization

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* SSL certificate (required for API calls)
* Anthropic API key

= Cost Efficient =

Uses Claude Haiku model by default - one of the most cost-effective AI models available:
* Approximately $0.003 per conversation
* 1,000 conversations costs about $3
* No monthly platform fees
* No per-seat costs

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/woo-ai-customer-service/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to 'AI Customer Chat' in the admin menu
4. Enter your Anthropic API key (get one at console.anthropic.com)
5. Configure your business information and widget settings
6. The chat widget will appear on your store frontend

== Frequently Asked Questions ==

= Where do I get an API key? =

Sign up at [console.anthropic.com](https://console.anthropic.com) to get your Anthropic API key.

= How much does the AI cost? =

Claude Haiku costs approximately $0.25/million input tokens and $1.25/million output tokens. A typical customer service conversation costs about $0.003.

= Can customers see their orders without logging in? =

Yes! The chatbot looks up orders by the email address provided in the lead form, so even guest customers can get order information.

= What tracking plugins are supported? =

The plugin automatically detects tracking information from:
* WooCommerce Shipment Tracking
* Advanced Shipment Tracking (AST)
* Custom order meta fields

= Is customer data secure? =

Yes. The plugin uses WordPress nonce verification, input sanitization, and encrypted API key storage. Customer data is only accessible within their chat session.

== Screenshots ==

1. Chat widget on storefront
2. Lead capture form
3. Active conversation with order information
4. Admin settings page
5. Leads management page

== Changelog ==

= 1.0.0 =
* Initial release
* AI-powered chat using Claude API
* Lead capture with name, email, phone
* WooCommerce order history integration
* Order tracking support
* Customizable widget appearance
* Admin leads management
* CSV export functionality

== Upgrade Notice ==

= 1.0.0 =
Initial release of WooCommerce AI Customer Service.
