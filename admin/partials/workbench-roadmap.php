<?php
/**
 * Roadmap Items Sub-Page Template
 *
 * Variables available:
 * - $stats           Summary statistics array
 * - $organizations   Array of organizations for filter
 * - $current_status  Currently selected status filter
 * - $current_org     Currently selected organization filter
 *
 * @package BBAB\Core\Admin
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

$statuses = array(
    ''                => __( 'Active', 'bbab-core' ),
    'Idea'            => __( 'Idea', 'bbab-core' ),
    'ADR In Progress' => __( 'ADR In Progress', 'bbab-core' ),
    'Proposed'        => __( 'Proposed', 'bbab-core' ),
    'Approved'        => __( 'Approved', 'bbab-core' ),
    'Declined'        => __( 'Declined', 'bbab-core' ),
);
?>
<div class="wrap bbab-workbench-wrap">
    <div class="bbab-workbench-header">
        <h1>
            <span class="dashicons dashicons-chart-line"></span>
            <?php esc_html_e( 'Roadmap Items', 'bbab-core' ); ?>
        </h1>
        <p class="bbab-text-muted">
            <?php esc_html_e( 'Feature requests and strategic roadmap items.', 'bbab-core' ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=bbab-workbench' ) ); ?>">
                &larr; <?php esc_html_e( 'Back to Workbench', 'bbab-core' ); ?>
            </a>
        </p>
    </div>

    <!-- Summary Stats Bar -->
    <div class="bbab-stats-bar">
        <div class="bbab-stat-box">
            <span class="bbab-stat-number"><?php echo esc_html( $stats['total_idea'] ); ?></span>
            <span class="bbab-stat-label"><?php esc_html_e( 'Ideas', 'bbab-core' ); ?></span>
        </div>
        <div class="bbab-stat-box">
            <span class="bbab-stat-number"><?php echo esc_html( $stats['total_adr'] ); ?></span>
            <span class="bbab-stat-label"><?php esc_html_e( 'ADR In Progress', 'bbab-core' ); ?></span>
        </div>
        <div class="bbab-stat-box <?php echo $stats['total_proposed'] > 0 ? 'bbab-stat-highlight' : ''; ?>">
            <span class="bbab-stat-number"><?php echo esc_html( $stats['total_proposed'] ); ?></span>
            <span class="bbab-stat-label"><?php esc_html_e( 'Awaiting Review', 'bbab-core' ); ?></span>
        </div>
        <div class="bbab-stat-box bbab-stat-divider">
            <span class="bbab-stat-number"><?php echo esc_html( $stats['total_approved'] ); ?></span>
            <span class="bbab-stat-label"><?php esc_html_e( 'Approved', 'bbab-core' ); ?></span>
        </div>
        <div class="bbab-stat-box">
            <span class="bbab-stat-number"><?php echo esc_html( $stats['total_declined'] ); ?></span>
            <span class="bbab-stat-label"><?php esc_html_e( 'Declined', 'bbab-core' ); ?></span>
        </div>
    </div>

    <!-- Filters -->
    <div class="bbab-filters-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="bbab-roadmap" />

            <!-- Status Filter Pills -->
            <div class="bbab-filter-group">
                <label class="bbab-filter-label"><?php esc_html_e( 'Status:', 'bbab-core' ); ?></label>
                <div class="bbab-status-pills">
                    <?php foreach ( $statuses as $value => $label ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( array(
                            'page'           => 'bbab-roadmap',
                            'roadmap_status' => $value,
                            'organization'   => $current_org ?: null,
                        ), admin_url( 'admin.php' ) ) ); ?>"
                           class="bbab-status-pill <?php echo $current_status === $value ? 'active' : ''; ?>">
                            <?php echo esc_html( $label ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Organization Filter -->
            <div class="bbab-filter-group">
                <label class="bbab-filter-label" for="organization"><?php esc_html_e( 'Client:', 'bbab-core' ); ?></label>
                <select name="organization" id="organization" class="bbab-client-select" onchange="this.form.submit()">
                    <option value=""><?php esc_html_e( 'All Clients', 'bbab-core' ); ?></option>
                    <?php foreach ( $organizations as $org ) : ?>
                        <option value="<?php echo esc_attr( $org['id'] ); ?>" <?php selected( $current_org, $org['id'] ); ?>>
                            <?php echo esc_html( $org['shortcode'] ? $org['shortcode'] . ' - ' . $org['name'] : $org['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Search -->
            <div class="bbab-filter-group bbab-filter-search">
                <?php $this->list_table->search_box( __( 'Search Roadmap', 'bbab-core' ), 'roadmap' ); ?>
            </div>
        </form>
    </div>

    <!-- Roadmap Table -->
    <div class="bbab-list-table-wrap">
        <form method="get" action="">
            <input type="hidden" name="page" value="bbab-roadmap" />
            <?php if ( $current_status ) : ?>
                <input type="hidden" name="roadmap_status" value="<?php echo esc_attr( $current_status ); ?>" />
            <?php endif; ?>
            <?php if ( $current_org ) : ?>
                <input type="hidden" name="organization" value="<?php echo esc_attr( $current_org ); ?>" />
            <?php endif; ?>

            <?php $this->list_table->display(); ?>
        </form>
    </div>

    <!-- Quick Actions -->
    <div class="bbab-quick-actions">
        <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=roadmap_item' ) ); ?>" class="button button-primary">
            <span class="dashicons dashicons-plus-alt2"></span>
            <?php esc_html_e( 'New Roadmap Item', 'bbab-core' ); ?>
        </a>
        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=roadmap_item' ) ); ?>" class="button">
            <?php esc_html_e( 'Native WP List', 'bbab-core' ); ?>
        </a>
    </div>

</div><!-- .bbab-workbench-wrap -->
