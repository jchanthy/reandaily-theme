<?php
/**
 * ReanDaily Custom Enrollments Admin Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Register Admin Menu Page
add_action( 'admin_menu', 'reandaily_register_enrollments_admin_page' );
function reandaily_register_enrollments_admin_page() {
    add_menu_page(
        'Custom Enrollments',
        'Custom Enrollments',
        'manage_options',
        'reandaily-enrollments',
        'reandaily_render_enrollments_admin_page',
        'dashicons-education',
        26
    );
}

// Handle Admin Actions (Approve/Delete)
add_action( 'admin_init', 'reandaily_handle_enrollment_admin_actions' );
function reandaily_handle_enrollment_admin_actions() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $page = $_GET['page'] ?? '';
    if ( $page !== 'reandaily-enrollments' ) {
        return;
    }

    $action = $_GET['action'] ?? '';
    $id = absint( $_GET['id'] ?? 0 );

    if ( ! $action || ! $id ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'reandaily_enrollments';

    if ( $action === 'approve' ) {
        $nonce = $_GET['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'reandaily_approve_enrollment_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        $enrollment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ) );
        if ( ! $enrollment ) {
            wp_die( 'Enrollment not found.' );
        }

        $user_id = intval( $enrollment->user_id );
        if ( $user_id === 0 ) {
            // Try to create from transient
            $user_id = reandaily_create_user_from_pending_transient( $enrollment->bill_number );
            
            // Fallback: If transient expired, create a passwordless account directly using DB info
            if ( $user_id === 0 ) {
                $email = $enrollment->student_email;
                $name = $enrollment->student_name;
                $existing = get_user_by( 'email', $email );
                if ( $existing ) {
                    $user_id = $existing->ID;
                } else {
                    $username = sanitize_user( current( explode( '@', $email ) ) );
                    if ( username_exists( $username ) ) { $username = $username . '_' . time(); }
                    $new_user = wp_create_user( $username, wp_generate_password(), $email );
                    if ( ! is_wp_error( $new_user ) ) {
                        $user_id = $new_user;
                        wp_update_user( array( 'ID' => $user_id, 'display_name' => $name, 'first_name' => $name ) );
                        wp_new_user_notification( $user_id, null, 'user' );
                    }
                }
            }

            if ( $user_id > 0 ) {
                $wpdb->update(
                    $table,
                    array( 'user_id' => $user_id ),
                    array( 'id' => $id ),
                    array( '%d' ),
                    array( '%d' )
                );
            }
        }
        $course_id = intval( $enrollment->course_id );

        // 1. Perform MasterStudy LMS Enrollment
        if ( $user_id > 0 && $course_id > 0 ) {
            if ( class_exists( 'STM_LMS_Course' ) ) {
                STM_LMS_Course::add_user_course( $course_id, $user_id, 0, 0 );
            } elseif ( function_exists( 'stm_lms_add_user_course' ) ) {
                stm_lms_add_user_course( array(
                    'user_id'    => $user_id,
                    'course_id'  => $course_id,
                    'status'     => 'enrolled',
                    'start_time' => time()
                ) );
            } else {
                $user_courses = get_user_meta( $user_id, 'stm_lms_courses', true );
                if ( ! is_array( $user_courses ) ) { $user_courses = array(); }
                if ( ! in_array( $course_id, $user_courses, true ) ) {
                    $user_courses[] = $course_id;
                    update_user_meta( $user_id, 'stm_lms_courses', $user_courses );
                }
            }
        }

        // 2. Update status in our DB
        $wpdb->update(
            $table,
            array( 'payment_status' => 'paid_verified' ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );

        // 3. Send confirmation emails (similar to automated flow)
        $student_name  = $enrollment->student_name;
        $student_email = $enrollment->student_email;
        $student_phone = $enrollment->student_phone;
        $currency      = $enrollment->currency;
        $amount        = $enrollment->amount;

        if ( ! empty( $student_email ) ) {
            $subject = 'ការទូទាត់ទទួលបានជោគជ័យ - ' . get_the_title( $course_id ) . ' | ReanDaily';
            $body  = "<h2>សូមអរគុណ {$student_name}!</h2>";
            $body .= '<p>ការទូទាត់ប្រាក់សម្រាប់វគ្គសិក្សា <strong>' . esc_html( get_the_title( $course_id ) ) . '</strong> ត្រូវបានផ្ទៀងផ្ទាត់ដោយជោគជ័យ។</p>';
            $body .= '<p>ប្រព័ន្ធបានបើកវគ្គសិក្សាជូនលោកអ្នករួចរាល់ហើយ។</p>';
            $body .= '<p>📚 <a href="' . get_permalink( $course_id ) . '">ចូលទៅកាន់វគ្គសិក្សា</a></p>';
            $body .= '<p>— ReanDaily Team</p>';
            wp_mail( $student_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        }

        wp_safe_redirect( add_query_arg( array( 'message' => 'approved' ), menu_page_url( 'reandaily-enrollments', false ) ) );
        exit;
    }

    if ( $action === 'delete' ) {
        $nonce = $_GET['_wpnonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, 'reandaily_delete_enrollment_' . $id ) ) {
            wp_die( 'Security check failed.' );
        }

        $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

        wp_safe_redirect( add_query_arg( array( 'message' => 'deleted' ), menu_page_url( 'reandaily-enrollments', false ) ) );
        exit;
    }
}

// Render Enrollments Page
function reandaily_render_enrollments_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'reandaily_enrollments';

    // Filters and Search params
    $status_filter = sanitize_text_field( $_GET['status'] ?? 'all' );
    $search_query  = sanitize_text_field( $_GET['s'] ?? '' );
    $paged         = absint( $_GET['paged'] ?? 1 );
    $limit         = 20;
    $offset        = ($paged - 1) * $limit;

    // Build SQL Query
    $where = array('1=1');
    $params = array();

    if ( $status_filter !== 'all' ) {
        if ( $status_filter === 'pending' ) {
            $where[] = "payment_status = 'pending'";
        } elseif ( $status_filter === 'unverified' ) {
            $where[] = "payment_status = 'paid_unverified'";
        } elseif ( $status_filter === 'verified' ) {
            $where[] = "payment_status = 'paid_verified'";
        }
    }

    if ( ! empty( $search_query ) ) {
        $where[] = "(student_name LIKE %s OR student_email LIKE %s OR bill_number LIKE %s OR transaction_ref LIKE %s)";
        $like = '%' . $wpdb->esc_like( $search_query ) . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $where_sql = implode( ' AND ', $where );
    
    // Count total rows
    $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
    if ( ! empty( $params ) ) {
        $total_rows = $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
    } else {
        $total_rows = $wpdb->get_var( $count_sql );
    }
    
    $total_pages = ceil( $total_rows / $limit );

    // Fetch actual rows
    $query_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
    $params[] = $limit;
    $params[] = $offset;
    $rows = $wpdb->get_results( $wpdb->prepare( $query_sql, $params ) );

    // Quick Stats
    $total_pending = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE payment_status = 'pending'" );
    $total_unverified = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE payment_status = 'paid_unverified'" );
    $total_verified = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE payment_status = 'paid_verified'" );
    
    // Display Message
    $msg = $_GET['message'] ?? '';
    ?>
    <div class="wrap reandaily-admin-wrap" style="font-family: 'Kantumruy Pro', 'Inter', sans-serif; margin-right: 20px;">
        <h1 style="font-size: 26px; font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
            🎓 <span>ReanDaily Custom Enrollments & Payments</span>
        </h1>

        <?php if ( $msg === 'approved' ) : ?>
            <div class="notice notice-success is-dismissible" style="border-radius: 8px; border-left-color: #10b981; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.05);"><p><strong>✅ Enrollment Approved successfully!</strong> Course activated and notification email sent to student.</p></div>
        <?php elseif ( $msg === 'deleted' ) : ?>
            <div class="notice notice-info is-dismissible" style="border-radius: 8px; border-left-color: #3b82f6;"><p>🗑 Enrollment record deleted.</p></div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div style="background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 32px; background: #eff6ff; padding: 12px; border-radius: 10px; line-height: 1;">📝</span>
                <div>
                    <div style="font-size: 13px; color: #64748b; font-weight: 600;">Total Registrations</div>
                    <div style="font-size: 24px; font-weight: 800; color: #0f172a;"><?php echo (int) $total_rows; ?></div>
                </div>
            </div>
            <div style="background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 32px; background: #fffbeb; padding: 12px; border-radius: 10px; line-height: 1;">⏳</span>
                <div>
                    <div style="font-size: 13px; color: #b45309; font-weight: 600;">Unverified Claims</div>
                    <div style="font-size: 24px; font-weight: 800; color: #d97706;"><?php echo (int) $total_unverified; ?></div>
                </div>
            </div>
            <div style="background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 32px; background: #ecfdf5; padding: 12px; border-radius: 10px; line-height: 1;">✅</span>
                <div>
                    <div style="font-size: 13px; color: #047857; font-weight: 600;">Verified Paid</div>
                    <div style="font-size: 24px; font-weight: 800; color: #059669;"><?php echo (int) $total_verified; ?></div>
                </div>
            </div>
            <div style="background: #ffffff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 15px;">
                <span style="font-size: 32px; background: #f8fafc; padding: 12px; border-radius: 10px; line-height: 1;">❌</span>
                <div>
                    <div style="font-size: 13px; color: #64748b; font-weight: 600;">Abandoned / Pending</div>
                    <div style="font-size: 24px; font-weight: 800; color: #475569;"><?php echo (int) $total_pending; ?></div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div style="background: #ffffff; border-radius: 12px; padding: 15px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; margin-bottom: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
            <!-- Status Filter Tabs -->
            <div style="display: flex; gap: 6px;">
                <a href="<?php echo esc_url( add_query_arg( array('status' => 'all', 'paged' => 1) ) ); ?>" 
                   style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; transition: all 0.2s; <?php echo $status_filter === 'all' ? 'background: #005a9c; color: #ffffff;' : 'color: #475569; background: #f1f5f9;'; ?>">
                    All (<?php echo (int)$total_rows; ?>)
                </a>
                <a href="<?php echo esc_url( add_query_arg( array('status' => 'unverified', 'paged' => 1) ) ); ?>" 
                   style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; transition: all 0.2s; <?php echo $status_filter === 'unverified' ? 'background: #d97706; color: #ffffff;' : 'color: #b45309; background: #fef3c7;'; ?>">
                    Claims (<?php echo (int)$total_unverified; ?>)
                </a>
                <a href="<?php echo esc_url( add_query_arg( array('status' => 'verified', 'paged' => 1) ) ); ?>" 
                   style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; transition: all 0.2s; <?php echo $status_filter === 'verified' ? 'background: #059669; color: #ffffff;' : 'color: #047857; background: #ecfdf5;'; ?>">
                    Verified (<?php echo (int)$total_verified; ?>)
                </a>
                <a href="<?php echo esc_url( add_query_arg( array('status' => 'pending', 'paged' => 1) ) ); ?>" 
                   style="text-decoration: none; padding: 8px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; transition: all 0.2s; <?php echo $status_filter === 'pending' ? 'background: #64748b; color: #ffffff;' : 'color: #475569; background: #f1f5f9;'; ?>">
                    Pending (<?php echo (int)$total_pending; ?>)
                </a>
            </div>

            <!-- Search Form -->
            <form method="get" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                <input type="hidden" name="page" value="reandaily-enrollments">
                <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                <input type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search student, bill, ref..." 
                       style="padding: 8px 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 13.5px; outline: none; width: 220px; font-family: 'Kantumruy Pro', sans-serif;">
                <button type="submit" class="button button-primary" style="height: auto; padding: 6px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 700; background: #005a9c; border-color: #005a9c; line-height: 2;">Search</button>
                <?php if ( ! empty( $search_query ) ) : ?>
                    <a href="<?php echo esc_url( remove_query_arg('s') ); ?>" style="font-size: 13px; color: #ef4444; margin-left: 5px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Table Card -->
        <div style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 20px;">
            <table class="wp-list-table widefat fixed striped table-view-list" style="border: none; box-shadow: none; margin: 0;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1.5px solid #e2e8f0;">
                        <th style="padding: 15px 12px; font-weight: 800; font-size: 14px; width: 6%; color: #0f172a;">ID</th>
                        <th style="padding: 15px 12px; font-weight: 800; font-size: 14px; width: 18%; color: #0f172a;">Student Profile</th>
                        <th style="padding: 15px 12px; font-weight: 800; font-size: 14px; width: 22%; color: #0f172a;">Course</th>
                        <th style="padding: 15px 12px; font-weight: 800; font-size: 14px; width: 14%; color: #0f172a;">Bill details</th>
                        <th style="padding: 15px 12px; font-weight: 800; font-size: 14px; width: 15%; color: #0f172a;">Manual Proof</th>
                        <th style="padding: 15px 12px; font-weight: 800; font-size: 14px; width: 11%; color: #0f172a;">Status</th>
                        <th style="padding: 15px 12px; font-weight: 800; font-size: 14px; width: 14%; color: #0f172a; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #64748b; font-size: 15px;">
                                📭 No custom enrollments found.
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : 
                            $course_title = get_the_title( $row->course_id );
                            ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 15px 12px; font-size: 13.5px; vertical-align: middle;"><b>#<?php echo (int) $row->id; ?></b></td>
                                <td style="padding: 15px 12px; vertical-align: middle;">
                                    <div style="font-weight: 800; font-size: 14px; color: #0f172a;"><?php echo esc_html( $row->student_name ); ?></div>
                                    <div style="font-size: 12.5px; color: #64748b; margin-top: 2px;">📧 <?php echo esc_html( $row->student_email ); ?></div>
                                    <div style="font-size: 12.5px; color: #64748b; margin-top: 1px;">📞 <?php echo esc_html( $row->student_phone ); ?></div>
                                </td>
                                <td style="padding: 15px 12px; vertical-align: middle;">
                                    <div style="font-weight: 700; font-size: 13.5px; color: #1e293b; line-height: 1.4;">
                                        <?php echo esc_html( $course_title ? $course_title : 'Course ID: ' . $row->course_id ); ?>
                                    </div>
                                    <div style="font-size: 11.5px; color: #94a3b8; margin-top: 4px;">📅 Registered: <?php echo esc_html( date('M d, Y h:i A', strtotime($row->enrolled_at)) ); ?></div>
                                </td>
                                <td style="padding: 15px 12px; vertical-align: middle;">
                                    <div style="font-size: 13px; color: #475569; font-weight: 700;">💳 Bill: <?php echo esc_html( $row->bill_number ); ?></div>
                                    <div style="font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 4px;">
                                        <?php echo esc_html( $row->currency === 'KHR' ? '៛' . number_format($row->amount) : '$' . number_format($row->amount, 2) ); ?>
                                    </div>
                                </td>
                                <td style="padding: 15px 12px; vertical-align: middle;">
                                    <?php if ( ! empty( $row->transaction_ref ) ) : ?>
                                        <div style="font-size: 12.5px; color: #0f172a; font-weight: 700; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; display: inline-block;">
                                            TXN: <?php echo esc_html( $row->transaction_ref ); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ( ! empty( $row->receipt_url ) ) : ?>
                                        <div style="margin-top: 5px;">
                                            <a href="#" class="reandaily-view-receipt" data-img="<?php echo esc_url( $row->receipt_url ); ?>" 
                                               style="font-size: 12px; color: #005a9c; font-weight: 700; text-decoration: none; border-bottom: 1px dashed #005a9c;">
                                                👁 View Receipt
                                            </a>
                                        </div>
                                    <?php elseif ( empty( $row->transaction_ref ) ) : ?>
                                        <span style="font-size: 12px; color: #94a3b8; font-style: italic;">No manual proof</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px 12px; vertical-align: middle;">
                                    <?php if ( $row->payment_status === 'paid_verified' ) : ?>
                                        <span style="background: #ecfdf5; color: #047857; padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 700; border: 1px solid #10b981;">Verified Paid</span>
                                    <?php elseif ( $row->payment_status === 'paid_unverified' ) : ?>
                                        <span style="background: #fffbeb; color: #b45309; padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 700; border: 1px solid #f59e0b;">Unverified Claim</span>
                                    <?php else : ?>
                                        <span style="background: #f1f5f9; color: #475569; padding: 5px 12px; border-radius: 50px; font-size: 12px; font-weight: 700; border: 1px solid #cbd5e1;">Pending Payment</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 15px 12px; vertical-align: middle; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                                        <?php if ( $row->payment_status !== 'paid_verified' ) : ?>
                                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'approve', 'id' => $row->id ) ), 'reandaily_approve_enrollment_' . $row->id ) ); ?>" 
                                               class="button" onclick="return confirm('Are you sure you want to approve this payment and enroll the student?');"
                                               style="background: #10b981; color: #ffffff; border-color: #10b981; font-weight: 700; padding: 4px 12px; border-radius: 6px; font-size: 12.5px; height: auto; line-height: 2;">
                                                Approve
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'delete', 'id' => $row->id ) ), 'reandaily_delete_enrollment_' . $row->id ) ); ?>" 
                                           class="button" onclick="return confirm('Are you sure you want to delete this record?');"
                                           style="background: #ef4444; color: #ffffff; border-color: #ef4444; font-weight: 700; padding: 4px 12px; border-radius: 6px; font-size: 12.5px; height: auto; line-height: 2;">
                                            Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav" style="margin: 0 0 20px;">
                <div class="tablenav-pages" style="display: flex; gap: 5px;">
                    <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" 
                           class="button <?php echo $paged === $i ? 'button-primary' : ''; ?>"
                           style="border-radius: 6px; padding: 3px 10px; height: auto; font-weight: 700; <?php echo $paged === $i ? 'background: #005a9c; border-color: #005a9c;' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Lightbox Modal for Receipt -->
        <div id="reandaily-receipt-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); z-index: 99999; justify-content: center; align-items: center;">
            <div style="background: #ffffff; border-radius: 16px; max-width: 90%; max-height: 90%; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #cbd5e1; display: flex; flex-direction: column; position: relative;">
                <button id="reandaily-modal-close" style="position: absolute; top: 15px; right: 15px; background: #f1f5f9; border: none; font-size: 20px; font-weight: 800; cursor: pointer; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; color: #475569; transition: all 0.2s;">✕</button>
                <div style="padding: 20px 20px 10px; font-weight: 800; font-size: 16px; border-bottom: 1px solid #e2e8f0; color: #0f172a;">📝 Receipt Screenshot Verification</div>
                <div style="padding: 20px; overflow-y: auto; text-align: center; background: #f8fafc;">
                    <img id="reandaily-receipt-img" src="" alt="Receipt proof" style="max-width: 100%; max-height: 550px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);">
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($){
            $('.reandaily-view-receipt').on('click', function(e){
                e.preventDefault();
                var imgUrl = $(this).data('img');
                $('#reandaily-receipt-img').attr('src', imgUrl);
                $('#reandaily-receipt-modal').css('display', 'flex');
            });
            $('#reandaily-modal-close, #reandaily-receipt-modal').on('click', function(e){
                if(e.target === this || this.id === 'reandaily-modal-close') {
                    $('#reandaily-receipt-modal').hide();
                    $('#reandaily-receipt-img').attr('src', '');
                }
            });
            // Escape key closes modal
            $(document).on('keydown', function(e){
                if(e.key === 'Escape') {
                    $('#reandaily-receipt-modal').hide();
                    $('#reandaily-receipt-img').attr('src', '');
                }
            });
        });
        </script>
    </div>
    <?php
}
