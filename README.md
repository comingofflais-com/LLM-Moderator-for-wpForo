# LLM Moderator for wpForo
AI-powered moderation using OpenRouter with standalone Moderator/Admin interface

## Description
Important: This plugin is still in beta. WARNING: The code uploaded is untested, and maybe pushed to github ahead of time without required testing as that takes time to do. The code is released for reference purposes only. Bug fixes are appreciated.

LLM Moderator for wpForo is a WordPress plugin that integrates AI-powered content moderation with the wpForo forum plugin. It uses OpenRouter API to analyze forum posts and topics in real-time, automatically flagging inappropriate content and muting users who violate forum guidelines.

## Features

- **AI-Powered Moderation**: Uses OpenRouter API with configurable AI models (default: deepseek/deepseek-chat-v3.1)
- **Flexible Flagging System**: Customizable flag types (flag, nsfw, spam, etc.) with individual mute durations
- **User Management**: Automatically moves flagged users to a "Muted" wpForo group with customizable permissions
- **Standalone Admin Interface**: Accessible to both Administrators and Moderators with proper capability controls
- **Real-time Processing**: Analyzes posts and topics as they're submitted
- **Append AI Message to post**: Append a custom string with {TYPE} and {REASON} formatting tags for AI response to the original post or topic after the edit 
- **Content Cleanup**: Automatically handles unapproved content removal when users are unmuted
- **Scheduled Maintenance**: Daily cleanup of expired mutes and orphaned records
- **Premium**: Has an automatically updating premium version available for purchase that includes:
   - **Essential "Moderator" usergroup members' control panel** short-code page for your human moderators to manage admin page actions for AI muted users such as view muted users, un-mute muted users, view triggering post and approve, or delete it.
   - **Easy Prompt panel** to help you create llm prompts with organized structure  
   - **Forum flood control and user post limit** to stop excessive user posting resulting in excessive AI use
   **Purchase the premium**: The purchase for the premium features will be available soon at https://comingofflais.com (after we finish some primary tests for both the this and the premium versions). Your purchase is greatly appreciated because it supports me and my work.

## Requirements

- WordPress 5.0+
- wpForo plugin (active) tested up to version 2.4.10
- OpenRouter API key
- PHP 7.4+

## Installation

1. Upload the plugin file to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **WPForo AI Moderation → Settings** in the admin menu
4. Configure your OpenRouter API key and settings
5. Set up the "Muted" user group in wpForo (see Configuration section)

## Configuration

### Essential Setup

1. **wpForo "Muted" User Group**: 
   - Create a "Muted" user group in wpForo admin area
   - Enable "allow as secondary group" option
   - Remove read access from private forums for this group
   - Customize permissions to restrict muted users as needed
   - See wpForo instructions on how to enable secondary groups for human moderators

2. **OpenRouter API Configuration**:
   - Get an API key from [OpenRouter](https://openrouter.ai/)
   - Enter the API key in plugin settings
   - Configure your preferred AI model
   - Free models are available on OpenRouter, but are not recommended. AI moderation is relatively cost effective.

3. **Flag Types Setup**:
   - Configure different flag types (flag, nsfw, spam, etc.)
   - Set individual mute durations for each flag type
   - Enable/disable flag types as needed
   - Append a custom message at the end of the post or topic body with formatting tags for AI response {TYPE} and {REASON}

### User Group Permissions

The plugin automatically manages permissions for different user roles:
- **Administrators**: Full access to all moderation features
- **Moderators**: Access to muted users list and unmute capabilities
- Custom wpForo user groups can be granted unmute permissions through the settings interface

## Usage

### Automatic Moderation

The plugin automatically:
- Scans new posts and topics for guideline violations
- Flags inappropriate content using AI analysis
- Mutes users who violate guidelines for configured durations, and unapproves offending post/topic
- Appends custom message with formatting tags for AI type and reason to the end of the post/topic
- Can be used to just append the custom message after AI analysis without forced muting
- Automatically removes expired muted users if not un-muted by the human moderator before the mute expiration time, deletes any pending unapproved post/topic
- Allow the admin to monitor (other human moderators can use the premium moderation page) the muted users' table, remove users, or let the system auto un-mute users when their mute expires (this will delete any pending approval posts that initially got the user muted)
### Manual Management

Administrators (and other human moderators with the premium plugin) can:
- View all currently muted users
- Manually unmute users ahead of schedule
- Easy navigate to the triggering post or topic
- Run cleanup operations manually (admin only)

### API Response Format

The AI must respond in JSON format. Engineer the prompt to receive the response in valid JSON format.
```json
{
  "type": "FLAG",
  "reason": "Brief explanation usually in 20 words or less (limit through prompt)"
}
```
WARNING: This plugin will not function correctly if you do not request the response in the eligible format or use an AI model, such as ChatGPT, that is incapable of responding according the the prompt.

## Settings

### Main Configuration
- **OpenRouter API Key**: Your OpenRouter API key for AI access
- **Model Selection**: Choose your preferred AI model
- **Custom Prompt**: Override the default moderation prompt

### Flag Types Management
- Add/remove custom flag types
- Set individual mute durations per flag type
- Enable/disable flag types
- Enable or disable automatic AI determined muting for the flag type
- Set the mute duration or leave empty for fallback duration if flag type not specified
- Optional append a custom message at the end of the post body with AI formatting tags {TYPE} and {RESPONSE}

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
- Configuration object for default settings
- Admin interface and database management

## Version History

**Still in beta**

Version: 1.4.0
Requires Plugins: wpforo
Author: comingofflais.com

Make sure to add: ["*","wordpress"] in php stubs for the IDE

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


## Support

For support and bug reports, please create an issue, or create a pull request, or better contact @colaiasq on telegram or "Imre" from the https://comingofflais.com forum telegram group.

## License

This plugin is released under the GPL v2 or later license.
