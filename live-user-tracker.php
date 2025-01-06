<?php
/**
 * Plugin Name: Live User Tracker
 * Description: Tracks the current live users on the website and provides visitor statistics.
 * Version: 1.2.0
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
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_item'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
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

        // Update total visitor count
        $total_visitors = get_option('lut_total_visitors', 0);
        update_option('lut_total_visitors', $total_visitors + 1);

        // Update daily visitor log
        $visitor_log = get_option('lut_visitor_log', []);
        $today = date('Y-m-d');
        if (!isset($visitor_log[$today])) {
            $visitor_log[$today] = 0;
        }
        $visitor_log[$today]++;
        update_option('lut_visitor_log', $visitor_log);

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

    public function get_statistics() {
        $total_visitors = get_option('lut_total_visitors', 0);
        $visitor_log = get_option('lut_visitor_log', []);

        $last_7_days = 0;
        $last_30_days = 0;
        $today = strtotime(date('Y-m-d'));

        foreach ($visitor_log as $date => $count) {
            $timestamp = strtotime($date);
            $diff = ($today - $timestamp) / (60 * 60 * 24);

            if ($diff <= 7) {
                $last_7_days += $count;
            }
            if ($diff <= 30) {
                $last_30_days += $count;
            }
        }

        return [
            'total_visitors' => $total_visitors,
            'last_7_days' => $last_7_days,
            'last_30_days' => $last_30_days,
        ];
    }

    public function add_dashboard_widget() {
        if (get_option('lut_display_option', 'dashboard') === 'dashboard') {
            wp_add_dashboard_widget(
                'live_user_tracker_widget',
                'Live User Tracker',
                [$this, 'dashboard_widget_content']
            );
        }
    }

    public function dashboard_widget_content() {
        $live_users = $this->get_live_user_count();
        $statistics = $this->get_statistics();

        echo '<div class="lut-widget">';
        echo '<p><strong>Current Live Users:</strong> ' . $live_users . '</p>';
        echo '<p><strong>Total Visitors:</strong> ' . $statistics['total_visitors'] . '</p>';
        echo '<p><strong>Last 7 Days Visitors:</strong> ' . $statistics['last_7_days'] . '</p>';
        echo '<p><strong>Last 30 Days Visitors:</strong> ' . $statistics['last_30_days'] . '</p>';
        echo '</div>';
    }

    public function add_admin_bar_item($wp_admin_bar) {
        if (get_option('lut_display_option', 'dashboard') === 'admin_bar') {
            $live_users = $this->get_live_user_count();
            $wp_admin_bar->add_node([
                'id' => 'live_user_tracker',
                'title' => 'Live Users: ' . $live_users,
                'href' => admin_url('options-general.php?page=live-user-tracker-settings'),
            ]);
        }
    }

    public function register_settings_page() {
        add_options_page(
            'Live User Tracker Settings',
            'Live User Tracker',
            'manage_options',
            'live-user-tracker-settings',
            [$this, 'settings_page_content']
        );

        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings() {
        register_setting('live_user_tracker', 'lut_display_option');
    }

    public function settings_page_content() {
        $statistics = $this->get_statistics();
        ?>
        <div class="wrap lut-settings">
            <h1>Live User Tracker Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('live_user_tracker'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Display Live Users</th>
                        <td>
                            <select name="lut_display_option">
                                <option value="dashboard" <?php selected(get_option('lut_display_option', 'dashboard'), 'dashboard'); ?>>Admin Dashboard</option>
                                <option value="admin_bar" <?php selected(get_option('lut_display_option', 'dashboard'), 'admin_bar'); ?>>Admin Bar</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <h2>Visitor Statistics</h2>
            <div class="lut-statistics">
                <p><strong>Total Visitors:</strong> <?php echo $statistics['total_visitors']; ?></p>
                <p><strong>Last 7 Days Visitors:</strong> <?php echo $statistics['last_7_days']; ?></p>
                <p><strong>Last 30 Days Visitors:</strong> <?php echo $statistics['last_30_days']; ?></p>
            </div>

            <!-- <div class="lut-advertisement">
                <h3>Advertisement</h3>
                <p>Check out our premium plugins for advanced analytics and reporting!</p>
                <a href="#" class="button button-primary" target="_blank">Learn More</a>
            </div> -->
        </div>
        <?php
    }

    public function enqueue_admin_styles() {
        wp_enqueue_style('live-user-tracker-admin', plugin_dir_url(__FILE__) . 'admin-style.css');
    }

    public function end_session() {
        if (session_id()) {
            session_write_close();
        }
    }
}

new LiveUserTracker();


