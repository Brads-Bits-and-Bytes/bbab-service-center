<?php
/**
 * The core plugin class.
 *
 * This is used to define admin-specific hooks and public-facing hooks.
 *
 * @package BBAB\Core
 * @since   1.0.0
 */

namespace BBAB\Core;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Class BBAB_Core
 *
 * The main plugin class that orchestrates all functionality.
 *
 * @since 1.0.0
 */
class BBAB_Core {

    /**
     * The admin instance.
     *
     * @var Admin\Admin
     */
    protected $admin;

    /**
     * Constructor.
     *
     * Sets up the plugin components.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->load_dependencies();
    }

    /**
     * Load required dependencies.
     *
     * @since 1.0.0
     * @return void
     */
    private function load_dependencies() {
        // Admin-specific functionality.
        if ( is_admin() ) {
            $this->admin = new Admin\Admin();
        }
    }

    /**
     * Run the plugin.
     *
     * Registers all hooks with WordPress.
     *
     * @since 1.0.0
     * @return void
     */
    public function run() {
        // Register admin hooks.
        if ( is_admin() && $this->admin ) {
            $this->admin->register_hooks();
        }

        // Register frontend simulation hooks (always needed for simulation to work).
        $this->register_simulation_hooks();
    }

    /**
     * Register simulation-related hooks.
     *
     * These need to run on both admin and frontend for simulation to work.
     *
     * @since 1.0.0
     * @return void
     */
    private function register_simulation_hooks() {
        // Client simulation filter - intercepts bbab_get_user_org_id() in snippets.
        add_filter( 'bbab_simulated_org_id', array( $this, 'maybe_override_org_id' ), 10, 2 );

        // Frontend simulation indicator bar.
        add_action( 'wp_footer', array( $this, 'render_simulation_indicator' ) );
    }

    /**
     * Filter callback to override org ID when simulation is active.
     *
     * @since 1.0.0
     * @param int|false $org_id  Current org ID (false = no override).
     * @param int       $user_id The user ID being looked up.
     * @return int|false Organization ID to simulate, or false for no override.
     */
    public function maybe_override_org_id( $org_id, $user_id ) {
        // Only admins can simulate.
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // Check for simulation transient.
        $simulated_org = get_transient( 'bbab_simulating_org_' . get_current_user_id() );

        if ( $simulated_org ) {
            return absint( $simulated_org );
        }

        return false;
    }

    /**
     * Render the simulation indicator bar on the frontend.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_simulation_indicator() {
        // Only for admins.
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if simulating.
        $simulated_org = get_transient( 'bbab_simulating_org_' . get_current_user_id() );

        if ( ! $simulated_org ) {
            return;
        }

        $org_name = get_the_title( $simulated_org );
        $exit_url = wp_nonce_url(
            admin_url( 'admin.php?page=bbab-workbench&bbab_stop_simulation=1' ),
            'bbab_simulation'
        );
        ?>
        <div id="bbab-simulation-bar" style="
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 12px 20px;
            z-index: 99999;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
        ">
            <span style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">👁️</span>
                <strong><?php esc_html_e( 'Simulation Mode', 'bbab-core' ); ?></strong>
                —
                <?php
                printf(
                    /* translators: %s: organization name */
                    esc_html__( 'Viewing as: %s', 'bbab-core' ),
                    '<strong>' . esc_html( $org_name ) . '</strong>'
                );
                ?>
            </span>
            <a href="<?php echo esc_url( $exit_url ); ?>" style="
                background: rgba(255,255,255,0.2);
                color: #fff;
                padding: 6px 16px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 500;
            ">
                <?php esc_html_e( 'Exit Simulation', 'bbab-core' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-workbench' ) ); ?>" style="
                background: #fff;
                color: #667eea;
                padding: 6px 16px;
                border-radius: 4px;
                text-decoration: none;
                font-weight: 500;
            ">
                <?php esc_html_e( 'Back to Workbench', 'bbab-core' ); ?>
            </a>
        </div>
        <style>
            body { padding-bottom: 60px !important; }
        </style>
        <?php
    }
}
