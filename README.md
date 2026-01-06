# LLM Moderator for wpForo
AI-powered moderation using OpenRouter with standalone Moderator/Admin interface

## Description
### Important
This plugin is still in beta. **WARNING**: The code uploaded is untested, and maybe pushed to github ahead of time without required testing as that takes time to do. The code is released for reference purposes only. Bug fixes are appreciated.

LLM Moderator for wpForo is a WordPress plugin that integrates AI-powered content moderation with the wpForo forum plugin. It uses OpenRouter API to analyze forum posts and topics in real-time, automatically flagging inappropriate content and muting users who violate forum guidelines.
Get control over your moderation ✊ ✊ ✊. Moderate at mere pennies. During testing the average cost per request is recorded at USD $0.00005 using the default prompt and 20 word sentences, with DeekSeek-v3.1.


## Features

- **AI-Powered Moderation**: Uses OpenRouter API with configurable AI models (default: deepseek/deepseek-chat-v3.1)
- **Flexible Flagging System**: Customizable flag types (flag, nsfw, spam, etc.) with individual mute durations
- **User Management**: Automatically moves flagged users to a "Muted" wpForo group with customizable permissions
- **Standalone Admin Interface**: Accessible to both Administrators and Moderators with proper capability controls
- **Real-time Processing**: Analyzes posts and topics as they're submitted
- **Append AI Message to post**: Append a custom string with {TYPE} and {REASON} formatting tags for AI response to the original post or topic after the edit 
- **Content Cleanup**: Automatically handles unapproved content removal when users are unmuted
- **Scheduled Maintenance**: Daily cleanup of expired mutes and orphaned records

## Premium (coming soon)
**Premium**: Has an automatically updating premium version available for purchase that includes:
   - **Essential "Moderator" usergroup members' control panel** short-code page for your human moderators to manage admin page actions for AI muted users such as view muted users, un-mute muted users, view triggering post and approve, or delete it.
   - **Easy Prompt panel** to help you create llm prompts with organized structure  
   - **Forum flood control and user post limit** to stop excessive user posting resulting in excessive AI use
   
   **Purchase the premium**: The purchase for the premium features will be available soon (after we finish some primary tests for both the this and the premium versions). Your purchase is greatly appreciated because it supports me and my work.

## Screenshots
![Alt text](screenshots/1.png)
![Alt text](screenshots/2.png)
![Alt text](screenshots/3.png)
![Alt text](screenshots/4.png)
![Alt text](screenshots/5.png)
![Alt text](screenshots/6.png)
![Alt text](screenshots/7.png)

## Premium Version Screenshots
![Alt text](screenshots/p/1.png)
![Alt text](screenshots/p/2.png)
![Alt text](screenshots/p/3.png)
![Alt text](screenshots/p/4.png)
![Alt text](screenshots/p/5.png)


## Requirements

- WordPress 6.0+ - Tested 6.9
- wpForo plugin (active) - Tested through versions 2.4.8 - 2.4.13
- PHP 8.0+ - Tested 8.2
- MySQL 8.0+ - Tested 8.0
- OpenRouter API key 

### Automatic Moderation

The plugin automatically:
- Scans new or edited posts and topics for guideline violations
- Flags inappropriate content using AI analysis
- Users who violate guidelines can be muted for configured durations, the penalizing posts or topics are unapproved. Muted users will see an error notification that they are currently muted and can not post until their expiration time or the human moderators unmute them before then.
- Allows the admin to set different mute expiration times for flag types 
- Appends custom message with formatting tags for AI type and reason to the end of the post, topic, and edits after LLM analysis
- Can be used to just append the custom message at the end of the post body after AI analysis (without forced muting), or metrics (desired feature)
- Automatically removes expired muted users on cleanup and deletes the penalizing posts and topics if not previously un-muted by the human moderator and  before the mute expiration time. This deletes any pending unapproved post or topic
- Allows the admin to monitor the muted users' table, remove users, run cleanup, or see when next cleanup will occur (other human moderators can use the essential premium moderation page feature)
- Allows the web admin to customize the prompt to have the LLM select the most appropriate "type" tag for the post 

## Installation

1. Upload the plugin file to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **WPForo AI Moderation → Settings** in the admin menu
4. Add unmute permissions as needed

## Configuration

### Essential Setup

1. **OpenRouter API Configuration**:
   - Get an API key from [OpenRouter](https://openrouter.ai/)
   - Enter the API key in plugin settings
   - Configure your preferred AI model 
      Single model: deepseek/deepseek-chat-v3.1
      Model chain: deepseek/deepseek-chat-v3.1:x-ai/grok-beta:mistralai/mistral-7b-instruct
   - Free models are available on OpenRouter, but are not recommended. AI moderation is relatively cost efficient.
   - Set key usage limits as needed (recommended to set and modify key limit to avoid unwanted billing, and issues related to cyber crimes). Set up notification when usage limits are being reached.
   - If key is at limit or your account is out of credits, the moderation will simply be skipped

2. **Flag Types Setup**:
   - Configure different flag types (FLAG, NSFW, SPAM, etc.)
   - Set individual mute durations for each flag type
   - Enable/disable flag types as needed 
   - Append a custom message at the end of the post or topic body with formatting tags {TYPE} and {REASON} for AI response 

3. **Set up the Moderator user group**
   - See wpForo instructions on how to enable secondary groups for human moderators

### User Group Permissions

The plugin automatically manages permissions for different user roles. Capability can be assigned to different usergroups:
- **Administrators**: Full access to all moderation features
- **Moderators**: Access to muted users list and unmute capabilities (Must be assigned)

## Usage

### Manual Management

Administrators (and other human moderators with the premium plugin) can:
- View all currently muted users
- Manually unmute users ahead of schedule
- Easy navigate to the triggering post or topic, and approve it
- Run cleanup operations manually (admin only)

### API Response Format
The AI must respond in JSON format and it must contain the "type" key. Optionally the "reason" key. You must engineer the prompt to receive the response in valid JSON format.
Example of what JSON format response looks like, that is expected from the OpenRouter LLM model:
```json
{
  "type": "FLAG",
  "reason": "This is a brief explanation usually in 20 words or less (limited through prompt)"
}
```
**WARNING**: This plugin must request a JSON response with "type" key. The query from OpenRouter is expected to format to JSON. It is best to include in your prompt that you want a JSON response. Do not ask for different format response.

## Settings

### Main Configuration
- **OpenRouter API Key**: Your OpenRouter API key for AI access
- **Model Selection**: Choose your preferred AI model
      Single model: deepseek/deepseek-chat-v3.1
      Model chain: deepseek/deepseek-chat-v3.1:x-ai/grok-beta:mistralai/mistral-7b-instruct
- **Custom Prompt**: Override the default moderation prompt

### Flag Types Management
- Add/remove custom flag types
- Set individual mute durations per flag type
- Enable/disable flag types
- Enable or disable automatic AI determined muting for the flag type
- Set the mute duration or leave empty for fallback duration if flag type not specified
- Optional append a custom message at the end of the post body with AI formatting tags {TYPE} and {RESPONSE}
- Removing all flag types will repopulate with default flag types
**WARNING** If you do not want any moderation, deactivate the plugin. Otherwise it will query to the LLM regardless of the fact that all flags are disabled

## Database

The plugin creates a custom table `wp_wpforo_ai_muted_users` to track:
- Muted user records
- Post/topic associations
- Moderation reasons and types
- Mute durations and expiration times

## Development


### Hooks Available
- `wpforo_ai_plugin_activation`: Plugin activation hook
- `wpforo_ai_plugin_deactivation`: Plugin deactivation hook
- Custom capabilities for role management

### File Structure
- `wpforo-ai-moderation.php`: Main plugin file with all functionality
- Three sections 1. GUI 2. All required functions and logic 3. Chain-Of-Responsibly, step-by-step execution of events on wpForo hook.
- Configuration object for default settings
- Admin interface and database management

## Other

### Community Help Requested

**HELP WANTED!** 

This is an open-source project available on GitHub that is built upon several different, changing, and evolving ecosystems, most importantly wpForo. This means we need community updates to this free plugin to keep it running consistently with version updates. At the very least, I need you to join my telegram group to immediately update/notify me of changes, and provide help.

### Project Background

I, the developer, have put a lot of effort into creating this plugin, wanting to capitalize on the lack of moderation opportunity that is essential to my website, only to find out late that the gVectors team has been working on their own moderation plugin, with development starting the same month. Had I known, I wouldn't have made this.

But maybe that is a good thing for you. And not only that, there may be some considerable differences where this might be all you need. This is a super low cost, user-controlled moderation system. The wpForo AI suite may have more features, but this plugin still solves the critical need for AI moderation. This is effective, and free (except for the very low cost OpenRouter fees), and gives you control over your moderation needs ✊ ✊ ✊.

### Comparison with wpForo AI Suite

I was told by the gVectors team that their system is an "Enterprise" level AI suite with a RAG-based search system. Great - suppose a topic has 250 pages and a few thousand posts. They send the close and stale topics to train the AI for response. This is a moderation-only system that doesn't do robust search, but it still solves the issue of unwanted messages.

However, this plugin can do stuff beyond just moderation. It follows a "chain-of-responsibility" pattern, making it easy for developers to understand and expand upon. It can be used to add topic tags upon topic creation.

In short, this plugin can be expanded to do just about anything because of how data is passed between the "chain-points", which makes it rather easy to develop with for moderation purposes.

### Premium Features & Community Development

As noted, I created this with the intention to capitalize on a commercial opportunity, otherwise the project was completed very early on. This means I had the idea to create "premium features". I encourage the community to also develop premium features built upon this "backbone" plugin.

### Desired Features

**Before you start, check our telegram and ask whether someone else is working on the same features:**

- **Code Rail Guards**: If the official code is suddenly changed, try-catch blocks should be able to avert disaster and simply stop moderation while sending an alert to the admin that an error has occurred and they need to contact me or other helpful developers from the telegram group. (Priority - can't have the code crash websites after wpForo or other updates) Mostly implemented.

- **Don't Crash wpForo Guard**: Likewise, all code manipulating wpForo should use built-in wpForo methods such as for deletion, status change, usergroup permission assignments, etc.

- **Exception Notification System**: Some sort of notification system on exceptions, passing the wpForo version, to quickly let me know if someone had crashing issues. GDPR compliant, opt-in permission needed.

- **User Progress Notification**: Notification system to show AI moderation progress to user.

- **Testing Framework**: An independent code not running in WordPress but interacts with the browser. Mimics user actions - on-page, sign up, create topic, etc. Monitor for errors that occur during automatic test actions.

- **Context-Aware Moderation**: Provide a few preceding approved posts for LLM moderation for better context. The best way may be the "memory" feature that is currently not supported by OpenRouter, so there may be no need to build it out now. The idea would be to get the AI to request back for more context, up to a few posts if it doesn't have enough certainty. Posts will need to be sent with user ID, name, order, whether it is a reply, what posts it directly succeeds, and post content. Users will need to be kept in the loop with notices and shown LLM reasons why it wants more context. (Somewhat time-consuming and requires some LLM knowledge)

- **Metrics and Analytics**: Add data to new database tables for stats. Use new tables to show full forum penalties over the past X time, or tagged posts of the user. Categorize as "mute-able" or not. Charts possible after metrics integration. Can it be done with one table, or does it need two? (Relatively easy to implement)

- **AI Topic Tags**: Have the LLM provide a "tags" key if it is a topic, in the JSON response, then use those to set topic tags. (Easy to implement)


## Support

For support and bug reports, please create an issue, or create a pull request, or best contact @colaiasq or "Imre" on the official telegram group https://t.me/wpforo_ai.

## License

This plugin is released under the GPL v2 or later license.

## Version History

**Still in beta**

 New in Version 1.5.4:
- Async logging to be notified whether post or topic was successfully manipulated
- Error handling, prevent website from crashing if the version of wpForo is not compatible. 
- Try-Catch exception handling for everything, with logs. Lots of helpful emojis... more emojis still needed
- Organized code into sections, moved it together
- Still notifications, unable to implement better solution, but will need to try GUI next time

Version: 1.5.3
Requires Plugins: wpforo
Author: comingofflais.com

New in Version 1.5.3:
Works with wpForo 2.4.13, wordpress 6.9, php 8.2.27, MySQL 8.0.35
- Major changes:
- WPF based post and topic deletion and status change! (Deleting and status changing of first-post apparently applied to topic)
- But broken AI notices

New in Version 1.5.0:
- Major re-write of how the topic, post, topic-edit, post-edit are used with hooks.
- Added a global dictionary for pending topics, posts, and edits, to preserve data-results through the moderation chain of events/hooks
- Fixed bug where the LLM message could not be appended to post body

New in Version 1.4:
- Enable or disable informational error logging from dashboard
- Append a custom message at the bottom of the post with AI {TYPE} and {REASON} formatting tags. 

New in Version 1.34:
- Prompt types can now be set through the admin panel instead of hardcode predetermined types.
- Updated logging

New in Version 1.3:
- Prompt types can now be set through the admin panel instead of hardcode predetermined types.
- Updated logging

New in Version 1.2:
- Standalone admin menu item for AI Moderation
- Dedicated interface accessible to Moderators and Admins

New in Version 1.1:
- Added moderator/Admin capability checking system
- Custom capability 'wpforo_ai_can_access_moderation' for access control
- Support for wpForo Moderator and Admin user groups
- Automatic capability assignment to WordPress admin roles

