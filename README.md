# WooCommerce AI Customer Service

AI-powered customer service chatbot for WooCommerce stores using Claude API. Captures leads, answers customer questions, and provides order tracking assistance.

## Features

- **AI-Powered Chat**: Uses Claude API for intelligent, context-aware responses
- **Lead Capture**: Collects customer information before chat starts
- **Order Tracking**: Automatically pulls customer order history and status
- **Image Upload**: Customers can share product photos or invoices
- **Conversation History**: View all chat conversations in admin panel
- **Status Management**: Mark conversations as resolved, needs attention, or waiting
- **Email Notifications**: Get notified when new chats start
- **Mobile Responsive**: Full-screen chat on mobile devices
- **Customizable**: Brand colors, welcome messages, and widget position

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- Claude API key from [Anthropic](https://www.anthropic.com/)

## Installation

1. Download the latest release zip file
2. Go to **WordPress Admin > Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin

## Configuration

1. Go to **AI Chat Settings** in your WordPress admin menu
2. Enter your Claude API key
3. Configure your business information:
   - Business name
   - Support email
   - Support phone
   - Business hours
4. Customize the chat widget:
   - Primary/secondary colors
   - Widget position
   - Welcome message
5. Set up email notifications (optional)

## Usage

Once configured, the chat widget appears on your WooCommerce store. Customers:

1. Click the chat button
2. Enter their name and email
3. Start chatting with the AI assistant

The AI has access to:
- Customer's order history
- Product catalog information
- Your business policies and contact info

## Admin Features

### Leads Management
View and manage all captured leads at **AI Chat > Leads**

### Conversation History
View all chat conversations at **AI Chat > Conversations**
- Filter by status (active, needs attention, waiting, resolved)
- Search conversations
- View full chat transcripts
- Update conversation status

### Email Notifications
Configure notifications at **AI Chat Settings > Email Notifications**
- Enable/disable notifications
- Multiple recipient emails
- Notify on chat start
- Notify on each message (optional)

## Customization

### System Prompt
Customize the AI's behavior and knowledge by editing the system prompt in settings. Include:
- Product information
- Policies (returns, shipping, etc.)
- Brand voice guidelines
- FAQ answers

### Styling
The widget uses CSS custom properties for easy styling:
```css
#woo-ai-chat-container {
    --woo-ai-primary-color: #2d5a27;
    --woo-ai-secondary-color: #ffffff;
}
```

## License

GPL v2 or later

## Support

For issues or feature requests, please open a GitHub issue.
