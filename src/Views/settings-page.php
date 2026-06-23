<?php
/**
 * Settings Page View
 *
 * Variables available from AdminPage::renderPage():
 *   $settings    — SettingsDTO
 *   $status      — string  (saved|done|rolled_back|error|run_error|rollback_error)
 *   $msg         — string  error message
 *   $jobId       — string
 *   $updated     — int
 *   $restored    — int
 *   $recentJobs  — string[]
 *   $allProducts — array{id:int, label:string}[]
 *   $excludedIds — int[]
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ── helpers ──────────────────────────────────────────────────────────────────
$avgAdj      = number_format( (float) ( $_GET['average_adjustment'] ?? 0 ), 2 );
$startedAt   = sanitize_text_field( rawurldecode( $_GET['started_at'] ?? '' ) );
$noticeTypes = [ 'error', 'run_error', 'rollback_error' ];

// ── progress screen ─────────────────────────────────────────────────────────
// When status=running the page shows a live progress UI; JS drives all batches.
if ( $status === 'running' ) :
	$redirectBase = admin_url( 'admin.php?page=wc-bulk-price-manager' );
?>
<div class="wc-bpm-wrap">

    <div class="wc-bpm-header">
        <div class="wc-bpm-header-title">
            <span class="dashicons dashicons-tag"></span>
            <div>
                <h1><?php esc_html_e( 'Bulk Price Manager', 'wc-bulk-price-manager' ); ?></h1>
                <p><?php esc_html_e( 'Update WooCommerce product prices in bulk — fast, safe, reversible.', 'wc-bulk-price-manager' ); ?></p>
            </div>
        </div>
        <span class="wc-bpm-version">v<?php echo esc_html( WC_BPM_VERSION ); ?></span>
    </div>

    <div class="wc-bpm-card wc-bpm-card-progress" style="max-width:700px;">

        <div class="wc-bpm-card-head">
            <span class="dashicons dashicons-update wc-bpm-spin"></span>
            <div>
                <h2 id="wc-bpm-heading"><?php esc_html_e( 'Updating Prices…', 'wc-bulk-price-manager' ); ?></h2>
                <p class="wc-bpm-card-desc" id="wc-bpm-subtitle">
                    <?php echo esc_html( sprintf(
                        __( 'Processing %1$d products per batch — %2$d batch(es) total.', 'wc-bulk-price-manager' ),
                        (int) $batchSize,
                        (int) $totalPages
                    ) ); ?>
                </p>
            </div>
        </div>

        <!-- Progress bar -->
        <div style="display:flex;align-items:center;gap:12px;margin:20px 0 8px;">
            <div style="flex:1;height:16px;background:#e2e8f0;border-radius:99px;overflow:hidden;">
                <div id="wc-bpm-bar"
                     style="height:100%;width:0%;background:#2271b1;border-radius:99px;transition:width .35s ease;"></div>
            </div>
            <span id="wc-bpm-bar-label"
                  style="font-size:13px;font-weight:700;color:#2271b1;min-width:40px;text-align:right;">0%</span>
        </div>

        <p id="wc-bpm-status"
           style="margin:4px 0 16px;font-size:13px;color:#50575e;">
            <?php esc_html_e( 'Initialising…', 'wc-bulk-price-manager' ); ?>
        </p>

        <!-- Per-batch log -->
        <ul id="wc-bpm-log"
            style="list-style:none;margin:0;padding:0;max-height:280px;overflow-y:auto;
                   border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;"></ul>

        <!-- Data for admin.js — all values set server-side -->
        <div id="wc-bpm-progress" style="display:none"
            data-job-id="<?php echo esc_attr( $jobId ); ?>"
            data-total-pages="<?php echo esc_attr( (int) $totalPages ); ?>"
            data-batch-size="<?php echo esc_attr( (int) $batchSize ); ?>"
            data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>"
            data-rest-base="<?php echo esc_url( rest_url() ); ?>"
            data-redirect-base="<?php echo esc_url( $redirectBase ); ?>"
        ></div>

    </div><!-- .wc-bpm-card -->
</div><!-- .wc-bpm-wrap -->
<?php
	return; // Don't render the normal settings UI while running.
endif;
?>
<div class="wc-bpm-wrap">

    <!-- ── Page header ──────────────────────────────────────────────────── -->
    <div class="wc-bpm-header">
        <div class="wc-bpm-header-title">
            <span class="dashicons dashicons-tag"></span>
            <div>
                <h1><?php esc_html_e( 'Bulk Price Manager', 'wc-bulk-price-manager' ); ?></h1>
                <p><?php esc_html_e( 'Update WooCommerce product prices in bulk — fast, safe, reversible.', 'wc-bulk-price-manager' ); ?></p>
            </div>
        </div>
        <span class="wc-bpm-version">v<?php echo esc_html( WC_BPM_VERSION ); ?></span>
    </div>

    <!-- ── Notices ──────────────────────────────────────────────────────── -->
    <?php if ( $status === 'saved' ) : ?>
        <div class="wc-bpm-notice wc-bpm-notice-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <span><?php esc_html_e( 'Settings saved successfully.', 'wc-bulk-price-manager' ); ?></span>
        </div>
    <?php elseif ( $status === 'done' ) : ?>
        <div class="wc-bpm-notice wc-bpm-notice-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <span><?php echo esc_html( sprintf( __( 'Job complete — %d products updated.', 'wc-bulk-price-manager' ), $updated ) ); ?></span>
        </div>
    <?php elseif ( $status === 'rolled_back' ) : ?>
        <div class="wc-bpm-notice wc-bpm-notice-success">
            <span class="dashicons dashicons-backup"></span>
            <span><?php echo esc_html( sprintf( __( 'Rollback complete — %d products restored.', 'wc-bulk-price-manager' ), $restored ) ); ?></span>
        </div>
    <?php elseif ( in_array( $status, $noticeTypes, true ) && $msg ) : ?>
        <div class="wc-bpm-notice wc-bpm-notice-error">
            <span class="dashicons dashicons-warning"></span>
            <span><?php echo esc_html( $msg ); ?></span>
        </div>
    <?php endif; ?>

    <!-- ── Job complete summary ─────────────────────────────────────────── -->
    <?php if ( $status === 'done' ) : ?>
    <div class="wc-bpm-summary">
        <div class="wc-bpm-summary-header">
            <span class="dashicons dashicons-yes-alt"></span>
            <h2><?php esc_html_e( 'Job Complete', 'wc-bulk-price-manager' ); ?></h2>
        </div>
        <div class="wc-bpm-summary-grid">
            <div class="wc-bpm-stat">
                <span class="wc-bpm-stat-label"><?php esc_html_e( 'Products Updated', 'wc-bulk-price-manager' ); ?></span>
                <span class="wc-bpm-stat-value"><?php echo esc_html( $updated ); ?></span>
            </div>
            <div class="wc-bpm-stat">
                <span class="wc-bpm-stat-label"><?php esc_html_e( 'Avg. Adjustment', 'wc-bulk-price-manager' ); ?></span>
                <span class="wc-bpm-stat-value"><?php echo esc_html( $avgAdj ); ?></span>
            </div>
            <?php if ( $startedAt ) : ?>
            <div class="wc-bpm-stat">
                <span class="wc-bpm-stat-label"><?php esc_html_e( 'Started At', 'wc-bulk-price-manager' ); ?></span>
                <span class="wc-bpm-stat-value wc-bpm-stat-value--sm"><?php echo esc_html( $startedAt ); ?></span>
            </div>
            <?php endif; ?>
        </div>
        <p class="wc-bpm-job-id">
            <?php esc_html_e( 'Job ID:', 'wc-bulk-price-manager' ); ?>
            <code><?php echo esc_html( $jobId ); ?></code>
        </p>
    </div>
    <?php endif; ?>

    <div class="wc-bpm-layout">

        <!-- ── LEFT COLUMN: Settings ────────────────────────────────────── -->
        <div class="wc-bpm-main">

            <div class="wc-bpm-card">
                <div class="wc-bpm-card-head">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <div>
                        <h2><?php esc_html_e( 'Price Adjustment Settings', 'wc-bulk-price-manager' ); ?></h2>
                        <p class="wc-bpm-card-desc"><?php esc_html_e( 'Configure the operation, then save before running.', 'wc-bulk-price-manager' ); ?></p>
                    </div>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field( 'wc_bpm_form' ); ?>
                    <input type="hidden" name="wc_bpm_action" value="save_settings">

                    <!-- Operation + Amount type -->
                    <div class="wc-bpm-fields">

                        <div class="wc-bpm-field">
                            <label for="bpm-operation">
                                <?php esc_html_e( 'Operation', 'wc-bulk-price-manager' ); ?>
                            </label>
                            <div class="wc-bpm-radio-group">
                                <label class="wc-bpm-radio <?php echo $settings->operation->value === 'increase' ? 'is-active' : ''; ?>">
                                    <input type="radio" name="operation" value="increase"
                                        <?php checked( $settings->operation->value, 'increase' ); ?>>
                                    <span class="dashicons dashicons-arrow-up-alt"></span>
                                    <?php esc_html_e( 'Increase', 'wc-bulk-price-manager' ); ?>
                                </label>
                                <label class="wc-bpm-radio <?php echo $settings->operation->value === 'decrease' ? 'is-active' : ''; ?>">
                                    <input type="radio" name="operation" value="decrease"
                                        <?php checked( $settings->operation->value, 'decrease' ); ?>>
                                    <span class="dashicons dashicons-arrow-down-alt"></span>
                                    <?php esc_html_e( 'Decrease', 'wc-bulk-price-manager' ); ?>
                                </label>
                            </div>
                        </div>

                        <div class="wc-bpm-field">
                            <label for="bpm-amount-type">
                                <?php esc_html_e( 'Adjustment Type', 'wc-bulk-price-manager' ); ?>
                            </label>
                            <div class="wc-bpm-radio-group">
                                <label class="wc-bpm-radio <?php echo $settings->amountType->value === 'flat' ? 'is-active' : ''; ?>">
                                    <input type="radio" name="amount_type" value="flat"
                                        <?php checked( $settings->amountType->value, 'flat' ); ?>>
                                    <span>$</span>
                                    <?php esc_html_e( 'Flat', 'wc-bulk-price-manager' ); ?>
                                </label>
                                <label class="wc-bpm-radio <?php echo $settings->amountType->value === 'percentage' ? 'is-active' : ''; ?>">
                                    <input type="radio" name="amount_type" value="percentage"
                                        <?php checked( $settings->amountType->value, 'percentage' ); ?>>
                                    <span>%</span>
                                    <?php esc_html_e( 'Percentage', 'wc-bulk-price-manager' ); ?>
                                </label>
                            </div>
                        </div>

                    </div><!-- .wc-bpm-fields -->

                    <!-- Amount -->
                    <div class="wc-bpm-field" style="margin-top:16px;">
                        <label for="bpm-amount"><?php esc_html_e( 'Amount', 'wc-bulk-price-manager' ); ?></label>
                        <div class="wc-bpm-input-wrap">
                            <span class="wc-bpm-input-prefix">
                                <?php echo $settings->amountType->value === 'percentage' ? '%' : '$'; ?>
                            </span>
                            <input
                                type="number"
                                id="bpm-amount"
                                name="amount"
                                min="0"
                                step="0.01"
                                value="<?php echo esc_attr( $settings->amount ); ?>"
                                placeholder="<?php esc_attr_e( '0.00', 'wc-bulk-price-manager' ); ?>"
                                dir="ltr"
                                required
                            />
                        </div>
                    </div>

                    <!-- Exclude Products -->
                    <div class="wc-bpm-field" style="margin-top:16px;">
                        <label for="bpm-exclude">
                            <?php esc_html_e( 'Exclude Products', 'wc-bulk-price-manager' ); ?>
                            <?php if ( ! empty( $excludedIds ) ) : ?>
                                <span class="wc-bpm-badge"><?php echo count( $excludedIds ); ?> <?php esc_html_e( 'excluded', 'wc-bulk-price-manager' ); ?></span>
                            <?php endif; ?>
                        </label>

                        <?php if ( empty( $allProducts ) ) : ?>
                            <div class="wc-bpm-notice wc-bpm-notice-info" style="margin:0;">
                                <span class="dashicons dashicons-info"></span>
                                <span><?php esc_html_e( 'No products found. Make sure WooCommerce has published products.', 'wc-bulk-price-manager' ); ?></span>
                            </div>
                        <?php else : ?>
                            <div class="wc-bpm-select-wrap">
                                <select
                                    id="bpm-exclude"
                                    name="excluded_ids[]"
                                    multiple
                                    size="<?php echo min( 8, count( $allProducts ) ); ?>"
                                    dir="ltr"
                                >
                                    <?php foreach ( $allProducts as $product ) :
                                        $isExcluded = in_array( (int) $product['id'], $excludedIds, true );
                                    ?>
                                        <option
                                            value="<?php echo esc_attr( $product['id'] ); ?>"
                                            <?php selected( $isExcluded, true ); ?>
                                        ><?php echo esc_html( $product['label'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <p class="wc-bpm-field-hint">
                                <span class="dashicons dashicons-info-outline"></span>
                                <?php esc_html_e( 'Hold Ctrl (Windows) or ⌘ Cmd (Mac) to select or deselect multiple products.', 'wc-bulk-price-manager' ); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="wc-bpm-actions">
                        <button type="submit" class="wc-bpm-btn wc-bpm-btn-secondary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save Settings', 'wc-bulk-price-manager' ); ?>
                        </button>
                    </div>
                </form>
            </div><!-- .wc-bpm-card -->

        </div><!-- .wc-bpm-main -->

        <!-- ── RIGHT COLUMN: Run + Rollback ─────────────────────────────── -->
        <div class="wc-bpm-sidebar">

            <!-- Run card -->
            <div class="wc-bpm-card wc-bpm-card-run">
                <div class="wc-bpm-card-head">
                    <span class="dashicons dashicons-controls-play"></span>
                    <div>
                        <h2><?php esc_html_e( 'Run Operation', 'wc-bulk-price-manager' ); ?></h2>
                        <p class="wc-bpm-card-desc"><?php esc_html_e( 'Save settings first, then run.', 'wc-bulk-price-manager' ); ?></p>
                    </div>
                </div>

                <div class="wc-bpm-run-summary">
                    <div class="wc-bpm-run-row">
                        <span><?php esc_html_e( 'Operation', 'wc-bulk-price-manager' ); ?></span>
                        <strong><?php echo esc_html( ucfirst( $settings->operation->value ) ); ?></strong>
                    </div>
                    <div class="wc-bpm-run-row">
                        <span><?php esc_html_e( 'Type', 'wc-bulk-price-manager' ); ?></span>
                        <strong><?php echo esc_html( ucfirst( $settings->amountType->value ) ); ?></strong>
                    </div>
                    <div class="wc-bpm-run-row">
                        <span><?php esc_html_e( 'Amount', 'wc-bulk-price-manager' ); ?></span>
                        <strong><?php echo esc_html( $settings->amount ); ?><?php echo $settings->amountType->value === 'percentage' ? '%' : ''; ?></strong>
                    </div>
                    <?php if ( ! empty( $excludedIds ) ) : ?>
                    <div class="wc-bpm-run-row">
                        <span><?php esc_html_e( 'Excluded', 'wc-bulk-price-manager' ); ?></span>
                        <strong><?php echo count( $excludedIds ); ?> <?php esc_html_e( 'products', 'wc-bulk-price-manager' ); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="wc-bpm-warning-box">
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php esc_html_e( 'This permanently modifies product prices. Ensure you have a database backup before proceeding.', 'wc-bulk-price-manager' ); ?></span>
                </div>

                <form method="post" action=""
                      onsubmit="return confirm('<?php echo esc_js( __( 'You are about to update prices for all matching products. Make sure you have a database backup. Continue?', 'wc-bulk-price-manager' ) ); ?>');">
                    <?php wp_nonce_field( 'wc_bpm_form' ); ?>
                    <input type="hidden" name="wc_bpm_action" value="run_job">
                    <button type="submit" class="wc-bpm-btn wc-bpm-btn-primary wc-bpm-btn-full">
                        <span class="dashicons dashicons-controls-play"></span>
                        <?php esc_html_e( 'Run Now', 'wc-bulk-price-manager' ); ?>
                    </button>
                </form>
            </div>

            <!-- Rollback card -->
            <?php if ( ! empty( $recentJobs ) ) : ?>
            <div class="wc-bpm-card wc-bpm-card-rollback">
                <div class="wc-bpm-card-head">
                    <span class="dashicons dashicons-backup"></span>
                    <div>
                        <h2><?php esc_html_e( 'Rollback', 'wc-bulk-price-manager' ); ?></h2>
                        <p class="wc-bpm-card-desc"><?php esc_html_e( 'Restore prices from a previous job.', 'wc-bulk-price-manager' ); ?></p>
                    </div>
                </div>

                <form method="post" action=""
                      onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure? This will restore all product prices to their state before the selected job. Continue?', 'wc-bulk-price-manager' ) ); ?>');">
                    <?php wp_nonce_field( 'wc_bpm_form' ); ?>
                    <input type="hidden" name="wc_bpm_action" value="rollback_job">

                    <div class="wc-bpm-field">
                        <label for="bpm-rollback-job"><?php esc_html_e( 'Select Job', 'wc-bulk-price-manager' ); ?></label>
                        <select id="bpm-rollback-job" name="rollback_job_id" class="wc-bpm-select-single" dir="ltr">
                            <option value=""><?php esc_html_e( '— Select a job to rollback —', 'wc-bulk-price-manager' ); ?></option>
                            <?php foreach ( $recentJobs as $jid ) : ?>
                                <option value="<?php echo esc_attr( $jid ); ?>"><?php echo esc_html( $jid ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="wc-bpm-btn wc-bpm-btn-danger wc-bpm-btn-full" style="margin-top:14px;">
                        <span class="dashicons dashicons-backup"></span>
                        <?php esc_html_e( 'Rollback This Job', 'wc-bulk-price-manager' ); ?>
                    </button>
                </form>
            </div>
            <?php endif; ?>

        </div><!-- .wc-bpm-sidebar -->

    </div><!-- .wc-bpm-layout -->

</div><!-- .wc-bpm-wrap -->