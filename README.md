# LLM Moderator for wpForo
AI-powered moderation using OpenRouter with standalone Moderator/Admin interface

## Description
Important: This plugin is still in beta, the code is released for reference purposes only. Bug fixes are appreciated.

LLM Moderator for wpForo is a WordPress plugin that integrates AI-powered content moderation with the wpForo forum plugin. It uses OpenRouter API to analyze forum posts and topics in real-time, automatically flagging inappropriate content and muting users who violate forum guidelines.

## Features

- **AI-Powered Moderation**: Uses OpenRouter API with configurable AI models (default: deepseek/deepseek-chat-v3-0324:free)
- **Flexible Flagging System**: Customizable flag types (flag, nsfw, spam, etc.) with individual mute durations
- **User Management**: Automatically moves flagged users to a "Muted" wpForo group with customizable permissions
- **Standalone Admin Interface**: Accessible to both Administrators and Moderators with proper capability controls
- **Real-time Processing**: Analyzes posts and topics as they're submitted
- **Content Cleanup**: Automatically handles unapproved content removal when users are unmuted
- **Scheduled Maintenance**: Daily cleanup of expired mutes and orphaned records

## Requirements

- WordPress 5.0+
- wpForo plugin (active)
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

2. **OpenRouter API Configuration**:
   - Get an API key from [OpenRouter](https://openrouter.ai/)
   - Enter the API key in plugin settings
   - Configure your preferred AI model

3. **Flag Types Setup**:
   - Configure different flag types (flag, nsfw, spam, etc.)
   - Set individual mute durations for each flag type
   - Enable/disable flag types as needed

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
- Mutes users who violate guidelines for configured durations
- Maintains a record of all moderation actions

### Manual Management

Administrators and Moderators can:
- View all currently muted users
- Manually unmute users ahead of schedule
- Monitor moderation statistics
- Run cleanup operations manually

### API Response Format

The AI expects responses in JSON format:
```json
{
  "type": "FLAG|OK|NSFW|SPAM",
  "reason": "Brief explanation (20 words or less)"
}
```

## Settings

### Main Configuration
- **OpenRouter API Key**: Your OpenRouter API key for AI access
- **Model Selection**: Choose your preferred AI model
- **Default Mute Duration**: Fallback duration if flag type not specified
- **Custom Prompt**: Override the default moderation prompt

### Flag Types Management
- Add/remove custom flag types
- Set individual mute durations per flag type
- Enable/disable flag types

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

**Version 1.3**
- Configurable flag types through admin panel
- Enhanced type-based moderation system

**Version 1.2**
- Standalone admin menu item
- Dedicated moderator interface

**Version 1.1**
- Moderator/Admin capability system
- wpForo user group integration
- Custom capability management

## Support

For support and bug reports, please check the plugin documentation or contact the development team.

## License

This plugin is released under the GPL v2 or later license.
