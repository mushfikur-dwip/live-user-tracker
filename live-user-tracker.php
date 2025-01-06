<?php
/**
 * Plugin Name: Live User Tracker
 * Description: Tracks the current live users on the website.
 * Version: 1.0.0
 * Author: Mushfikur Rahman
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class LiveUserTracker {

    private $timeout = 300; // User inactivity timeout in seconds (5 minutes)

    public function __construct() {
        add_action('init', [$this, 'start_session'], 1);
        add_action('wp', [$this, 'track_user_activity']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        add_action('shutdown', [$this, 'end_session']);
    }

    public function start_session() {
        if (!session_id()) {
            session_start();
        }
    }

    public function track_user_activity() {
        $live_users = get_transient('live_user_tracker') ?: [];
        $session_id = session_id();
        
        $live_users[$session_id] = time();

        // Remove inactive users
        foreach ($live_users as $sid => $last_active) {
            if (time() - $last_active > $this->timeout) {
                unset($live_users[$sid]);
            }
        }

        set_transient('live_user_tracker', $live_users, $this->timeout);
    }

    public function get_live_user_count() {
        $live_users = get_transient('live_user_tracker') ?: [];
        return count($live_users);
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'live_user_tracker_widget',
            'Live User Tracker',
            [$this, 'dashboard_widget_content']
        );
    }

    public function dashboard_widget_content() {
        $live_users = $this->get_live_user_count();
        echo '<p><strong>Current Live Users:</strong> ' . $live_users . '</p>';
    }

    public function end_session() {
        if (session_id()) {
            session_write_close();
        }
    }
}

new LiveUserTracker();
