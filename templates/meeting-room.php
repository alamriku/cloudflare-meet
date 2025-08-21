<?php
/**
 * Meeting Room Template
 * 
 * @var array $atts Shortcode attributes
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="cloudflare-meet-container cf-theme-<?php echo esc_attr($atts['theme']); ?>" 
     data-room="<?php echo esc_attr($atts['room']); ?>"
     data-title="<?php echo esc_attr($atts['title']); ?>"
     data-max-participants="<?php echo esc_attr($atts['max_participants']); ?>"
     data-record="<?php echo esc_attr($atts['record_on_start']); ?>"
     style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
     
    <!-- Status Messages -->
    <div class="cf-status" style="display: none;"></div>
    
    <!-- Pre-Meeting Interface -->
    <div class="cf-pre-meeting">
        <div class="cf-header">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <p class="cf-description"><?php _e('Start or join a video meeting', 'cloudflare-meet'); ?></p>
        </div>
        
        <div class="cf-actions">
            <?php if (is_user_logged_in()): ?>
                <button class="cf-create-meeting cf-btn cf-btn-primary">
                    <?php _e('Create Meeting', 'cloudflare-meet'); ?>
                </button>
                <div class="cf-divider"><?php _e('or', 'cloudflare-meet'); ?></div>
            <?php endif; ?>
            
            <div class="cf-join-section">
                <input type="text" class="cf-meeting-id-input" placeholder="<?php _e('Enter Meeting ID', 'cloudflare-meet'); ?>">
                <input type="text" class="cf-participant-name-input" placeholder="<?php _e('Your Name', 'cloudflare-meet'); ?>" 
                       value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>">
                <button class="cf-join-meeting cf-btn cf-btn-secondary">
                    <?php _e('Join Meeting', 'cloudflare-meet'); ?>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Meeting Interface -->
    <div class="cf-meeting-interface" style="display: none;">
        <!-- Meeting Header -->
        <div class="cf-meeting-header">
            <div class="cf-meeting-info">
                <span class="cf-meeting-title"><?php echo esc_html($atts['title']); ?></span>
                <span class="cf-meeting-id">
                    <?php _e('ID:', 'cloudflare-meet'); ?> <span class="cf-meeting-id-display"></span>
                </span>
                <span class="cf-participant-count-display">
                    <?php _e('Participants:', 'cloudflare-meet'); ?> <span class="cf-participant-count">0</span>
                </span>
            </div>
            
            <div class="cf-recording-indicator" style="display: none;">
                🔴 <?php _e('Recording', 'cloudflare-meet'); ?>
            </div>
        </div>
        
        <!-- Video Grid -->
        <div class="cf-video-container">
            <!-- Local Video -->
            <div class="cf-local-video-container">
                <video class="cf-local-video" autoplay muted playsinline></video>
                <div class="cf-local-info">
                    <span><?php _e('You', 'cloudflare-meet'); ?></span>
                </div>
            </div>
            
            <!-- Remote Participants -->
            <div class="cf-participants-grid"></div>
        </div>
        
        <!-- Controls -->
        <div class="cf-controls">
            <div class="cf-media-controls">
                <button class="cf-toggle-audio cf-btn cf-btn-control" title="<?php _e('Toggle Audio', 'cloudflare-meet'); ?>">
                    🎤
                </button>
                <button class="cf-toggle-video cf-btn cf-btn-control" title="<?php _e('Toggle Video', 'cloudflare-meet'); ?>">
                    📹
                </button>
                <button class="cf-share-screen cf-btn cf-btn-control" title="<?php _e('Share Screen', 'cloudflare-meet'); ?>">
                    🖥️
                </button>
            </div>
            
            <div class="cf-meeting-controls">
                <button class="cf-leave-meeting cf-btn cf-btn-danger">
                    <?php _e('Leave', 'cloudflare-meet'); ?>
                </button>
            </div>
            
            <!-- Host Controls -->
            <div class="cf-host-controls" style="display: none;">
                <button class="cf-toggle-recording cf-btn cf-btn-control">
                    🔴 <?php _e('Record', 'cloudflare-meet'); ?>
                </button>
                <button class="cf-end-meeting cf-btn cf-btn-danger">
                    <?php _e('End Meeting', 'cloudflare-meet'); ?>
                </button>
            </div>
        </div>
        
        <!-- Optional Features Panel -->
        <div class="cf-features-panel">
            <!-- Chat -->
            <div class="cf-chat-panel" style="display: none;">
                <div class="cf-chat-messages"></div>
                <div class="cf-chat-input">
                    <input type="text" placeholder="<?php _e('Type a message...', 'cloudflare-meet'); ?>">
                    <button><?php _e('Send', 'cloudflare-meet'); ?></button>
                </div>
            </div>
            
            <!-- Transcript -->
            <div class="cf-transcript" style="display: none;">
                <h4><?php _e('Live Transcript', 'cloudflare-meet'); ?></h4>
                <div class="cf-transcript-content"></div>
            </div>
        </div>
    </div>
</div>