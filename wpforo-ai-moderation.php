<?php
/*
Plugin Name: LLM Moderator for wpForo
Description: AI-powered moderation using OpenRouter with standalone Moderator/Admin interface

Version: 1.4.0
Requires Plugins: wpforo
Author: comingofflais.com

Make sure to add: ["*","wordpress"] in php stubs for the IDE

New in Version 1.4:
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
*/

// Configuration object for default values
$wpforo_ai_config = [
    'default_prompt' => "You are a forum moderator during a test of the website. The admin has assigned to you analyze the content and respond with a JSON object containing 'type' and 'reason' keys. 

RULES:
1. If the content contains the text \"[FLAGGED]\", set 'type' to 'FLAG'
2. If the content contains the text \"[REVIEW]\", set 'type' to 'REVIEW' 
3. If the content contains the text \"[ALLOW]\", set 'type' to 'ALLOW'

Provide a concise reason of 20 words or less in 'reason'. Always respond with valid JSON format only, no additional text.",
    'default_mute_duration' => 7,
    'default_model' => 'deepseek/deepseek-chat-v3.1',
    'can_log_info_errors' => false, // Set to true to enable informational error logging
    'flag_types' => [
        [
            'type' => 'FLAG',
            'enabled' => true,
            'shouldMute' => true,
            'muteDuration' => 1,
            'appendString' => '🤖 The AI moderator has flagged this post for the following reason: {REASON}'
        ],
        [
            'type' => 'REVIEW',
            'enabled' => true,
            'shouldMute' => false,
            'muteDuration' => 1,
            'appendString' => '🤖 The AI moderator has flagged this post as {TYPE} for the following reason {REASON}'
        ],
        [
            'type' => 'ALLOW',
            'enabled' => false,
            'shouldMute' => false,
            'muteDuration' => 1,
            'appendString' => ''
        ],

    ]
];



// Static class for reusable utilities
class WPFORO_AI_Utilities {

    // Helper function for informational logging
    public static function wpforo_ai_log_info($message) {
        global $wpforo_ai_config;
        $can_log_info = get_option('wpforo_ai_can_log_info', $wpforo_ai_config['can_log_info_errors']);
        if ($can_log_info) {
            error_log('Info ' . $message);
        }
    }
    
    /**
     * Check if a type should be flagged
     * 
     * @param string $type The type to check
     * @return bool True if type should be flagged
     */
    public static function should_check_type($type) {
        global $wpforo_ai_config;
        $flag_types = get_option('wpforo_ai_flag_types', $wpforo_ai_config['flag_types']);
        
        $type_lower = strtolower($type);
        
        foreach ($flag_types as $flag_type) {
            if (is_array($flag_type) && isset($flag_type['type']) && isset($flag_type['enabled'])) {
                if (strtolower($flag_type['type']) === $type_lower && $flag_type['enabled']) {
                    wpforo_ai_log_info('WPForo AI Moderation: The trigger is ENABLED for type ' . $type_lower);
                    return true;
                }
            }
        }
        wpforo_ai_log_info('WPForo AI Moderation: The trigger is DISABLED for ' . $type_lower);
        return false;
    }
    
    /**
     * Check if user is moderator or admin
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if user is moderator/admin
     */
    public static function is_moderator_or_admin($user_id = null) {
        if (empty($user_id)) {
            $user_id = get_current_user_id();
        }
        
        if (empty($user_id)) {
            return false;
        }
        
        // Check WordPress admin roles first (Administrator, Editor, etc.)
        $user = get_userdata($user_id);
        if ($user) {
            // Check if user has administrator capabilities or administrator role
            if (in_array('administrator', $user->roles) || user_can($user_id, 'manage_options')) {
                return true;
            }
        }
        
        // Check wpForo Moderator or Admin groups (if wpForo is active)
        if (function_exists('WPF')) {
            // Get user's wpForo group IDs (both primary and secondary)
            $group_ids = [];
            
            // Get primary group ID
            $primary_group_id = WPF()->member->get_groupid($user_id);
            if ($primary_group_id) {
                $group_ids[] = $primary_group_id;
            }
            
            // Get secondary group IDs
            $secondary_group_ids = WPF()->member->get_secondary_groupids($user_id, true);
            if (is_array($secondary_group_ids) && !empty($secondary_group_ids)) {
                $group_ids = array_merge($group_ids, $secondary_group_ids);
            }
            
            // Check if user is in any Moderator or Admin groups
            $usergroups = WPF()->usergroup->get_usergroups('full');
            foreach ($usergroups as $group) {
                if (in_array($group['groupid'], $group_ids)) {
                    // Check if group name contains "Moderator" or "Admin" (case-insensitive match)
                    $group_name = strtolower($group['name']);
                    if (strpos($group_name, 'moderator') !== false || strpos($group_name, 'admin') !== false) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
}

// Backward compatibility functions

function wpforo_ai_log_info($message) {
    WPFORO_AI_Utilities::wpforo_ai_log_info($message);
}

function wpforo_ai_should_check_type($type) {
    return WPFORO_AI_Utilities::should_check_type($type);
}

function wpforo_ai_is_moderator_or_admin($user_id = null) {
    return WPFORO_AI_Utilities::is_moderator_or_admin($user_id);
}

// Combined activation hook
register_activation_hook(__FILE__, 'wpforo_ai_plugin_activation');
register_deactivation_hook(__FILE__, 'wpforo_ai_plugin_deactivation');

function wpforo_ai_plugin_activation() {
    // Create muted users table
    wpforo_ai_create_muted_users_table();
    
    // Upgrade table if it already exists
    wpforo_ai_upgrade_muted_users_table();
    
    // Setup daily cleanup schedule
    wpforo_ai_schedule_cleanup();
    
    // Add unmute permission to Administrator group (group ID 1)
    wpforo_ai_manage_unmute_permission(1, 'add');
}

function wpforo_ai_plugin_deactivation() {
    // Clear cleanup schedule
    wpforo_ai_unschedule_cleanup();
    
    // Clean up our custom capabilities
    wpforo_ai_cleanup_capabilities();
}

function wpforo_ai_cleanup_capabilities() {
    // Remove the custom capability from all roles
    $roles = ['administrator', 'editor', 'author'];
    foreach ($roles as $role) {
        $role_obj = get_role($role);
        if ($role_obj) {
            $role_obj->remove_cap('wpforo_ai_can_access_moderation');
        }
    }
}

function wpforo_ai_upgrade_muted_users_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
    
    // Check if topic_id column exists
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    $topic_id_exists = false;
    $type_exists = false;
    $reason_exists = false;
    
    foreach ($columns as $column) {
        if ($column->Field == 'topic_id') {
            $topic_id_exists = true;
        }
        if ($column->Field == 'type') {
            $type_exists = true;
        }
        if ($column->Field == 'reason') {
            $reason_exists = true;
        }
    }
    
    if (!$topic_id_exists) {
        // Add topic_id column
        $wpdb->query("ALTER TABLE $table_name ADD topic_id BIGINT(20) UNSIGNED DEFAULT NULL AFTER post_id");
        $wpdb->query("ALTER TABLE $table_name ADD KEY topic_id (topic_id)");
        wpforo_ai_log_info('WPForo AI Moderation: Added topic_id column to muted users table');
    }
    
    if (!$type_exists) {
        // Add type column
        $wpdb->query("ALTER TABLE $table_name ADD type VARCHAR(50) DEFAULT 'flag' AFTER expiration_time");
        $wpdb->query("ALTER TABLE $table_name ADD KEY type (type)");
        wpforo_ai_log_info('WPForo AI Moderation: Added type column to muted users table');
    }
    
    if (!$reason_exists) {
        // Add reason column
        $wpdb->query("ALTER TABLE $table_name ADD reason TEXT DEFAULT NULL AFTER type");
        wpforo_ai_log_info('WPForo AI Moderation: Added reason column to muted users table');
    }
}

function wpforo_ai_create_muted_users_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
    
    wpforo_ai_log_info('WPForo AI Moderation: Using table: ' . $table_name . ' for muted user');
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        post_id BIGINT(20) UNSIGNED DEFAULT NULL,
        topic_id BIGINT(20) UNSIGNED DEFAULT NULL,
        post_content TEXT DEFAULT NULL,
        mute_time DATETIME NOT NULL,
        expiration_time DATETIME NOT NULL,
        type VARCHAR(50) DEFAULT 'flag',
        reason TEXT DEFAULT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY expiration_time (expiration_time),
        KEY topic_id (topic_id),
        KEY type (type)
    ) $charset_collate;";

    // Upgrade existing table if it exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    if ($table_exists) {
        wpforo_ai_upgrade_muted_users_table();
        return true;
    }
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Check if table already exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        error_log('WPForo AI Moderation: Table not found at wpforo_ai_create_muted_users_table. Attempting to create table: ' . $table_name);
        $result = dbDelta($sql);
        
        // Log the result for debugging
        if (is_wp_error($result)) {
            error_log('WPForo AI Moderation: Table creation failed - ' . $result->get_error_message());
            return false;
        } else {
        wpforo_ai_log_info('WPForo AI Moderation: Table creation result: ' . print_r($result, true));
            
            // Double check if table was actually created
            $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        error_log('WPForo AI Moderation: Table exists after creation attempt: ' . ($table_exists_after ? 'Yes' : 'No'));
            
            return $table_exists_after;
        }
    }
    
    wpforo_ai_log_info('WPForo AI Moderation: Table already exists');
    return true;
}

// Function to check if a flag type should mute users
function wpforo_ai_should_mute_for_type($flag_type) {
    global $wpforo_ai_config;
    $flag_types = get_option('wpforo_ai_flag_types', $wpforo_ai_config['flag_types']);
    
    $flag_type_lower = strtolower($flag_type);
    
    foreach ($flag_types as $type_config) {
        if (is_array($type_config) && isset($type_config['type']) && isset($type_config['enabled']) && 
            strtolower($type_config['type']) === $flag_type_lower && $type_config['enabled']) {
            
            if (isset($type_config['shouldMute'])) {
                wpforo_ai_log_info('WPForo AI Moderation: MUTE for type ' . $flag_type_lower . ' is ' . ($type_config['shouldMute'] ? 'true' : 'false'));
                return (bool)$type_config['shouldMute'];
            }
            else {
                error_log('WPForo AI Moderation: Error MUTE for type ' . $flag_type_lower . ' is not set. Returning true');
                return true;
            }
        }
    }
    
    // Fallback to true if flag type not found or not enabled
    error_log('WPForo AI Moderation: Unknown Error MUTE for type ' . $flag_type_lower . ' is not found in flag types. Returning true');
    return true;
}

// Function to get mute duration for a specific flag type
function wpforo_ai_get_mute_duration_for_type($flag_type) {
    global $wpforo_ai_config;
    $flag_types = get_option('wpforo_ai_flag_types', $wpforo_ai_config['flag_types']);
    
    $flag_type_lower = strtolower($flag_type);
    
    foreach ($flag_types as $type_config) {
        if (is_array($type_config) && isset($type_config['type']) && isset($type_config['enabled']) && 
            strtolower($type_config['type']) === $flag_type_lower && $type_config['enabled']) {
            
            // If shouldMute is false, return 0 (no mute)
            if (isset($type_config['shouldMute']) && !$type_config['shouldMute']) {
                return 0;
            }
            
            // Return the configured mute duration
            return isset($type_config['muteDuration']) ? (int)$type_config['muteDuration'] : get_option('wpforo_ai_mute_duration', $wpforo_ai_config['default_mute_duration']);
        }
    }
    
    // Fallback to configured mute duration if flag type not found or not enabled
    return get_option('wpforo_ai_mute_duration', $wpforo_ai_config['default_mute_duration']);
}

// Function to add or update muted user in database
function wpforo_ai_add_muted_user($user_id, $post_id = null, $post_content = null, $topic_id = null, $type = 'flag', $reason = '') {
    global $wpdb;
    
    global $wpforo_ai_config;
    $mute_duration = wpforo_ai_get_mute_duration_for_type($type);
    
    // If mute duration is less than 0, don't mute the user
    if ($mute_duration < 0) {
        error_log('WPForo AI Moderation: Unexpected error. Mute duration is less than 0 for type "' . $type . '", skipping user mute.');
        return false;
    }
    
    $mute_time = current_time('mysql');
    $expiration_time = date('Y-m-d H:i:s', strtotime("+$mute_duration days", strtotime($mute_time)));
    
    $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
    
    // Check if user already exists in muted users table
    $existing_record = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table_name WHERE user_id = %d", 
        $user_id
    ));
    
    if ($existing_record) {
        wpforo_ai_log_info('WPForo AI Moderation: Updating existing muted user record for user_id: ' . $user_id);
        
        // Update existing record
        $wpdb->update(
            $table_name,
            array(
                'post_id' => $post_id,
                'topic_id' => $topic_id,
                'post_content' => $post_content,
                'mute_time' => $mute_time,
                'expiration_time' => $expiration_time,
                'type' => $type,
                'reason' => $reason
            ),
            array('user_id' => $user_id),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        wpforo_ai_log_info('WPForo AI Moderation: Updated existing muted user record for user_id: ' . $user_id);
        return $existing_record->id;
    } else {
        wpforo_ai_log_info('WPForo AI Moderation: Inserting new muted user record for user_id: ' . $user_id);
        
        // Insert new record
        $result = $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'post_id' => $post_id,
                'topic_id' => $topic_id,
                'post_content' => $post_content,
                'mute_time' => $mute_time,
                'expiration_time' => $expiration_time,
                'type' => $type,
                'reason' => $reason
            ),
            array(
                '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            error_log('WPForo AI Moderation: Failed to insert muted user record for user_id: ' . $user_id . ', Error: ' . $wpdb->last_error);
        } else {
            wpforo_ai_log_info('WPForo AI Moderation: Successfully inserted new muted user record for user_id: ' . $user_id . ', Insert ID: ' . $wpdb->insert_id);
        }
        
        return $wpdb->insert_id;
    }
}

// Function to remove expired mutes and cleanup posts
function wpforo_ai_clean_expired_mutes() {
    global $wpdb;
    
    wpforo_ai_log_info('WPForo AI Moderation: Starting wpforo_ai_clean_expired_mutes cleanup');
    
    $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
    $current_time = current_time('mysql');
    
    wpforo_ai_log_info('WPForo AI Moderation: Current time: ' . $current_time);
    
    // Log a sample of expiration times for debugging
    $sample_expirations = $wpdb->get_results("SELECT expiration_time FROM $table_name ORDER BY expiration_time ASC LIMIT 5");
    if ($sample_expirations) {
        foreach ($sample_expirations as $sample) {
            wpforo_ai_log_info('WPForo AI Moderation: Sample expiration time: ' . $sample->expiration_time);
        }
    }
    
    // Get expired muted users (using a small buffer to handle floating point precision issues)
    $expired_users = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE expiration_time <= %s OR expiration_time <= DATE_SUB(%s, INTERVAL 1 MINUTE)",
        $current_time,
        $current_time
    ));
    
    wpforo_ai_log_info('WPForo AI Moderation: Found ' . count($expired_users) . ' expired muted users');
    
    if (!empty($expired_users)) {
        wpforo_ai_log_info('WPForo AI Moderation: Cleaning up ' . count($expired_users) . ' expired muted users');
        
        foreach ($expired_users as $user) {
            wpforo_ai_log_info('WPForo AI Moderation: Processing expired muted user ID: ' . $user->user_id . ', Mute ID: ' . $user->id);
            
            // Remove user from wpForo muted group if wpForo is available
            if (function_exists('WPF')) {
                wpforo_ai_log_info('WPForo AI Moderation: WPF() function exists, checking for muted groups');
                $usergroups = WPF()->usergroup->get_usergroups('full');
                $muted_group_id = false;
                
                foreach ($usergroups as $group) {
                    if (stripos($group['name'], 'Muted') !== false) {
                        $muted_group_id = $group['groupid'];
                        wpforo_ai_log_info('WPForo AI Moderation: Found muted group ID: ' . $muted_group_id);
                        break;
                    }
                }
                
                if (!$muted_group_id) {
                    error_log('WPForo AI Moderation: No muted group found in wpForo usergroups');
                }
                
                if ($muted_group_id) {
                    // Remove the muted group from the user's secondary groups
                    $user_secondary_groups = WPF()->member->get_secondary_groupids($user->user_id, true);
                    if (is_array($user_secondary_groups) && in_array($muted_group_id, $user_secondary_groups)) {
                        $updated_groups = array_diff($user_secondary_groups, [$muted_group_id]);
                        WPF()->member->set_secondary_groupids($user->user_id, $updated_groups);
                        wpforo_ai_log_info('WPForo AI Moderation: Removed user ' . $user->user_id . ' from muted group');
                    } else {
                        wpforo_ai_log_info('WPForo AI Moderation: User ' . $user->user_id . ' is not in muted group, skipping removal');
                    }
                }
            }
            
            // Delete the post only if it still exists and is still unapproved
            if ($user->post_id && function_exists('WPF')) {
                wpforo_ai_log_info('WPForo AI Moderation: Checking post ' . $user->post_id . ' during cleanup for user ' . $user->user_id);
                
                // Check if post still exists and is unapproved (status = 1)
                $post = WPF()->db->get_row($wpdb->prepare(
                    "SELECT status FROM " . WPF()->tables->posts . " WHERE postid = %d",
                    $user->post_id
                ));
                
                if ($post) {
                    wpforo_ai_log_info('WPForo AI Moderation: Post ' . $user->post_id . ' found with status: ' . $post->status);
                    
                    if ($post->status == 1) {
                        // Post exists and is still unapproved, delete it
                        $delete_result = WPF()->db->delete(
                            WPF()->tables->posts,
                            array('postid' => $user->post_id),
                            array('%d')
                        );
                        
                        if ($delete_result) {
                            wpforo_ai_log_info('WPForo AI Moderation: Successfully deleted unapproved post ' . $user->post_id . ' for expired muted user ' . $user->user_id);
                        } else {
                            error_log('WPForo AI Moderation: Failed to delete post ' . $user->post_id . ' for user ' . $user->user_id . ', Error: ' . WPF()->db->last_error);
                        }
                    } else {
                        wpforo_ai_log_info('WPForo AI Moderation: Post ' . $user->post_id . ' was approved (status: ' . $post->status . '), not deleting for user ' . $user->user_id);
                    }
                } else {
                    wpforo_ai_log_info('WPForo AI Moderation: Post ' . $user->post_id . ' no longer exists for user ' . $user->user_id);
                }
            } else if ($user->post_id) {
                wpforo_ai_log_info('WPForo AI Moderation: WPF() not available, cannot check post ' . $user->post_id . ' for user ' . $user->user_id);
            }
            
            // Delete the topic only if it still exists, is still unapproved, and user was muted for a topic
            if ($user->topic_id && function_exists('WPF')) {
                wpforo_ai_log_info('WPForo AI Moderation: Checking topic ' . $user->topic_id . ' for cleanup for user ' . $user->user_id);
                
                // Check if topic still exists and is unapproved (status = 1)
                $topic = WPF()->db->get_row($wpdb->prepare(
                    "SELECT status FROM " . WPF()->tables->topics . " WHERE topicid = %d",
                    $user->topic_id
                ));
                
                if ($topic) {
                    wpforo_ai_log_info('WPForo AI Moderation: Topic ' . $user->topic_id . ' found with status: ' . $topic->status);
                    
                    if ($topic->status == 1) {
                        // Topic exists and is still unapproved, delete it and all its posts
                        // First delete all posts in the topic
                        $posts_deleted = WPF()->db->delete(
                            WPF()->tables->posts,
                            array('topicid' => $user->topic_id),
                            array('%d')
                        );
                        
                        // Then delete the topic itself
                        $topic_deleted = WPF()->db->delete(
                            WPF()->tables->topics,
                            array('topicid' => $user->topic_id),
                            array('%d')
                        );
                        
                        if ($topic_deleted) {
                wpforo_ai_log_info('WPForo AI Moderation: Successfully deleted unapproved topic ' . $user->topic_id . ' and ' . $posts_deleted . ' posts for expired muted user ' . $user->user_id);
                            
                            // Delete topic subscriptions after successful topic deletion
                            $subscriptions_deleted = WPF()->sbscrb->delete( [ 'type' => 'topic', 'itemid' => $user->topic_id ] );
                            wpforo_ai_log_info('WPForo AI Moderation: Deleted ' . $subscriptions_deleted . ' subscriptions for topic ' . $user->topic_id);
                        } else {
                            error_log('WPForo AI Moderation: Failed to delete topic ' . $user->topic_id . ' for user ' . $user->user_id . ', Error: ' . WPF()->db->last_error);
                        }
                    } else {
                        wpforo_ai_log_info('WPForo AI Moderation: Topic ' . $user->topic_id . ' was approved (status: ' . $topic->status . '), not deleting for user ' . $user->user_id);
                    }
                } else {
                    wpforo_ai_log_info('WPForo AI Moderation: Topic ' . $user->topic_id . ' no longer exists for user ' . $user->user_id);
                }
            } else if ($user->topic_id) {
                wpforo_ai_log_info('WPForo AI Moderation: WPF() not available, cannot check topic ' . $user->topic_id . ' for user ' . $user->user_id);
            }
        }
        
        // Delete all expired records from muted users table (using same buffer for consistency)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE expiration_time <= %s OR expiration_time <= DATE_SUB(%s, INTERVAL 1 MINUTE)",
            $current_time,
            $current_time
        ));
        
        wpforo_ai_log_info('WPForo AI Moderation: Cleanup completed. Removed ' . count($expired_users) . ' expired muted users');
    }
}

// Schedule cleanup of expired mutes
add_action('wpforo_ai_job_cleanup', 'wpforo_ai_clean_expired_mutes');

function wpforo_ai_schedule_cleanup() {
    if (!wp_next_scheduled('wpforo_ai_job_cleanup')) {
        wp_schedule_event(time(), 'hourly', 'wpforo_ai_job_cleanup');
    }
}

function wpforo_ai_unschedule_cleanup() {
    wp_clear_scheduled_hook('wpforo_ai_job_cleanup');
}

// Function to get append message for flag type
function wpforo_ai_get_append_message($flag_type, $type, $reason) {
    global $wpforo_ai_config;
    
    try {
        $flag_types = get_option('wpforo_ai_flag_types', $wpforo_ai_config['flag_types']);
        
        // Assume the flag type exists and is enabled (since this is called from should_check_type context)
        $flag_type_lower = strtolower($flag_type);
        
        foreach ($flag_types as $type_config) {
            if (is_array($type_config) && isset($type_config['type']) && 
                strtolower($type_config['type']) === $flag_type_lower) {
                
                if (isset($type_config['appendString']) && !empty(trim($type_config['appendString']))) {
                    $message = $type_config['appendString'];
                    // Replace placeholders with actual values
                    $message = str_replace('{TYPE}', $type, $message);
                    $message = str_replace('{REASON}', $reason, $message);
                    return $message;
                }
                break;
            }
        }
    } catch (Exception $e) {
        error_log('WPForo AI Moderation: Error getting append message for type ' . $flag_type . ': ' . $e->getMessage());
    }
    
    return '';
}

// Function to check if wpForo user group exists
function check_wpforo_usergroup_exists( $group_name ) {
    global $wpdb;
    
    // Check if the table exists before running a query
    $table_name = $wpdb->prefix . 'wpforo_usergroups';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
        return false;
    }

    // Prepare and run the query
    $query = $wpdb->prepare(
        "SELECT groupid FROM $table_name WHERE name = %s",
        $group_name
    );
    $group_id = $wpdb->get_var( $query );

    // Return the group ID or false
    return $group_id ? (int) $group_id : false;
}



// Function to manage unmute permission for any usergroup
function wpforo_ai_manage_unmute_permission($group_id, $action = 'add') {
    if (!function_exists('WPF')) {
        return false;
    }
    
    // Get the specified group
    $group = WPF()->usergroup->get_usergroup($group_id);
    if (!$group) {
        error_log('WPForo AI Moderation: Group ' . $group_id . ' not found');
        return false;
    }
    
    // Get current permissions
    $group_cans = $group['cans'];
    if (is_string($group_cans)) {
        $group_cans = unserialize($group_cans);
    }
    if (!is_array($group_cans)) {
        $group_cans = array();
    }
    
    if ($action === 'add') {
        // Add unmute permission
        $group_cans['wpforo_ai_can_unmute'] = 1;
        $message = 'Added unmute permission to group ' . $group_id;
    } else {
        // Remove unmute permission
        unset($group_cans['wpforo_ai_can_unmute']);
        $message = 'Removed unmute permission from group ' . $group_id;
    }
    
    // Update the usergroup in database
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'wpforo_usergroups',
        array('cans' => serialize($group_cans)),
        array('groupid' => $group_id),
        array('%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wpforo_ai_log_info('WPForo AI Moderation: ' . $message);
        
        // Clear wpForo cache to ensure updated permissions are immediately visible
        if (function_exists('WPF')) {
            if (isset(WPF()->ram_cache) && method_exists(WPF()->ram_cache, 'reset')) {
                // Clear the entire RAM cache to ensure usergroups are refreshed
                WPF()->ram_cache->reset();
            }
        }
        
        return true;
    } else {
        error_log('WPForo AI Moderation: Failed to update group ' . $group_id);
        return false;
    }
}

// === CAPABILITY MANAGEMENT ===

/**
 * Register our custom capability and assign it to appropriate WordPress roles
 * This runs on WordPress init to ensure roles are available
 */
add_action('init', 'wpforo_ai_register_capabilities');

function wpforo_ai_register_capabilities() {
    /**
     * Assigns the 'wpforo_ai_can_access_moderation' capability to WordPress roles
     * that should have access to the moderation interface
     */

     
    
    // Get the administrator role - always gets access
    $admin_role = get_role('administrator');
    if ($admin_role) {
        if (!$admin_role->has_cap('wpforo_ai_can_access_moderation')) {
            $admin_role->add_cap('wpforo_ai_can_access_moderation');
            wpforo_ai_log_info('WPForo AI Moderation: Custom capabilities registered for admin roles');
            return;
        }
        else {
            wpforo_ai_log_info("WPForo AI Moderation: Init Admin role already assigned custom capabilities.");
            return;
        }
    }
    
    // Get the editor role (often used for moderators in WordPress)
    $editor_role = get_role('editor');
    if ($editor_role) {
        if (!$editor_role->has_cap('wpforo_ai_can_access_moderation')){ 
            $editor_role->add_cap('wpforo_ai_can_access_moderation');
            wpforo_ai_log_info('WPForo AI Moderation: Custom capabilities registered for editor roles');
            return;
        }
        else {
            wpforo_ai_log_info("WPForo AI Moderation: Init Editor role already assigned custom capabilities.");
            return;
        }
    }
    
    // Add capability to author role as well (can be customized as needed)
    $author_role = get_role('author');
    if ($author_role) {
        if (!$author_role->has_cap('wpforo_ai_can_access_moderation')) {
            $author_role->add_cap('wpforo_ai_can_access_moderation');
            wpforo_ai_log_info('WPForo AI Moderation: Custom capabilities registered for author roles');
            return;
        }
        else {
            wpforo_ai_log_info("WPForo AI Moderation: Init Author role already assigned custom capabilities.");
            return;
        }
    }
    
    wpforo_ai_log_info('WPForo AI Moderation: Custom capabilities init for unauthorized role');
}

add_action('admin_menu', function () {
    // Add as a top-level menu item instead of under Settings
    add_menu_page(
        'LLM Moderator for WPForo',      // Page title
        'AI Moderation',                 // Menu title
        'wpforo_ai_can_access_moderation', // Capability
        'wpforo-ai-moderation',          // Menu slug
        'llm_settings_page',             // Callback function
        'dashicons-shield',              // Icon (using shield icon for moderation)
        30                               // Position (after Comments at 25, before Appearance at 60)
    );
    
    // Also add the original submenu item to maintain backward compatibility
    add_submenu_page(
        'wpforo-ai-moderation',          // Parent slug
        'LLM Moderator for WPForo',      // Page title
        'Moderation Settings',           // Menu title
        'wpforo_ai_can_access_moderation', // Capability
        'wpforo-ai-moderation',          // Menu slug
        'llm_settings_page'              // Callback function
    );
});

// Custom capability check for accessing the moderation page (for wpForo groups)
add_filter('user_has_cap', 'wpforo_ai_custom_capability_check', 10, 4);


function wpforo_ai_custom_capability_check($allcaps, $caps, $args, $user) {
    /**
     * WordPress capability filter for the 'wpforo_ai_can_access_moderation' capability
     * 
     * This filter dynamically grants the custom capability to users who:
     * 1. Already have the capability explicitly assigned (via WordPress roles)
     * 2. Have the 'manage_options' capability (WordPress administrators)
     * 3. Are identified as wpForo Moderators or Admins via the helper function
     * 
     * @param array $allcaps All capabilities the user has
     * @param array $caps The capabilities being checked
     * @param array $args Additional arguments
     * @param WP_User $user The user object
     * @return array Modified capabilities array
     */
    
    // Check if we're dealing with our custom capability
    if (in_array('wpforo_ai_can_access_moderation', $caps)) {
        // If user already has the capability explicitly assigned, allow access
        if (isset($allcaps['wpforo_ai_can_access_moderation']) && $allcaps['wpforo_ai_can_access_moderation']) {
            return $allcaps;
        }
        
        // If user has manage_options, always allow access (WordPress admins)
        if (isset($allcaps['manage_options']) && $allcaps['manage_options']) {
            $allcaps['wpforo_ai_can_access_moderation'] = true;
            return $allcaps;
        }
        
        // Check if user is a wpForo Moderator or Admin using our helper function
        if (wpforo_ai_is_moderator_or_admin($user->ID)) {
            $allcaps['wpforo_ai_can_access_moderation'] = true;
            return $allcaps;
        }
    }
    
    return $allcaps;
}

function llm_settings_page(){
    // Check which tab is active
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
    
    // Check if premium plugin is active
    $premium_plugin_active = function_exists('wpforo_ai_premium_init');
    if (!$premium_plugin_active) {
        $premium_plugins = get_option('active_plugins');
        $premium_plugin_slug = 'wpforo-ai-premium/wpforo-ai-premium-moderation.php';
        $premium_plugin_active = in_array($premium_plugin_slug, $premium_plugins);
    }
    
    // Display premium notification
    if (!$premium_plugin_active) {
        echo '<div class="notice notice-info is-dismissible" style="border-left-color: #0073aa; background: #f0f9ff; padding: 15px; margin: 20px 0;">';
        echo '<h3 style="margin-top: 0; color: #0073aa;">💎 Premium Features Available!</h3>';
        echo '<p>🤖 Enhance your moderation capabilities with our premium plugin which includes:</p>';
        echo '<ul style="margin: 10px 0 15px 0;">';
        echo '<li>Moderator mute control panel page shortcode</li>';
        echo '<li>Easy prompt generation panel</li>';
        echo '<li>Advanced flood control system</li>';
        echo '</ul>';
        echo '<p><strong>Get it now at: <a href="https://insertmywebsitehere.com/shop" target="_blank" style="color: #0073aa; text-decoration: underline;">insertmywebsitehere.com/shop</a></strong></p>';
        echo '<p>Features automatic new version upgrade notification in Dashboard -> Plugins</p>';
        echo '</div>';
    } else {
        echo '<div class="notice notice-success is-dismissible" style="border-left-color: #46b450; background: #f0fff0; padding: 15px; margin: 20px 0;">';
        echo '<h3 style="margin-top: 0; color: #46b450;">🎉 Thank You for Using Premium!</h3>';
        echo '<p>🤖 Your active participation in development is greatly appreciated. Enjoy the enhanced moderation features!</p>';
        echo '</div>';
    }
    
    // Handle manual permission fix
        // Handle permission management actions
        if (isset($_GET['action']) && isset($_GET['group_id']) && isset($_GET['_wpnonce'])) {
            $group_id = intval($_GET['group_id']);
            
            if ($_GET['action'] === 'add_unmute_permission' && wp_verify_nonce($_GET['_wpnonce'], 'wpforo_ai_add_unmute_' . $group_id)) {
                if (wpforo_ai_manage_unmute_permission($group_id, 'add')) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('Added unmute permission to group ID %d!', 'wpforo-ai-moderation'), $group_id) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(__('Failed to add unmute permission to group ID %d!', 'wpforo-ai-moderation'), $group_id) . '</p></div>';
                }
            }
            
            if ($_GET['action'] === 'remove_unmute_permission' && wp_verify_nonce($_GET['_wpnonce'], 'wpforo_ai_remove_unmute_' . $group_id)) {
                if (wpforo_ai_manage_unmute_permission($group_id, 'remove')) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('Removed unmute permission from group ID %d!', 'wpforo-ai-moderation'), $group_id) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(__('Failed to remove unmute permission from group ID %d!', 'wpforo-ai-moderation'), $group_id) . '</p></div>';
                }
            }
        }
    
    // Handle form submission, set the new settings from the admin panel on submit
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpforo_ai_nonce']) && wp_verify_nonce($_POST['wpforo_ai_nonce'], 'wpforo_ai_save_settings')) {
        // Only allow administrators to save settings
        if (current_user_can('manage_options')) {
            // Save all settings
            $settings = [
                'openrouter_api_key' => 'sanitize_text_field',
                'openrouter_model' => 'sanitize_text_field',
                'wpforo_ai_moderation_prompt' => function($value) { 
                    // Simple sanitization that removes any existing slashes and preserves content
                    if (function_exists('wp_kses_post')) {
                        $value = wp_kses_post($value);
                    }
                    $value = wp_check_invalid_utf8($value);
                    return trim($value);
                },
                'wpforo_ai_mute_duration' => 'absint',
                'wpforo_ai_can_log_info' => function($value) { return filter_var($value, FILTER_VALIDATE_BOOLEAN); },
            ];
            
            // Handle flag types saving
            if (isset($_POST['wpforo_ai_flag_types'])) {
                $flag_types = [];
                $posted_types = $_POST['wpforo_ai_flag_types'];
                
                foreach ($posted_types as $index => $type_data) {
                    if (!empty($type_data['type'])) {
                        $flag_types[] = [
                            'type' => sanitize_text_field($type_data['type']),
                            'enabled' => isset($type_data['enabled']) ? (bool)$type_data['enabled'] : false,
                            'shouldMute' => isset($type_data['shouldMute']) ? (bool)$type_data['shouldMute'] : false,
                            'muteDuration' => isset($type_data['muteDuration']) ? absint($type_data['muteDuration']) : 7,
                            'appendString' => isset($type_data['appendString']) ? sanitize_textarea_field($type_data['appendString']) : ''
                        ];
                    }
                }
                
                update_option('wpforo_ai_flag_types', $flag_types);
                // Log the complete flag types configuration as JSON
                $flag_types_json = json_encode($flag_types, JSON_PRETTY_PRINT);
                wpforo_ai_log_info("WPForo AI Moderation: Flag types configuration:\n" . $flag_types_json);
                wpforo_ai_log_info("WPForo AI Moderation: Saved " . count($flag_types) . " flag types configuration");
            }
            else {
                global $wpforo_ai_config;
                $flag_types = $wpforo_ai_config['flag_types'];
                update_option('wpforo_ai_flag_types', $flag_types);
            }
            
            foreach ($settings as $key => $sanitizer) {
                // Handle checkbox fields (like wpforo_ai_can_log_info) - if not in POST, set to false
                if (!isset($_POST[$key]) && strpos($key, 'wpforo_ai_can_log_info') !== false) {
                    $value = false;
                    update_option($key, $value);
                    wpforo_ai_log_info("WPForo AI Moderation: Saved setting - {$key}: false (unchecked)");
                    continue;
                }
                
                if (isset($_POST[$key])) {
                    $value = $_POST[$key];
                    if (is_callable($sanitizer)) {
                        $value = call_user_func($sanitizer, $value);
                    } else {
                        $value = call_user_func($sanitizer, $value);
                    }
                    update_option($key, $value);
                    // Log the setting change with more detail
                    if ($key === 'wpforo_ai_moderation_prompt') {
                        wpforo_ai_log_info("WPForo AI Moderation: Saved prompt setting (truncated): " . substr($value, 0, 100) . "...");
                    } else if (is_bool($value) ){
                        $log_value =  $value ? 'true' : 'false';
                        wpforo_ai_log_info("WPForo AI Moderation: Saved setting - {$key}: " . $log_value);
                    }
                    else if ($key === 'openrouter_api_key'){
                        wpforo_ai_log_info("WPForo AI Moderation: Saved $key: " . substr($value, 0, 15)  );
                    }
                    else {
                        wpforo_ai_log_info("WPForo AI Moderation: Saved $key: " . $value );
                    }
                }
            }
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wpforo-ai-moderation') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('You do not have permission to save settings.', 'wpforo-ai-moderation') . '</p></div>';
        }
    }
    

    
    // Check if "Muted" user group exists
    $muted_group_exists = false;
    $group_name = 'Muted';
    $group_id = check_wpforo_usergroup_exists( $group_name );

    if ( $group_id ) {
        $muted_group_exists = true;
    }
    
    // Handle unmute action
    if (isset($_GET['action']) && $_GET['action'] === 'unmute' && isset($_GET['user_id']) && isset($_GET['_wpnonce'])) {
        if (wp_verify_nonce($_GET['_wpnonce'], 'wpforo_ai_unmute_' . $_GET['user_id'])) {
            $user_id = intval($_GET['user_id']);
            wpforo_ai_unmute_user($user_id);
            
            // Redirect back to muted users page without action parameters
            wp_redirect(add_query_arg(array('page' => 'wpforo-ai-moderation', 'tab' => 'muted_users'), admin_url('admin.php')));
            
        }
    }
    
    ?>
    <div class="wrap">
        <h1>🤖 WPForo AI Moderation plugin</h1>
        
        <h2 class="nav-tab-wrapper">
            <?php if (current_user_can('manage_options')): ?>
                <a href="<?php echo add_query_arg('tab', 'settings'); ?>" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
            <?php endif; ?>
            <a href="<?php echo add_query_arg('tab', 'muted_users'); ?>" class="nav-tab <?php echo $active_tab === 'muted_users' ? 'nav-tab-active' : ''; ?>">Muted Users</a>
        </h2>
        
        <?php if ($active_tab === 'settings'): ?>
        
            <div style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                <h3>User Group Permissions Management</h3>
                <p><strong>Manage which user groups can unmute members:</strong> Use this section to add or remove the unmute permission from any wpForo user group.</p>
                
                <?php if (function_exists('WPF')): ?>
                    <?php
                    $usergroups = WPF()->usergroup->get_usergroups('full');
                    if (!empty($usergroups)):
                    ?>
                    <table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>Group ID</th>
                                <th>Group Name</th>
                                <th>Current Permission</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usergroups as $group): ?>
                            <?php
                            // Check if this group has the unmute permission
                            $group_cans = $group['cans'];
                            if (is_string($group_cans)) {
                                $group_cans = unserialize($group_cans);
                            }
                            $has_permission = is_array($group_cans) && isset($group_cans['wpforo_ai_can_unmute']) && $group_cans['wpforo_ai_can_unmute'];
                            ?>
                            <tr>
                                <td><?php echo esc_html($group['groupid']); ?></td>
                                <td><?php echo esc_html($group['name']); ?></td>
                                <td>
                                    <?php if ($has_permission): ?>
                                        <span style="color: green; font-weight: bold;">✅ Has Permission</span>
                                    <?php else: ?>
                                        <span style="color: #666;">❌ No Permission</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($has_permission): ?>
                                        <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'remove_unmute_permission', 'group_id' => $group['groupid'])), 'wpforo_ai_remove_unmute_' . $group['groupid']); ?>" class="button button-small" onclick="return confirm('Are you sure you want to remove unmute permission from this group?')">
                                            Remove Permission
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo wp_nonce_url(add_query_arg(array('action' => 'add_unmute_permission', 'group_id' => $group['groupid'])), 'wpforo_ai_add_unmute_' . $group['groupid']); ?>" class="button button-small button-primary">
                                            Add Permission
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p>No wpForo user groups found.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p>wpForo is not active or not installed.</p>
                <?php endif; ?>
            </div>
        
            <?php if ($muted_group_exists): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong style="color: green;">✅ Great. "Muted" usergroup exists!</strong> This is where muted users will be placed when flagged by the 🤖 AI moderation system.<br>🛑Ensure "allow as secondary group" is checked for the "Muted" usergroup, and read access from private forums is removed by editing the private forum category/topics for this new group. When adding Moderators, place them in usergroup "Moderator".<br>❗IMPORTANT: Remove read access from private forums by editing the private forum category/topics for the "Muted" group, otherwise muted users will be able to see the contents of a private topic.<br>ℹ️ Follow wpForo's instructions on how to enable adding users to secondary usergroups before assigning moderators. </p>
                </div>
            <?php else: ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong style="color: red;">❌ CRITICAL: "Muted" usergroup not found!</strong> The plugin cannot function properly without the wpForo user group "Muted". <br> Create a "Muted" usergroup through the <u>wpForo->Usergroups</u> admin section of the WordPress admin panel. Make sure to "allow as secondary group" and customize user permissions as needed.<br>Then remove read access from private forums by editing the private forum category/topics for this new group.</p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
            <?php wp_nonce_field('wpforo_ai_save_settings', 'wpforo_ai_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="wpforo_ai_can_log_info">Enable Informational Logging</label></th>
                    <td>
                        <?php 
                        global $wpforo_ai_config;
                        $value = get_option('wpforo_ai_can_log_info', $wpforo_ai_config['can_log_info_errors']);
                        $bool_value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        // Debug output
                        error_log("wpforo_ai_can_log_info debug - Raw value: '" . $value . "', Boolean value: " . ($bool_value ? 'true' : 'false'));
                        ?>
                        <input type="checkbox" name="wpforo_ai_can_log_info" id="wpforo_ai_can_log_info" value="1" <?php checked($bool_value, true); ?>>
                        <p class="description">Enable informational logging in Debug.log for debugging purposes, or to follow the moderation process step-by-step (Non error)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openrouter_api_key">OpenRouter API Key</label></th>
                    <td>
                        <?php $value = get_option('openrouter_api_key', ''); ?>
                        <input type="password" name="openrouter_api_key" id="openrouter_api_key" value="<?php echo esc_attr($value); ?>" size="50">
                        <p class="description">Your OpenRouter API key</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="openrouter_model">Model</label></th>
                    <td>
                        <?php 
                        global $wpforo_ai_config;
                        $value = get_option('openrouter_model', $wpforo_ai_config['default_model']);
                        ?>
                        <input type="text" name="openrouter_model" id="openrouter_model" value="<?php echo esc_attr($value); ?>" size="50">
                        <p class="description">🤖 Model (e.g. deepseek/deepseek-chat-v3.1, <?php echo esc_html($wpforo_ai_config['default_model']); ?>)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label>Flag Types Configuration</label></th>
                    <td>
                        <?php 
                        global $wpforo_ai_config;
                        $flag_types = get_option('wpforo_ai_flag_types', $wpforo_ai_config['flag_types']);
                        ?>
                        <div id="flag-types-container" style="margin-bottom: 20px;">
                            <?php foreach ($flag_types as $index => $flag_type): ?>
                            <div class="flag-type-row" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9;">
                                <input type="hidden" name="wpforo_ai_flag_types[<?php echo $index; ?>][index]" value="<?php echo $index; ?>">
                                
                                <div style="margin-bottom: 10px;">
                                    <label style="display: inline-block; width: 120px; font-weight: bold;">Flag Type:</label>
                                    <input type="text" name="wpforo_ai_flag_types[<?php echo $index; ?>][type]" value="<?php echo esc_attr($flag_type['type']); ?>" placeholder="e.g., flag, nsfw, spam" style="width: 200px;">
                                </div>
                                
                                <div style="margin-bottom: 10px;">
                                    <label style="display: inline-block; width: 120px; font-weight: bold;">Enabled:</label>
                                    <input type="checkbox" name="wpforo_ai_flag_types[<?php echo $index; ?>][enabled]" value="1" <?php checked($flag_type['enabled'], true); ?>>
                                    <span class="description">Enable this flag type for moderation</span>
                                </div>
                                
                                <div style="margin-bottom: 10px;">
                                    <label style="display: inline-block; width: 120px; font-weight: bold;">Should Mute:</label>
                                    <input type="checkbox" name="wpforo_ai_flag_types[<?php echo $index; ?>][shouldMute]" value="1" <?php checked($flag_type['shouldMute'], true); ?>>
                                    <span class="description">Mute users when this flag type is triggered</span>
                                </div>
                                
                                <div style="margin-bottom: 10px;">
                                    <label style="display: inline-block; width: 120px; font-weight: bold;">Mute Duration (days):</label>
                                    <input type="number" name="wpforo_ai_flag_types[<?php echo $index; ?>][muteDuration]" value="<?php echo esc_attr($flag_type['muteDuration']); ?>" min="0" max="365" style="width: 80px;">
                                    <span class="description">Days to mute for this specific flag type</span>
                                </div>
                                
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: inline-block; width: 120px; font-weight: bold;">Append Message:</label>
                                        <input type="text" name="wpforo_ai_flag_types[<?php echo $index; ?>][appendString]" value="<?php echo esc_attr($flag_type['appendString']); ?>" placeholder="Leave blank for no message. Use {TYPE} and {REASON} for LLM response formatting tags (case sensitive)." style="width: 700px; font-size: 14px;">
                                        <div class="description" style="margin-top: 5px; max-width: 400px;">
                                            Message to append at the end of flagged content. The {TYPE} and {REASON} formatting tags (case sensitive) will be automatically replaced with the actual values from the LLM AI moderator response.
                                        </div>
                                    </div>
                                
                                <button type="button" class="button button-small remove-flag-type" style="color: #dc3232; border-color: #dc3232;">Remove</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="button" id="add-flag-type" class="button button-secondary">Add New Flag Type</button>
                        
                        <script>
                        jQuery(function($) {
                            $('#add-flag-type').on('click', function() {
                                var index = $('.flag-type-row').length;
                                var html = '<div class="flag-type-row" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9;">' +
                                    '<input type="hidden" name="wpforo_ai_flag_types[' + index + '][index]" value="' + index + '">' +
                                    '<div style="margin-bottom: 10px;">' +
                                        '<label style="display: inline-block; width: 120px; font-weight: bold;">Flag Type:</label>' +
                                        '<input type="text" name="wpforo_ai_flag_types[' + index + '][type]" value="" placeholder="e.g., flag, nsfw, spam" style="width: 200px;">' +
                                    '</div>' +
                                    '<div style="margin-bottom: 10px;">' +
                                        '<label style="display: inline-block; width: 120px; font-weight: bold;">Enabled:</label>' +
                                        '<input type="checkbox" name="wpforo_ai_flag_types[' + index + '][enabled]" value="1" checked>' +
                                        '<span class="description">Enable this flag type for moderation</span>' +
                                    '</div>' +
                                    '<div style="margin-bottom: 10px;">' +
                                        '<label style="display: inline-block; width: 120px; font-weight: bold;">Should Mute:</label>' +
                                        '<input type="checkbox" name="wpforo_ai_flag_types[' + index + '][shouldMute]" value="1" checked>' +
                                        '<span class="description">Mute users when this flag type is triggered</span>' +
                                    '</div>' +
                                    '<div style="margin-bottom: 10px;">' +
                                        '<label style="display: inline-block; width: 120px; font-weight: bold;">Mute Duration (days):</label>' +
                                        '<input type="number" name="wpforo_ai_flag_types[' + index + '][muteDuration]" value="7" min="0" max="365" style="width: 80px;">' +
                                        '<span class="description">Days to mute for this specific flag type</span>' +
                                    '</div>' +
                                    '<div style="margin-bottom: 10px;">' +
                                        '<label style="display: inline-block; width: 120px; font-weight: bold;">Append Message:</label>' +
                                        '<input type="text" name="wpforo_ai_flag_types[' + index + '][appendString]" value="" placeholder="Leave blank for no message. Use {TYPE} and {REASON} for LLM response formatting tags (case sensitive)." style="width: 600px; font-size: 14px;">' +
                                        '<div class="description" style="margin-top: 5px; max-width: 400px;">' +
                                            'Message to append at the end of flagged content. The {TYPE} and {REASON} formatting tags (case sensitive) will be automatically replaced with the actual values from the LLM AI moderator response.' +
                                        '</div>' +
                                    '</div>' +
                                    '<button type="button" class="button button-small remove-flag-type" style="color: #dc3232; border-color: #dc3232;">Remove</button>' +
                                '</div>';
                                
                                $('#flag-types-container').append(html);
                            });
                            
                            $(document).on('click', '.remove-flag-type', function() {
                                $(this).closest('.flag-type-row').remove();
                                // Reindex the rows
                                $('.flag-type-row').each(function(newIndex) {
                                    $(this).find('input[name$="[index]"]').val(newIndex);
                                    $(this).find('input, select').each(function() {
                                        var name = $(this).attr('name').replace(/\[\d+\]/, '[' + newIndex + ']');
                                        $(this).attr('name', name);
                                    });
                                });
                            });
                        });
                        </script>
                        
                        <p class="description">Configure different flag types with their own mute durations. The AI will return these flag types in its response.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpforo_ai_moderation_prompt">Moderation Prompt</label></th>
                    <td>
                        <?php 
                        global $wpforo_ai_config;
                        $default_prompt = $wpforo_ai_config['default_prompt'];
                        $value = get_option('wpforo_ai_moderation_prompt', '');
                        ?>
                        <textarea name="wpforo_ai_moderation_prompt" id="wpforo_ai_moderation_prompt" rows="5" cols="80" placeholder="<?php echo esc_attr($default_prompt); ?>"><?php echo stripslashes($value); ?></textarea>
                        <p class="description">Customize the moderation prompt used by the LLM. Leave blank to use the default prompt.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="wpforo_ai_mute_duration">Mute Duration (days)</label></th>
                    <td>
                        <?php 
                        global $wpforo_ai_config;
                        $value = get_option('wpforo_ai_mute_duration', $wpforo_ai_config['default_mute_duration']);
                        ?>
                        <input type="number" name="wpforo_ai_mute_duration" id="wpforo_ai_mute_duration" value="<?php echo esc_attr($value); ?>" min="0" max="30">
                        <p class="description">Default number of days to mute users when flagged (used as fallback if flag type duration is not set)</p>
                    </td>
                </tr>
                
            </table>
            <?php submit_button(); ?>
        </form>
        
        <?php endif; // End settings tab ?>
        
        <?php if ($active_tab === 'muted_users'): ?>
            <?php 
            // Check if table exists first, create it if not
            global $wpdb;
            $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            
            if (!$table_exists) {
                wpforo_ai_log_info('WPForo AI Moderation: Table does not exist, attempting to create it...');
                
                // Try to create the table
                $creation_result = wpforo_ai_create_muted_users_table();
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
                
                if ($table_exists) {
                    echo '<div class="notice notice-success"><p>Muted users table created successfully!</p></div>';
                    wpforo_ai_display_muted_users();
                } else {
                    echo '<div class="notice notice-error"><p>The muted users table could not be created. ';
                    echo 'Please check your database permissions or deactivate and reactivate the plugin.</p></div>';
                    
                    // Debug info
                    echo '<div class="notice notice-info"><p>Debug info: ';
                    echo 'Table name: ' . $table_name . '<br>';
                    echo 'Creation result: ' . ($creation_result ? 'Success' : 'Failed') . '<br>';
                    echo 'Current table exists check: ' . ($table_exists ? 'Yes' : 'No');
                    echo '</p></div>';
                }
            } else {
                wpforo_ai_log_info('WPForo AI Moderation: Table exists, displaying muted users...');
                wpforo_ai_display_muted_users();
            }
            ?>
        <?php endif; ?>
        
    </div>    
    <?php
}

// Function to unmute a user and delete their trigger post if unapproved
function wpforo_ai_unmute_user($user_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
    
    // Get the muted user record
    $muted_user = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE user_id = %d",
        $user_id
    ));
    
    if (!$muted_user) {
        error_log('WPForo AI Moderation: Muted user ' . $user_id . ' was not found in the Muted users database table');
        return false;
    }
    
    // Remove user from wpForo muted group if wpForo is available
    if (function_exists('WPF')) {
        $usergroups = WPF()->usergroup->get_usergroups('full');
        $muted_group_id = false;
        
        foreach ($usergroups as $group) {
            if (stripos($group['name'], 'Muted') !== false) {
                $muted_group_id = $group['groupid'];
                break;
            }
        }
        
            if ($muted_group_id && $muted_group_id > 0) {
                // Verify the user is actually in this group before trying to remove
                $user_secondary_groups = WPF()->member->get_secondary_groupids($user_id, true);
                if (is_array($user_secondary_groups) && in_array($muted_group_id, $user_secondary_groups)) {
                    // Remove the muted group from the secondary groups array
                    $updated_groups = array_diff($user_secondary_groups, [$muted_group_id]);
                    WPF()->member->set_secondary_groupids($user_id, $updated_groups);
                    wpforo_ai_log_info('WPForo AI Moderation: Manually removed user ' . $user_id . ' from muted group ' . $muted_group_id);
                } else {
                    wpforo_ai_log_info('WPForo AI Moderation: User ' . $user_id . ' is not in muted group ' . $muted_group_id . ', skipping removal');
                }
            } else {
                error_log('WPForo AI Moderation: No valid muted group found for user ' . $user_id);
            }
    }
    
    // Delete the post only if it still exists and is still unapproved
    if ($muted_user->post_id && function_exists('WPF')) {
        // Check if post still exists and is unapproved (status = 1)
        $post = WPF()->db->get_row($wpdb->prepare(
            "SELECT status FROM " . WPF()->tables->posts . " WHERE postid = %d",
            $muted_user->post_id
        ));
        
        if ($post && $post->status == 1) {
            // Post exists and is still unapproved, delete it
            WPF()->db->delete(
                WPF()->tables->posts,
                array('postid' => $muted_user->post_id),
                array('%d')
            );
            wpforo_ai_log_info('WPForo AI Moderation: Manually deleted unapproved post ' . $muted_user->post_id . ' for unmuted user ' . $user_id);
        } else if ($post) {
            wpforo_ai_log_info('WPForo AI Moderation: Post ' . $muted_user->post_id . ' was approved, not deleting for user ' . $user_id);
        } else {
            wpforo_ai_log_info('WPForo AI Moderation: Post ' . $muted_user->post_id . ' no longer exists for user ' . $user_id);
        }
    }
    
    // Delete the topic only if it still exists, is still unapproved, and user was muted for a topic
    if ($muted_user->topic_id && function_exists('WPF')) {
        wpforo_ai_log_info('WPForo AI Moderation: Checking topic ' . $muted_user->topic_id . ' for cleanup for user ' . $user_id);
        
        // Check if topic still exists and is unapproved (status = 1)
        $topic = WPF()->db->get_row($wpdb->prepare(
            "SELECT status FROM " . WPF()->tables->topics . " WHERE topicid = %d",
            $muted_user->topic_id
        ));
        
        if ($topic) {
            wpforo_ai_log_info('WPForo AI Moderation: Topic ' . $muted_user->topic_id . ' found with status: ' . $topic->status);
            
            if ($topic->status == 1) {
                // Topic exists and is still unapproved, delete it and all its posts
                // First delete all posts in the topic
                $posts_deleted = WPF()->db->delete(
                    WPF()->tables->posts,
                    array('topicid' => $muted_user->topic_id),
                    array('%d')
                );
                
                // Then delete the topic itself
                $topic_deleted = WPF()->db->delete(
                    WPF()->tables->topics,
                    array('topicid' => $muted_user->topic_id),
                    array('%d')
                );
                
                if ($topic_deleted) {
                    wpforo_ai_log_info('WPForo AI Moderation: Manually deleted unapproved topic ' . $muted_user->topic_id . ' and ' . $posts_deleted . ' posts for unmuted user ' . $user_id);
                    
                    // Delete topic subscriptions after successful topic deletion
                    $subscriptions_deleted = WPF()->sbscrb->delete( [ 'type' => 'topic', 'itemid' => $muted_user->topic_id ] );
                    wpforo_ai_log_info('WPForo AI Moderation: Deleted ' . $subscriptions_deleted . ' subscriptions for topic ' . $muted_user->topic_id);
                } else {
                    error_log('WPForo AI Moderation: Failed to delete topic ' . $muted_user->topic_id . ' for user ' . $user_id . ', Error: ' . WPF()->db->last_error);
                }
            } else {
                wpforo_ai_log_info('WPForo AI Moderation: Topic ' . $muted_user->topic_id . ' was approved (status: ' . $topic->status . '), not deleting for user ' . $user_id);
            }
        } else {
            wpforo_ai_log_info('WPForo AI Moderation: Topic ' . $muted_user->topic_id . ' no longer exists for user ' . $user_id);
        }
    }
    
    // Delete the record from muted users table
    $wpdb->delete(
        $table_name,
        array('user_id' => $user_id),
        array('%d')
    );
    
    wpforo_ai_log_info('WPForo AI Moderation: Successfully unmuted user ' . $user_id);
    return true;
}

// Function to display muted users with pagination
function wpforo_ai_display_muted_users() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
    
    // Get total count for pagination
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $items_per_page = 100;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $total_pages = ceil($total_items / $items_per_page);
    $offset = ($current_page - 1) * $items_per_page;
    
    // Get muted users with pagination
    $muted_users = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY mute_time DESC LIMIT %d OFFSET %d",
        $items_per_page,
        $offset
    ));
    
    ?>
    <div class="wrap">
        <h1>Muted Users</h1>
        
        <div style="margin: 20px 0; padding: 15px; background: #f7f7f7; border: 1px solid #ddd; border-radius: 4px;">
            <h3>System Information</h3>
            <p><strong>Current Server Time:</strong> <?php echo esc_html(current_time('mysql')); ?></p>
            <p><strong>Current UTC Time:</strong> <?php echo esc_html(gmdate('Y-m-d H:i:s')); ?></p>
            <p><strong>Next Cleanup Job:</strong> 
                <?php 
                $next_cleanup = wp_next_scheduled('wpforo_ai_job_cleanup');
                if ($next_cleanup) {
                    // Convert UTC timestamp to site's timezone for display
                    $next_cleanup_local = get_date_from_gmt(gmdate('Y-m-d H:i:s', $next_cleanup), 'Y-m-d H:i:s');
                    echo esc_html($next_cleanup_local);
                    
                    // Calculate difference using current UTC time
                    $current_utc_time = time(); // This is already UTC
                    echo ' (' . esc_html(human_time_diff($current_utc_time, $next_cleanup)) . ' from now)';
                } else {
                    echo 'Not scheduled';
                }
                ?>
            </p>
            <?php if (current_user_can('manage_options')): ?>
            <p>
                <button type="button" id="wpforo-ai-cleanup-now" class="button button-primary">
                    Run Cleanup Now
                </button>
                <span id="wpforo-ai-cleanup-status" style="margin-left: 10px;"></span>
            </p>
            <?php endif; ?>
        </div>
        
        <div class="tablenav top">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo sprintf(_n('%d muted user', '%d muted users', $total_items), $total_items); ?></span>
                <?php
                if ($total_pages > 1) {
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                }
                ?>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Post ID</th>
                    <th>Post Content Preview</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Mute Time</th>
                    <th>Expiration Time</th>   
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($muted_users)): ?>
                    <?php foreach ($muted_users as $muted_user): ?>
                        <tr>
                            <td><?php echo esc_html($muted_user->user_id); ?></td>
                            <td>
                                <?php 
                                $user = get_user_by('id', $muted_user->user_id);
                                if ($user) {
                                    echo esc_html($user->user_login);
                                } else {
                                    echo '<span style="color: #999;">User not found</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo $muted_user->post_id ? esc_html($muted_user->post_id) : 'N/A'; ?></td>
                            <td>
                                <?php 
                                if ($muted_user->post_content) {
                                    echo esc_html(wp_trim_words($muted_user->post_content, 10));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($muted_user->type ?: 'flag'); ?></td>
                            <td><?php echo esc_html($muted_user->reason ?: 'N/A'); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($muted_user->mute_time))); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($muted_user->expiration_time))); ?></td>
                            <td>
                                <?php
                                $unmute_url = wp_nonce_url(
                                    add_query_arg(array(
                                        'page' => 'wpforo-ai-moderation',
                                        'tab' => 'muted_users',
                                        'action' => 'unmute',
                                        'user_id' => $muted_user->user_id
                                    ), admin_url('admin.php')),
                                    'wpforo_ai_unmute_' . $muted_user->user_id
                                );
                                ?>
                                <a href="<?php echo esc_url($unmute_url); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('Are you sure you want to unmute this user and delete their trigger post (if unapproved)?')">
                                    Unmute
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No muted users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo sprintf(_n('%d muted user', '%d muted users', $total_items), $total_items); ?></span>
                <?php
                if ($total_pages > 1) {
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                }
                ?>
            </div>
        </div>
    </div>
    
    <?php if (current_user_can('manage_options')): ?>
    <script>
    jQuery(document).ready(function($) {
        $('#wpforo-ai-cleanup-now').on('click', function() {
            var button = $(this);
            var status = $('#wpforo-ai-cleanup-status');
            
            button.prop('disabled', true);
            status.text('Running cleanup...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpforo_ai_manual_cleanup',
                    _ajax_nonce: '<?php echo wp_create_nonce("wpforo_ai_cleanup_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        status.text('Cleanup completed! ' + response.data.message);
                        // Reload the page to show updated list
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        status.text('Error: ' + response.data);
                    }
                },
                error: function() {
                    status.text('AJAX error occurred');
                },
                complete: function() {
                    button.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php endif; ?>
    <?php
}

// Function to display muted users table with pagination
function wpforo_ai_display_muted_users_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
    
    // Setup pagination
    $per_page = 100;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;
    
    // Get total count
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $total_pages = ceil($total_items / $per_page);
    
    // Get muted users for current page
    $muted_users = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY mute_time DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ));
    
    ?>
    <div class="wrap">
        <h2>Muted Users</h2>
        
        <div class="tablenav top">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo sprintf(_n('%s muted user', '%s muted users', $total_items), number_format_i18n($total_items)); ?></span>
                <?php
                if ($total_pages > 1) {
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                }
                ?>
            </div>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Post ID</th>
                    <th>Post Content Preview</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Mute Time</th>
                    <th>Expiration Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($muted_users)): ?>
                    <?php foreach ($muted_users as $user): ?>
                        <tr>
                            <td><?php echo esc_html($user->user_id); ?></td>
                            <td>
                                <?php 
                                $user_info = get_user_by('id', $user->user_id);
                                if ($user_info) {
                                    echo esc_html($user_info->user_login);
                                } else {
                                    echo '<span style="color: #999;">User not found</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo $user->post_id ? esc_html($user->post_id) : 'N/A'; ?></td>
                            <td>
                                <?php 
                                if ($user->post_content) {
                                    echo esc_html(wp_trim_words($user->post_content, 10));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </td>
                            <td><?php echo esc_html($user->type ?: 'flag'); ?></td>
                            <td><?php echo esc_html($user->reason ?: 'N/A'); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($user->mute_time))); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i', strtotime($user->expiration_time))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">No muted users found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo sprintf(_n('%s muted user', '%s muted users', $total_items), number_format_i18n($total_items)); ?></span>
                <?php
                if ($total_pages > 1) {
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

// === LLM API CALL ===
function wpforo_ai_query_llm($api_key, $model, $prompt)
{
    try {
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json',
        ];
        
        // Modify prompt to request JSON response
        $json_prompt = $prompt ;
        
        $body = json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $json_prompt],
            ],
            'max_tokens' => 150,
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
        ]);
        
        if ($body === false) {
            error_log('WPForo AI Moderation: JSON encoding failed');
            return null;
        }
        
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 15,
        ]);
        if (is_wp_error($response)) {
            error_log('WPForo AI Moderation API Error: ' . $response->get_error_message());
            return null;
        }
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('WPForo AI Moderation API returned status: ' . $response_code);
            return null;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WPForo AI Moderation API returned invalid JSON: ' . json_last_error_msg());
            return null;
        }
        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('WPForo AI Moderation API returned invalid response format');
            return null;
        }

        // Parse the JSON response
        $response_content = $data['choices'][0]['message']['content'];
        $parsed_response = json_decode($response_content, true);
        
        if (!$parsed_response || !isset($parsed_response['type'])) {
            error_log('WPForo AI Moderation API returned invalid JSON response: ' . $response_content);
            return null;
        }
        
        return $parsed_response;
    } catch (Exception $e) {
        error_log('WPForo AI Moderation: Exception in wpforo_ai_query_llm: ' . $e->getMessage());
        return null;
    }
}


// === MODERATION LOGIC - WPForo HOOK MODIFICATION FOR BEFORE AND AFTER error_log('WPForo AI Moderation: Registering hooks...');
add_action('wpforo_start_add_post', 'wpforo_ai_before_moderate_post', 100, 1);
add_filter('wpforo_after_add_post_filter', 'wpforo_ai_after_add_moderate_post', 100, 1);
add_action('wpforo_start_add_topic', 'wpforo_ai_before_moderate_topic', 100, 1);
add_filter('wpforo_after_add_topic_filter', 'wpforo_ai_after_add_moderate_topic', 10, 1);
add_action('wpforo_start_edit_post', 'wpforo_ai_before_moderate_post_edit', 100, 1);
add_filter('wpforo_edit_post_data_filter', 'wpforo_ai_after_add_moderate_post_edit', 10, 1);
add_action('wpforo_start_edit_topic', 'wpforo_ai_before_moderate_topic_edit', 100, 1);
add_filter('wpforo_edit_topic_data_filter', 'wpforo_ai_after_add_moderate_topic_edit', 100, 1);
function wpforo_ai_before_moderate_post($post)
{
    global $wpdb;
    
    wpforo_ai_log_info('WPForo AI Moderation: Starting before post moderation for post ID: ' . ($post['postid'] ?? 'unknown'));
    
    // Skip moderation check for admin users
    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping before post moderation for administrator user ID: ' . $user->ID);
        return $post;
    }

    // Check if the user has 'Muted' Usergroup name in secondary group.
    $user_id = false;
    if (function_exists('WPF')) {
        $user_id = $user->ID;
        wpforo_ai_log_info('WPForo AI Moderation: Checking muted status for user ID: ' . $user_id);
        
        // Get the user's secondary group IDs
        $secondary_groupids = WPF()->member->get_secondary_groupids($user_id, true);
        
        if (!empty($secondary_groupids)) {
            wpforo_ai_log_info('WPForo AI Moderation: User has secondary groups: ' . print_r($secondary_groupids, true));
            
            // Get the names of the secondary groups
            $secondary_group_names = WPF()->usergroup->get_secondary_group_names($secondary_groupids);
            
            // Check if any secondary group name contains "Muted"
            if (is_array($secondary_group_names)) {
                foreach ($secondary_group_names as $group_name) {
                    if (stripos($group_name, 'Muted') !== false) {
                        wpforo_ai_log_info('WPForo AI Moderation: User ' . $user_id . ' is muted, checking expiration time');
                        
                        // User is muted, prevent posting with detailed information
                        $muted_users_table = $wpdb->prefix . 'wpforo_ai_muted_users';
                        $mute_info = $wpdb->get_row($wpdb->prepare(
                            "SELECT expiration_time FROM $muted_users_table WHERE user_id = %d",
                            $user_id
                        ));
                        
                        if ($mute_info) {
                            $expiration_time = $mute_info->expiration_time;
                            $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));
                            
                            $message = 'You are currently muted and cannot post messages in the forum. ';
                            $message .= 'Your mute will expire on ' . $formatted_expiration . '. ';
                            $message .= 'Please wait for a human moderator to review your case. ';
                            $message .= 'If a moderator does not attend to your mute, you will be automatically unmuted between 0-12 hours after your mute expites ';
                            $message .= 'but your original post will be deleted.';
                        } else {
                            $message = 'You are currently muted and cannot post messages in the forum. ';
                            $message .= 'We can\'t check your mute expiration time at this time.';
                            $message .= 'Please contact a moderator on telegram for assistance.';
                        }
                        
                        wp_die($message, __('Muted User', 'wpforo-ai-moderation'), 403);
                        return false;
                    }
                }
            }
        }
    }

    

    return $post;
}

// You can also add a note or metadata to track why it was flagged
// This allows the post to be created but requires moderator approval
function wpforo_ai_after_add_moderate_post($post) {
    // Handle Moderation here. e.g. Flag it for review or Prohibit it from being published

    wpforo_ai_log_info('WPForo AI Moderation: Starting after post moderation for post ID: ' . ($post['postid'] ?? 'unknown'));

    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping after post moderation for administrator user ID: ' . $user->ID);
        return $post;
    }

    $user_id = false;
    if (function_exists('WPF')) {
        $user_id = $user->ID;

        $api_key = get_option('openrouter_api_key');
        global $wpforo_ai_config;
        $model = get_option('openrouter_model', $wpforo_ai_config['default_model']);
        if (empty($api_key)) {
            error_log('WPForo AI Moderation: No API key found, skipping moderation');
            return $post;
        }
        
        wpforo_ai_log_info('WPForo AI Moderation: API key found, proceeding with moderation');
        
        global $wpforo_ai_config;
        $content = sanitize_text_field($post['body']);
        $custom_prompt = trim(get_option('wpforo_ai_moderation_prompt', ''));
        $default_prompt = $wpforo_ai_config['default_prompt'];

        $prompt = ($custom_prompt ?: $default_prompt) . "\n\n" . $content;
        wpforo_ai_log_info('WPForo AI Moderation: Sending content to LLM for moderation');
        
        $response = wpforo_ai_query_llm($api_key, $model, $prompt);
        wpforo_ai_log_info('WPForo AI Moderation: LLM response: ' . ($response ? json_encode($response) : 'No response'));

        if ($response && isset($response['type']) && wpforo_ai_should_check_type($response['type'])) {
            if (wpforo_ai_should_mute_for_type($response['type'])) {

            
                wpforo_ai_add_user_to_muted_table_on_post($user_id, $post, $content, $response);

                // Add a notice to inform the user they've been muted with detailed information
                if (function_exists('WPF')) {
                    global $wpforo_ai_config;
                    $mute_duration = wpforo_ai_get_mute_duration_for_type($response['type']);
                    $expiration_time = date('Y-m-d H:i:s', strtotime("+$mute_duration days"));
                    $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));
                    
                    $message = 'Your post was flagged by the AI moderator for violating community guidelines. ';
                    $message .= 'Your account has been muted until ' . $formatted_expiration . '. ';
                    $message .= 'A human moderator will review your profile and decide whether to further implient or remove restrictive functions.';
                    $message .= 'If the human moderator takes no action, you will be automatically unmuted between 0-12 hours after your mute expires. ';
                    $message .= 'Your original post will be deleted unless a human moderator approves it before your mute expires.';
                    
                    WPF()->notice->add($message, 'error', 120);
                    // Return the post array instead of false to prevent corruption
                    // The post has already been set to unapproved status in wpforo_ai_add_user_to_muted_table_on_post
                    // return $post;
                }
            }
            else {
                wpforo_ai_log_info("WPForo AI Moderation: The post is marked type " . $response['type'] . " and is enabled, but muting is disabled.");
            }

            // Add appendString message if configured
            if (isset($response['type']) && isset($response['reason'])) {
                $append_message = wpforo_ai_get_append_message($response['type'], $response['type'], $response['reason']);
                if (!empty($append_message)) {
                    $post['body'] .= "\n\n" . $append_message;
                    wpforo_ai_log_info("WPForo AI Moderation: Appended message to post: " . $append_message);
                }
            }
        }
    }
    return $post;
}

// Function to moderate new topics
function wpforo_ai_before_moderate_topic($topic) {
    global $wpdb;
    
    wpforo_ai_log_info('WPForo AI Moderation: Starting before topic moderation for topic ID: ' . ($topic['topicid'] ?? 'unknown'));
    
    // Skip moderation check for admin users
    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping before topic moderation for administrator user ID: ' . $user->ID);
        return $topic;
    }

    // Check if the user has 'Muted' Usergroup name in secondary group
    $user_id = false;
    if (function_exists('WPF')) {
        $user_id = $user->ID;
        
        // Get the user's secondary group IDs
        $secondary_groupids = WPF()->member->get_secondary_groupids($user_id, true);
        
        if (!empty($secondary_groupids)) {
            // Get the names of the secondary groups
            $secondary_group_names = WPF()->usergroup->get_secondary_group_names($secondary_groupids);
            
            // Check if any secondary group name contains "Muted"
            if (is_array($secondary_group_names)) {
                foreach ($secondary_group_names as $group_name) {
                    if (stripos($group_name, 'Muted') !== false) {
                        // User is muted, prevent posting with detailed information
                        $muted_users_table = $wpdb->prefix . 'wpforo_ai_muted_users';
                        $mute_info = $wpdb->get_row($wpdb->prepare(
                            "SELECT expiration_time FROM $muted_users_table WHERE user_id = %d",
                            $user_id
                        ));
                        
                        if ($mute_info) {
                            $expiration_time = $mute_info->expiration_time;
                            $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));
                            
                            $message = 'You are currently muted and cannot create topics in the forum. ';
                            $message .= 'Your mute will expire on ' . $formatted_expiration . '. ';
                            $message .= 'Please wait for a human moderator to review your case. ';
                            $message .= 'If a moderator does not attend to your mute, you will be automatically unmuted between 0-12 hours after your mute expires ';
                            $message .= 'but your original topic will be deleted.';
                        } else {
                            $message = 'You are currently muted and cannot create topics in the forum. ';
                            $message .= 'We can\'t check your mute expiration time at this time.';
                            $message .= 'Please contact a moderator on telegram for assistance.';
                        }
                        
                        wp_die($message, __('Muted User', 'wpforo-ai-moderation'), 403);
                        return false;
                    }
                }
            }
        }
    }

    

    return $topic;
}

function wpforo_ai_after_add_moderate_topic($topic){

    wpforo_ai_log_info('WPForo AI Moderation: Starting after topic moderation for topic ID: ' . ($topic['topicid'] ?? 'unknown'));
    
    // Skip moderation check for admin users
    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping after topic moderation for administrator user ID: ' . $user->ID);
        return $topic;
    }

    // Check if the user has 'Muted' Usergroup name in secondary group
    $user_id = false;
    if (function_exists('WPF')) {
        $user_id = $user->ID;


        // Handle Moderation for topics
        $api_key = get_option('openrouter_api_key');
        global $wpforo_ai_config;
        $model = get_option('openrouter_model', $wpforo_ai_config['default_model']);
        if (empty($api_key)) {
            error_log('WPForo AI Moderation: No API key found, skipping topic moderation');
            return $topic;
        }
        
        wpforo_ai_log_info('WPForo AI Moderation: API key found, proceeding with topic moderation');
        
        global $wpforo_ai_config;
        // Combine topic title and body for moderation
        $content = sanitize_text_field($topic['title'] . "\n\n" . $topic['body']);
        $custom_prompt = trim(get_option('wpforo_ai_moderation_prompt', ''));
        $default_prompt = $wpforo_ai_config['default_prompt'];

        $prompt = ($custom_prompt ?: $default_prompt) . "\n\n" . $content;
        wpforo_ai_log_info('WPForo AI Moderation: Sending topic content to LLM for moderation');
        
        $response = wpforo_ai_query_llm($api_key, $model, $prompt);
        wpforo_ai_log_info('WPForo AI Moderation: LLM response for topic: ' . ($response ? json_encode($response) : 'No response'));

        if ($response && isset($response['type']) && wpforo_ai_should_check_type($response['type'])) {

            if (wpforo_ai_should_mute_for_type($response['type'])) {

                wpforo_ai_add_user_to_muted_table_on_topic($user_id, $topic, $content, $response);

                // Add a notice to inform the user they've been muted with detailed information
                if (function_exists('WPF')) {
                    global $wpforo_ai_config;
                    $mute_duration = wpforo_ai_get_mute_duration_for_type($response['type']);
                    $expiration_time = date('Y-m-d H:i:s', strtotime("+$mute_duration days"));
                    $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));

                    $message = 'Your topic was flagged by the AI moderator for violating community guidelines. ';
                    $message .= 'Your account has been muted until ' . $formatted_expiration . '. ';
                    $message .= 'A human moderator will review your profile and decide whether to further implement or remove restrictive functions.';
                    $message .= 'If the human moderator takes no action, you will be automatically unmuted between 0-12 hours after your mute expires. ';
                    $message .= 'Your original topic will be deleted unless a human moderator approves it before your mute expires.';

                    WPF()->notice->add($message, 'error', 120);
                    //return false;
                }
            }
            else {
                wpforo_ai_log_info("WPForo AI Moderation: The topic is marked type " . $response['type'] . " and is enabled, but muting is disabled.");
            }

            // Add appendString message if configured
            if (isset($response['type']) && isset($response['reason'])) {
                $append_message = wpforo_ai_get_append_message($response['type'], $response['type'], $response['reason']);
                if (!empty($append_message)) {
                    $topic['body'] .= "\n\n" . $append_message;
                    wpforo_ai_log_info("WPForo AI Moderation: Appended message to topic: " . $append_message);
                }
            }
        }

    }
    return $topic;
}

// Function to moderate post edits
function wpforo_ai_before_moderate_post_edit($post) {
    global $wpdb;
    
    wpforo_ai_log_info('WPForo AI Moderation: Starting before post edit moderation for post ID: ' . ($post['postid'] ?? 'unknown'));
    
    // Skip moderation check for admin users
    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping before post edit moderation for administrator user ID: ' . $user->ID);
        return $post;
    }

    // Check if the user has 'Muted' Usergroup name in secondary group
    $user_id = false;
    if (function_exists('WPF')) {
        $user_id = $user->ID;
        
        // Get the user's secondary group IDs
        $secondary_groupids = WPF()->member->get_secondary_groupids($user_id, true);
        
        if (!empty($secondary_groupids)) {
            // Get the names of the secondary groups
            $secondary_group_names = WPF()->usergroup->get_secondary_group_names($secondary_groupids);
            
            // Check if any secondary group name contains "Muted"
            if (is_array($secondary_group_names)) {
                foreach ($secondary_group_names as $group_name) {
                    if (stripos($group_name, 'Muted') !== false) {
                        // User is muted, prevent posting with detailed information
                        $muted_users_table = $wpdb->prefix . 'wpforo_ai_muted_users';
                        $mute_info = $wpdb->get_row($wpdb->prepare(
                            "SELECT expiration_time FROM $muted_users_table WHERE user_id = %d",
                            $user_id
                        ));
                        
                        if ($mute_info) {
                            $expiration_time = $mute_info->expiration_time;
                            $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));
                            
                            $message = 'You are currently muted and cannot edit posts in the forum. ';
                            $message .= 'Your mute will expire on ' . $formatted_expiration . '. ';
                            $message .= 'Please wait for a human moderator to review your case. ';
                            $message .= 'If a moderator does not attend to your mute, you will be automatically unmuted between 0-12 hours after your mute expires ';
                            $message .= 'but your edited post will be reverted.';
                        } else {
                            $message = 'You are currently muted and cannot edit posts in the forum. ';
                            $message .= 'We can\'t check your mute expiration time at this time.';
                            $message .= 'Please contact a moderator on telegram for assistance.';
                        }
                        
                        wp_die($message, __('Muted User', 'wpforo-ai-moderation'), 403);
                        return false;
                    }
                }
            }
        }
    }

    


    return $post;
}


function wpforo_ai_after_add_moderate_post_edit($post) {

    wpforo_ai_log_info('WPForo AI Moderation: Starting after post edit moderation for post ID: ' . ($post['postid'] ?? 'unknown'));
    
    // Skip moderation check for admin users
    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping after post edit moderation for administrator user ID: ' . $user->ID);
        return $post;
    }

    // Check if the user has 'Muted' Usergroup name in secondary group
    $user_id = false;
    if (function_exists('WPF')) {
            $user_id = $user->ID;
        // Handle Moderation for post edits
        $api_key = get_option('openrouter_api_key');
        global $wpforo_ai_config;
        $model = get_option('openrouter_model', $wpforo_ai_config['default_model']);
        if (empty($api_key)) return $post;
        
        global $wpforo_ai_config;
        $content = sanitize_text_field($post['body']);
        $custom_prompt = trim(get_option('wpforo_ai_moderation_prompt', ''));
        $default_prompt = $wpforo_ai_config['default_prompt'];

        $prompt = ($custom_prompt ?: $default_prompt) . "\n\n" . $content;
        $response = wpforo_ai_query_llm($api_key, $model, $prompt);
        wpforo_ai_log_info('WPForo AI Moderation: LLM response for post edit: ' . ($response ? json_encode($response) : 'No response'));

        if ($response && isset($response['type']) && wpforo_ai_should_check_type($response['type'])) {

            if (wpforo_ai_should_mute_for_type($response['type'])) {
                wpforo_ai_add_user_to_muted_table_on_post_edit($user_id, $post, $content, $response);

                // Add a notice to inform the user they've been muted with detailed information
                if (function_exists('WPF')) {
                    global $wpforo_ai_config;
                    $mute_duration = wpforo_ai_get_mute_duration_for_type($response['type']);
                    $expiration_time = date('Y-m-d H:i:s', strtotime("+$mute_duration days"));
                    $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));
                    
                    $message = 'Your edited post was flagged by the AI moderator for violating community guidelines. ';
                    $message .= 'Your account has been muted until ' . $formatted_expiration . '. ';
                    $message .= 'A human moderator will review your profile and decide whether to further implement or remove restrictive functions.';
                    $message .= 'If the human moderator takes no action, you will be automatically unmuted between 0-12 hours after your mute expires. ';
                    $message .= 'Your edited post will be deleted unless a human moderator approves it before your mute expires.';
                    
                    WPF()->notice->add($message, 'error');
                    //return false;
                }
            }
            else {
                wpforo_ai_log_info("WPForo AI Moderation: The post_edit is marked type " . $response['type'] . " and is enabled, but muting is disabled.");
            }

            // Add appendString message if configured
            if (isset($response['type']) && isset($response['reason'])) {
                $append_message = wpforo_ai_get_append_message($response['type'], $response['type'], $response['reason']);
                if (!empty($append_message)) {
                    $post['body'] .= "\n\n" . $append_message;
                    wpforo_ai_log_info("WPForo AI Moderation: Appended message to post_edit: " . $append_message);
                }
            }
        }
    }

    return $post;
}

/**
 * Callback for wpforo_start_edit_topic action hook
 * Handles topic edit moderation
 * 
 * @param array $topic Topic data
 */
function wpforo_ai_before_moderate_topic_edit($topic) {
    global $wpdb;
    wpforo_ai_log_info('WPForo AI Moderation: Starting before topic edit moderation for topic ID: ' . ($topic['topicid'] ?? 'unknown'));
    
    // Skip moderation check for admin users
    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping before topic edit moderation for administrator user ID: ' . $user->ID);
        return;
    }

    // Check if the user has 'Muted' Usergroup name in secondary group
    $user_id = false;
    if (function_exists('WPF')) {
        $user_id = $user->ID;
        
        // Get the user's secondary group IDs
        $secondary_groupids = WPF()->member->get_secondary_groupids($user_id, true);
        
        if (!empty($secondary_groupids)) {
            // Get the names of the secondary groups
            $secondary_group_names = WPF()->usergroup->get_secondary_group_names($secondary_groupids);
            
            // Check if any secondary group name contains "Muted"
            if (is_array($secondary_group_names)) {
                foreach ($secondary_group_names as $group_name) {
                    if (stripos($group_name, 'Muted') !== false) {
                        // User is muted, prevent posting with detailed information
                        $muted_users_table = $wpdb->prefix . 'wpforo_ai_muted_users';
                        $mute_info = $wpdb->get_row($wpdb->prepare(
                            "SELECT expiration_time FROM $muted_users_table WHERE user_id = %d",
                            $user_id
                        ));
                        
                        if ($mute_info) {
                            $expiration_time = $mute_info->expiration_time;
                            $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));
                            
                            $message = 'You are currently muted and cannot edit posts in the forum. ';
                            $message .= 'Your mute will expire on ' . $formatted_expiration . '. ';
                            $message .= 'Please wait for a human moderator to review your case. ';
                            $message .= 'If a moderator does not attend to your mute, you will be automatically unmuted between 0-12 hours after your mute expires ';
                            $message .= 'but your edited post will be reverted.';
                        } else {
                            $message = 'You are currently muted and cannot edit posts in the forum. ';
                            $message .= 'We can\'t check your mute expiration time at this time.';
                            $message .= 'Please contact a moderator on telegram for assistance.';
                        }
                        
                        wp_die($message, __('Muted User', 'wpforo-ai-moderation'), 403);
                        return false;
                    }
                }
            }
        }
    }
    return $topic;
}

/**
 * Callback for wpforo_edit_topic_data_filter filter hook
 * Handles topic edit moderation after the edit
 * 
 * @param array $topic Topic data
 * @return array|false Modified topic data or false if moderation fails
 */
function wpforo_ai_after_add_moderate_topic_edit($topic) {
    wpforo_ai_log_info('WPForo AI Moderation: Starting after topic edit moderation for topic ID: ' . ($topic['topicid'] ?? 'unknown'));
    
    // Skip moderation check for admin users
    $user = wp_get_current_user();
    if (in_array('administrator', (array) $user->roles)) {
        wpforo_ai_log_info('WPForo AI Moderation: Skipping after topic edit moderation for administrator user ID: ' . $user->ID);
        return $topic;
    }

    // Check if the user has 'Muted' Usergroup name in secondary group
    $user_id = false;
    if (function_exists('WPF')) {
        $user_id = $user->ID;
        
        // Handle Moderation for topic edits
        $api_key = get_option('openrouter_api_key');
        global $wpforo_ai_config;
        $model = get_option('openrouter_model', $wpforo_ai_config['default_model']);
        if (empty($api_key)) return $topic;
        
        global $wpforo_ai_config;
        $content = sanitize_text_field($topic['body']);
        $custom_prompt = trim(get_option('wpforo_ai_moderation_prompt', ''));
        $default_prompt = $wpforo_ai_config['default_prompt'];

        $prompt = ($custom_prompt ?: $default_prompt) . "\n\n" . $content;
        $response = wpforo_ai_query_llm($api_key, $model, $prompt);
        wpforo_ai_log_info('WPForo AI Moderation: LLM response for topic edit: ' . ($response ? json_encode($response) : 'No response'));

        if ($response && isset($response['type']) && wpforo_ai_should_check_type($response['type'])) {

            if (wpforo_ai_should_mute_for_type($response['type'])) {
                wpforo_ai_add_user_to_muted_table_on_topic_edit($user_id, $topic, $content, $response);

                // Add a notice to inform the user they've been muted with detailed information
                if (function_exists('WPF')) {
                    global $wpforo_ai_config;
                    $mute_duration = wpforo_ai_get_mute_duration_for_type($response['type']);
                    $expiration_time = date('Y-m-d H:i:s', strtotime("+$mute_duration days"));
                    $formatted_expiration = date('F j, Y \a\t g:i a', strtotime($expiration_time));
                    
                    $message = 'Your edited topic was flagged by the AI moderator for violating community guidelines. ';
                    $message .= 'Your account has been muted until ' . $formatted_expiration . '. ';
                    $message .= 'A human moderator will review your profile and decide whether to further implement or remove restrictive functions.';
                    $message .= 'If the human moderator takes no action, you will be automatically unmuted between 0-12 hours after your mute expires. ';
                    $message .= 'Your edited topic will be deleted unless a human moderator approves it before your mute expires.';
                    
                    WPF()->notice->add($message, 'error');
                    //return false;
                }
            }
            else {
                wpforo_ai_log_info("WPForo AI Moderation: The topic_edit is marked type " . $response['type'] . " and is enabled, but muting is disabled.");
            }

            // Add appendString message if configured
            if (isset($response['type']) && isset($response['reason'])) {
                $append_message = wpforo_ai_get_append_message($response['type'], $response['type'], $response['reason']);
                if (!empty($append_message)) {
                    $topic['body'] .= "\n\n" . $append_message;
                    wpforo_ai_log_info("WPForo AI Moderation: Appended message to topic_edit: " . $append_message);
                }
            }
        }
    }

    return $topic;
}


// AJAX handler for manual cleanup
add_action('wp_ajax_wpforo_ai_manual_cleanup', 'wpforo_ai_handle_manual_cleanup');

function wpforo_ai_handle_manual_cleanup() {
    try {
        // Verify nonce for security
        if (!check_ajax_referer('wpforo_ai_cleanup_nonce', '_ajax_nonce', false)) {
            wp_die('Security check failed', 403);
        }
        
        // Check if user has permission
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions', 403);
        }
        
        // Run the cleanup function
        wpforo_ai_clean_expired_mutes();
        
        // Get current count of expired users to report back
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpforo_ai_muted_users';
        $current_time = current_time('mysql');
        $expired_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE expiration_time <= %s",
            $current_time
        ));
        
        if ($expired_count === false) {
            error_log('WPForo AI Moderation: Failed to count expired users during manual cleanup');
            $expired_count = 0;
        }
        
        wp_send_json_success(array(
            'message' => 'Cleanup completed successfully. ' . $expired_count . ' users currently expired.'
        ));
    } catch (Exception $e) {
        error_log('WPForo AI Moderation: Exception in manual cleanup: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'Cleanup failed due to an internal error.'
        ));
    }
}

// Register our scripts for the admin area
add_action('admin_enqueue_scripts', 'wpforo_ai_enqueue_admin_scripts');

function wpforo_ai_enqueue_admin_scripts($hook) {
    // Only load on our plugin's settings page
    if ($hook !== 'settings_page_wpforo-ai-moderation') {
        return;
    }
    
    // Ensure jQuery is loaded for AJAX functionality
    wp_enqueue_script('jquery');
    
    // Localize AJAX URL for our script
    wp_localize_script('jquery', 'wpforo_ai_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
?>
<?php
    function wpforo_ai_add_user_to_muted_table_on_topic($user_id, &$topic, &$content, &$response) {
        global $wpdb;
        $reason = isset($response['reason']) ? $response['reason'] : 'Content flagged by AI moderation';
        wpforo_ai_log_info('WPForo AI Moderation: Topic content flagged by AI, muting user. Reason: ' . $reason);
        
        // Find or create "Muted" usergroup
        $muted_group_id = false;
        
        // Check if wpForo is available
        if (function_exists('WPF')) {
            // Get all usergroups to find "Muted" group
            $usergroups = WPF()->usergroup->get_usergroups('full');
            foreach ($usergroups as $group) {
                if (stripos($group['name'], 'Muted') !== false) {
                    $muted_group_id = $group['groupid'];
                    break;
                }
            }
            
            // Add muted group as secondary usergroup to the user only if the flag type should mute
            if (!empty($muted_group_id)) {
                if ($user_id) {
                    WPF()->member->append_secondary_groupids($user_id, [$muted_group_id]);
                    
                    // Add or update user in muted users table - store topic ID for topic-level moderation
                    wpforo_ai_add_muted_user($user_id, null, $content, $topic['topicid'], $response['type'], $reason);
                }
            }
            
            // Update the topic status to unapproved in the database
            WPF()->db->update(
                WPF()->tables->topics,
                array('status' => 1),
                array('topicid' => $topic['topicid']),
                array('%d'),
                array('%d')
            );
            
            // Also update the first post status (topic body)
            $first_post = WPF()->db->get_row($wpdb->prepare(
                "SELECT postid FROM " . WPF()->tables->posts . " WHERE topicid = %d ORDER BY postid ASC LIMIT 1",
                $topic['topicid']
            ));
            
            if ($first_post) {
                WPF()->db->update(
                    WPF()->tables->posts,
                    array('status' => 1),
                    array('postid' => $first_post->postid),
                    array('%d'),
                    array('%d')
                );
            }
        }
    }
    function wpforo_ai_add_user_to_muted_table_on_post($user_id, &$post, &$content, &$response) {
        $reason = isset($response['reason']) ? $response['reason'] : 'Content flagged by AI moderation';
        wpforo_ai_log_info('WPForo AI Moderation: Content flagged by AI, muting user. Reason: ' . $reason);
        
        // Find or create "Muted" usergroup
        $muted_group_id = false;
        
        // Check if wpForo is available
        if (function_exists('WPF')) {
            wpforo_ai_log_info('WPForo AI Moderation: WPF() available, finding muted group');
            // Get all usergroups to find "Muted" group
            $usergroups = WPF()->usergroup->get_usergroups('full');
            foreach ($usergroups as $group) {
                if (stripos($group['name'], 'Muted') !== false) {
                    $muted_group_id = $group['groupid'];
                    wpforo_ai_log_info('WPForo AI Moderation: Found muted group with ID: ' . $muted_group_id);
                    break;
                }
            }
            
            if (!$muted_group_id) {
                error_log('WPForo AI Moderation: No muted group found in wpForo usergroups');
            }
            
            // Add muted group as secondary usergroup to the user only if the flag type should mute
            if (!empty($muted_group_id)) {
                if ($user_id) {
                    wpforo_ai_add_muted_user('WPForo AI Moderation: Adding user ' . $user_id . ' to muted group ' . $muted_group_id);
                    WPF()->member->append_secondary_groupids($user_id, [$muted_group_id]);
                    
                    // Add or update user in muted users table - store post ID for post-level moderation, null for topic_id
                    wpforo_ai_log_info('WPForo AI Moderation: Adding user to muted users table');
                    wpforo_ai_add_muted_user($user_id, $post['postid'], $content, null, $response['type'], $reason);
                } else {
                    error_log('WPForo AI Moderation: No user ID available for muting');
                }
            }
            
            // Update the post status to unapproved in the database
            WPF()->db->update(
                WPF()->tables->posts,
                array('status' => 1),
                array('postid' => $post['postid']),
                array('%d'),
                array('%d')
            );
        }
    }
    function wpforo_ai_add_user_to_muted_table_on_post_edit($user_id, &$post, &$content, &$response){
        $reason = isset($response['reason']) ? $response['reason'] : 'Content flagged by AI moderation';
        wpforo_ai_log_info('WPForo AI Moderation: Post edit flagged by AI, muting user. Reason: ' . $reason);
        // Find or create "Muted" usergroup
        $muted_group_id = false;
        
        // Check if wpForo is available
        if (function_exists('WPF')) {
            // Get all usergroups to find "Muted" group
            $usergroups = WPF()->usergroup->get_usergroups('full');
            foreach ($usergroups as $group) {
                if (stripos($group['name'], 'Muted') !== false) {
                    $muted_group_id = $group['groupid'];
                    break;
                }
            }
            
            // Add muted group as secondary usergroup to the user only if the flag type should mute
            if (!empty($muted_group_id)) {
                if ($user_id) {
                    WPF()->member->append_secondary_groupids($user_id, [$muted_group_id]);
                    
                    // Add or update user in muted users table
                    wpforo_ai_add_muted_user($user_id, $post['postid'], $content, null, $response['type'], $reason);
                }
            }
            
            // Update the post status to unapproved in the database
            WPF()->db->update(
                WPF()->tables->posts,
                array('status' => 1),
                array('postid' => $post['postid']),
                array('%d'),
                array('%d')
            );
        }
    }

    function wpforo_ai_add_user_to_muted_table_on_topic_edit($user_id, &$topic, &$content, &$response){
        $reason = isset($response['reason']) ? $response['reason'] : 'Content flagged by AI moderation';
        wpforo_ai_log_info('WPForo AI Moderation: Topic edit flagged by AI, muting user. Reason: ' . $reason);
        // Find or create "Muted" usergroup
        $muted_group_id = false;
        
        // Check if wpForo is available
        if (function_exists('WPF')) {
            // Get all usergroups to find "Muted" group
            $usergroups = WPF()->usergroup->get_usergroups('full');
            foreach ($usergroups as $group) {
                if (stripos($group['name'], 'Muted') !== false) {
                    $muted_group_id = $group['groupid'];
                    break;
                }
            }
            
            // Add muted group as secondary usergroup to the user only if the flag type should mute
            if (!empty($muted_group_id)) {
                if ($user_id) {
                    WPF()->member->append_secondary_groupids($user_id, [$muted_group_id]);
                    
                    // Add or update user in muted users table with topic_id instead of post_id
                    wpforo_ai_add_muted_user($user_id, null, $content, $topic['topicid'], $response['type'], $reason);
                }
            }
            
            // Update the topic status to unapproved in the database
            WPF()->db->update(
                WPF()->tables->topics,
                array('status' => 1),
                array('topicid' => $topic['topicid']),
                array('%d'),
                array('%d')
            );
        }
    }
?>