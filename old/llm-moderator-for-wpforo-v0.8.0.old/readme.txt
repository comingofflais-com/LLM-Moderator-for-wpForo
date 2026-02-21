=== LLM Moderator For wpForo ===
Contributors: colaiasq
Tags: llm, moderator, wpforo, ai, forum
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html 

AI-powered context-aware moderation for wpForo using OpenRouter with standalone Moderator/Admin interface.

== Description ==

This plugin integrates AI-powered context-aware content moderation with the wpForo forum plugin. It uses OpenRouter API to analyze forum posts and topics in real-time, automatically flagging inappropriate content and muting users who violate your custom forum moderation guidelines.

= Important Note =
This plugin is in beta. **Ready/rc but still an early release. It's recommend to backup your site before installing beta software.** 

= Base Features =
* **AI-Powered Moderation**: Uses OpenRouter API with configurable AI models (default: deepseek/deepseek-chat-v3.2)
* **Context Aware Moderation**: Analyzes posts with surrounding context (previous and next posts) for better accuracy
* **Private by default**: Considerable forum and user privacy by default when sending user data for analysis, and your data lives on your server
* **Flexible Flagging System**: Customizable flag types (flag, nsfw, spam, etc.) with individual mute durations
* **User Management**: Automatically moves flagged users to a "Muted" database table, prevents them from posting
* **Flags Only Supported**: Enable only for flag metrics, stealthy tag users without applying mute penalty
* **Standalone Admin Interface**: Accessible to both Administrators, Moderators, and assigned usergroups
* **Real-time Processing**: Analyzes posts and topics as they're submitted, set your OpenRouter query timeout limit
* **Append AI Message**: Append custom messages with {TYPE} and {REASON} formatting tags
* **Content Cleanup**: Automatically handles unapproved content removal
* **Scheduled Maintenance**: Daily cleanup of expired mutes
* **Metrics**: Track basic moderation statistics up to 1 year (5 years in premium)
* **Notifications**: Shortcode for user notifications about moderation and mute status

= Requirements =
* WordPress 6.0+ (Tested 6.9)
* wpForo plugin active (Tested current: 2.4.15, Previous versions tested 2.4.8 – 2.4.15)
* PHP 8.0+ (Tested 8.2)
* MySQL 8.0+ (Tested 8.0)
* OpenRouter API key

== Installation ==

1. **Automatic Installation**
    Plugins → Add New → Activate
    Note: If errors occur, deactivate, try using with the highest tested wpForo version
    Let me know of any issue you encounter through the Telegram (preferred way of contacting me)

2. **Post-Installation – Prompt and Flag type configuration**:
    * Navigate to **WPForo AI Moderation → Settings** in the admin menu
    * Set a prompt to request back a JSON object with 'type' and 'reason' keys
    * Set up flag types matching the types in the prompt, enable the flag types for moderation and metrics
    * Enable muting on the flag types, and set custom mute durations
    * Assign unmute group permission to wpForo usergroups

3. **Start Moderating**
    * Configure your OpenRouter API key and save in settings

== Shortcode ==

This plugin provides shortcodes for displaying moderation-related content on your site.

### Base Plugin Shortcode

**`[colaias_wpforo_ai_notices]`** – Displays user notifications about moderation and mute status

**Usage:**

    [colaias_wpforo_ai_notices top='30px' right='30%' width='40%']

**Parameters:**
* `top` – (Optional) Top position of the notification container. Default: '20px'
* `right` – (Optional) Right position of the notification container. Default: '20px'
* `width` – (Optional) Width of the notification container. Default: '350px'

**Placement:**
Add this shortcode to the `[wpforo]` page. The notifications will display information to users:
* When a user is muted hence cannot post
* When a user's post has been flagged
* Moderation status updates


== Frequently Asked Questions ==

= Why Choose LLM Moderator for wpForo? =
✊ **Take Control of Your Moderation** – Easy to set up. Full forum and user privacy by default, no data collection. Let AI handle the heavy lifting to automatically mute users, and delete unwanted posts (if unapproved) while you focus on community building.

💰 **Cost-Effective Solution** – Moderate at mere pennies per request, or use a free model. No subscription needed, pay based on usage. Muted users are prevented and do not add to LLM costs. Use OpenRouter's model chaining for best costs. During testing, the average cost per request was under USD $0.0001 using DeepSeek-v3.1.

🚀 **Real-Time Protection** – Get instant AI analysis of every post and topic, with surrounding post context, as they're submitted, preventing inappropriate content from ever appearing.

🔧 **Fully Customizable** – Tailor the moderation to your community's specific needs with customizable flag types, mute durations, and AI prompts.

📊 **Metrics** – Track moderation effectiveness with detailed statistics on flag types, muted users, and prevention rates. Includes robust charts and user search metrics in the premium.

🤖 **Powered by Cutting-Edge AI** – Leverage the vast list of AI models through OpenRouter, with support for model chaining and continuous improvements.

🏠 **Your Data, Your Server** – All LLM moderation data is stored on YOUR server, giving you complete control over whether local law enforcement can obtain data with a warrant (directly bypassing you without your knowledge), this lets you keep everything under your chosen jurisdiction and control.

= What is this plugin for? =
This is a moderation only plugin that mutes users who violate your custom rules and guidelines of your wpForo forum. Prevent trolls, and keep your forum clean. Additionally it can be dual used as anti-spam by default through prompt configuration. It can also just be used to track enabled only flags; it tracks flag and mute metrics of users. The premium version provides some expansions such as flood control and better metrics.

While it can, however, be custom coded for a some features beyond moderation, this plugin will not support extra features beyond moderation. If you are interested in more AI features, contact the developer, and check out the premium.

= What versions of wpForo are supported? =
Tested through wpForo versions 2.4.8 – 2.4.15. The plugin should work with newer versions, or may require updates. The latest version can also be broken on the older versions of wpForo. 

= Does this plugin track or send any data? =
No, only the user data post content is sent to OpenRouter for moderation purposes without any identifiable information.
Privacy commitment:
    🚫 No tracking
    🚫 No sending private user metadata to OpenRouter models, such as usernames, user-ID, email, post time, etc, only sends user posts' content
    🚫 No data selling
    🚫 No ads
    🚫👮 Local law enforcement can obtain data with a warrant (and directly bypass you without your knowledge), but this moderation plugin lets you keep everything on a server under your chosen jurisdiction and control
    🫵 See OpenRouter privacy policy (they are also very private by default, no logging or storing, additionally you can customize rules for your API key)

= What is OpenRouter and do I need an account? =
OpenRouter is a service that routes access to various AI models. Yes, you need an OpenRouter account and API key to use this plugin for moderation (but you do not need it for premium flood control, easy-prompt, metrics viewing). Sign up at [https://openrouter.ai/](https://openrouter.ai/).

= How much does it cost to use this plugin? =
The plugin itself is free. However, you need to pay for OpenRouter API usage. During testing, the average cost per request is about USD $0.00005 using DeepSeek-v3.1 model (DeepSeek-v3.2 is even cheaper).

The premium version is available for paying users.

= Can I use free AI models? =
Yes, free models are available on OpenRouter, but you can not use a free model with model-chaining. Moreover, AI moderation with OpenRouter is relatively cost effective and efficient with paid models that can be chained.

= What happens if my OpenRouter API key runs out of credits? =
If your key is at its limit or your account is out of credits, the moderation will simply be skipped. Make sure to enable OpenRouter alerts to monitor your usage.

= What happens if my forum comes under spam and troll attacks, will it cost me money? =
After an account is muted, their posts will not query to OpenRouter until they are unmuted. The premium plugin also has a flood control system for user and forum posting limits. Moreover, you can set a spending limit on your OpenRouter key to prevent overuse. Further blocking and anti-spam solutions maybe available from gVectors.

= How do I disable moderation temporarily? =
If you don't want any moderation, deactivate the plugin. Alternatively, you can remove/unsave your OpenRouter API key in the settings, doing so will retain the currently muted users until their mute expires.



= Will this be supported with future wpForo versions? =
The goal is to support it as long as possible. The base plugin is feature complete and shouldn't require more features that can break it. (If the community wants other AI features, they are easier to build out separately. Contact the developer for serious inquires.)
wpForo seldomly makes core updates, WordPress even promotes the source code in their "Development" section of the plugin, strongly suggesting they want the community to build with them for these OpenSource projects.
The main developer is, however, very busy with other web and android projects, but will take a look if the community reaches out directly on Telegram [https://t.me/wpforo_ai](https://t.me/wpforo_ai) (and ALERTS, remember this) as that is the quickest way to get in touch.
You can also directly participate in keeping the plugin up-to-date over on Github [https://github.com/comingofflais-com/LLM-Moderator-for-wpForo](https://github.com/comingofflais-com/LLM-Moderator-for-wpForo)
For additional support and bug reports please see the Github page.

= Can I customize the prompt per individual forum bases? =
No, the plugin has global moderation that is applied to all forums, however, this is a feature that can be built out. Feel free to add features as you like through Github pull requests. Make sure to discuss your idea with us on our Telegram before working on the changes. 

== Check out Premium 🚀 ==

✨ **Premium Version** ✨

Upgrade to the premium version for advanced features including:

🌟 **Enhanced Features:**
* 🛠️ **Enhanced Moderator Control Panel** – Bulk select management for efficient moderation
* 🎨 **Easy Prompt Engineering Interface** – Visual prompt builder
* 🌊 **Forum Flood Control System** – User and forum posting limits to prevent spam attacks
* 📊 **Comprehensive Premium Metrics** – 5-year data retention with detailed analytics
* 📈 **Charts & Graphs For Metrics** – Comprehensive visualizations with user table for metrics
* 🔎 **User Look-Up** – Look up individual users flag metrics
* 💎 **Lifetime Single Purchase** – No subscriptions, one-time payment for lifetime updates


💖 **Support Independent Development:**
Your purchase helps keep this plugin free and actively maintained! Support innovation in open-source AI moderation tools.

📧 **Interested in Premium?**
Contact us for early access and pricing:
* Telegram: [https://t.me/wpforo_ai](https://t.me/wpforo_ai)

== External Services ==

This plugin integrates with the OpenRouter API ([https://openrouter.ai/](https://openrouter.ai/)) to provide AI-powered content moderation for wpForo forums. When configured with a valid OpenRouter API key, the plugin sends moderation requests to analyze forum posts in real-time.

The query is made to this url: 'https://openrouter.ai/api/v1/chat/completions'

### Data Transmission and Privacy

**What Data Is Sent:**
- Only the user's post content is transmitted to OpenRouter
- A moderation prompt configured by the administrator precedes the post content
- No personally identifiable information (name, username, or user ID) is included in the post content
- Each post from non-muted users triggers exactly one API request

**Current Implementation:**
The plugin now includes Context Aware Moderation, which analyzes posts with surrounding context (previous and next posts) for better accuracy while maintaining user privacy with aliases. Usernames are replaced with aliases to protect user privacy while providing the AI with necessary conversational context.

### About OpenRouter

OpenRouter is an AI routing service that provides access to multiple LLM models from various providers. When using this plugin, your data passes through OpenRouter to the selected AI model provider.

**Important Links:**
- OpenRouter Privacy Policy: [https://openrouter.ai/privacy](https://openrouter.ai/privacy)
- OpenRouter Terms of Service: [https://openrouter.ai/terms](https://openrouter.ai/terms)  
- OpenRouter Data Collection Policy: [https://openrouter.ai/docs/guides/privacy/data-collection](https://openrouter.ai/docs/guides/privacy/data-collection)

**AI Provider Policies:**
Each LLM provider (such as OpenAI, Anthropic, DeepSeek, etc.) has its own data handling policies. For information on how different providers process data, see OpenRouter's provider logging documentation: [https://openrouter.ai/docs/guides/privacy/logging](https://openrouter.ai/docs/guides/privacy/logging)

### Optional Data Sharing

You may optionally send:
- Your site's URL (via HTTP-Referer header)
- Application title (via X-Title header)

These optional fields help OpenRouter rank applications and improve service quality.

### Technical Implementation

**API Request Structure:**
The plugin sends POST requests to OpenRouter ('https://openrouter.ai/api/v1/chat/completions') with the following JSON payload structure:

    $body = json_encode( [
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => $json_prompt],
        ],
        'max_tokens' => 1000, // Maximum tokens in response (50-80 is typically sufficient)
        'temperature' => 0.1,
        'response_format' => ['type' => 'json_object'],
    ] );

**Response Expectation:**
The plugin expects OpenRouter to return a JSON object containing moderation results with 'type' and 'reason' keys as specified in your moderation prompt.

**Configuration Options:**
- The plugin uses default provider selection parameters. For advanced routing configuration, see: [https://openrouter.ai/docs/guides/routing/provider-selection](https://openrouter.ai/docs/guides/routing/provider-selection), though these can not be changed from default besides what is already configured above
- You can customize request processing through the OpenRouter dashboard, including model selection and privacy policies on a per-key basis: [https://openrouter.ai/settings/privacy](https://openrouter.ai/settings/privacy)
- You can configure your key, and set limits and alerts as needed
- With this plugin, you can also set a timeout for the OpenRouter query from 10-300 seconds 

### Requirements
- A valid OpenRouter API key must be configured in the plugin settings
- Your OpenRouter account must have sufficient credits for API usage
- Your prompt must specifically ask for a JSON format response with 'type' key and optionally 'reason' key, no other formats are accepted

== Screenshots ==

1. Admin Settings Page – Shows OpenRouter API configuration and model selection
2. Flag Types Management – Interface for managing custom flag types and mute durations
3. Muted Users List – View of currently muted users with unmute options
4. Metrics Dashboard – Statistics on moderation activities and flag types
5. Notification Shortcode – User-facing notifications about moderation status
6. Prompt Configuration – Custom prompt engineering interface
7. Settings – Closer look at OpenRouter config
8. FLAG - Closer look at the flag type
9. Context Aware Moderation - Send custom amount of surrounding posts for context

== Changelog ==

= 0.7.5 =
* 1st published beta version (ready for moderation, requesting user feedback / bug reports)
* This is a beta release. Please backup your site before upgrading.
* The plugin is still in active development and may have breaking changes.
* Compatibility wpForo 2.4.15 (or higher)

= 0.7.4 =
* Compatibility with wpForo 2.4.14 (or higher)
* Security fixes
* Renaming
* Enqueued JS
* Updated README
* Minor refactoring, fixes

= 0.6.7 =
* Initial WordPress.org submit version

== Upgrade Notice ==

= 0.7.5 =
* This is a beta release. Please backup your site before upgrading.
* The plugin is still in active development and may have breaking changes.
