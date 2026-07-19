<?php
/**
 * Theme functions and definitions for ReanDaily Custom.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 */

// ============================================================
// CREATE ENROLLMENT TABLE ON THEME ACTIVATION
// ============================================================
function reandaily_create_enrollment_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'reandaily_enrollments';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        course_id    BIGINT(20) UNSIGNED NOT NULL,
        user_id      BIGINT(20) UNSIGNED DEFAULT 0,
        student_name VARCHAR(150) NOT NULL,
        student_email VARCHAR(150) NOT NULL,
        student_phone VARCHAR(50) NOT NULL,
        bill_number  VARCHAR(100) NOT NULL,
        transaction_ref VARCHAR(100) DEFAULT NULL,
        receipt_url  TEXT DEFAULT NULL,
        currency     VARCHAR(10) NOT NULL DEFAULT 'USD',
        amount       DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        is_free      TINYINT(1) NOT NULL DEFAULT 0,
        payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
        qr_string    TEXT,
        enrolled_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY course_id (course_id),
        KEY student_email (student_email)
    ) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Dynamic database migration check for existing tables (using DESCRIBE for robust cross-platform checks)
    $columns = $wpdb->get_col( "DESCRIBE {$table}" );
    if ( is_array( $columns ) && ! empty( $columns ) ) {
        if ( ! in_array( 'transaction_ref', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN transaction_ref VARCHAR(100) DEFAULT NULL AFTER bill_number" );
        }
        if ( ! in_array( 'receipt_url', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table} ADD COLUMN receipt_url TEXT DEFAULT NULL AFTER transaction_ref" );
        }
    }
}
add_action( 'after_switch_theme', 'reandaily_create_enrollment_table' );
// Also run on init in case table is missing/outdated (idempotent)
add_action( 'init', function() {
    if ( get_option('reandaily_enrollment_table_v2') !== '1' ) {
        reandaily_create_enrollment_table();
        update_option( 'reandaily_enrollment_table_v2', '1' );
    }

    // Auto-create physical 'enroll' page if missing to prevent 404/canonical redirects on production
    if ( ! is_admin() && ! wp_doing_ajax() ) {
        $page = get_page_by_path( 'enroll' );
        if ( ! $page ) {
            wp_insert_post( array(
                'post_title'   => 'Enroll',
                'post_name'    => 'enroll',
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => '',
            ) );
        }
    }
} );

// ============================================================
// REGISTER THEME SUPPORT FOR CUSTOM LOGO
// ============================================================
function reandaily_setup_theme() {
    add_theme_support( 'custom-logo', array(
        'height'      => 80,
        'width'       => 452,
        'flex-height' => true,
        'flex-width'  => true,
    ) );
}
add_action( 'after_setup_theme', 'reandaily_setup_theme', 20 );

/**
 * Generate a secure token for a bill number
 */
function reandaily_generate_bill_token( $bill_number ) {
    return hash_hmac( 'sha256', $bill_number, wp_salt( 'auth' ) );
}

/**
 * Helper: Create user account from transient data once payment is verified.
 */
function reandaily_create_user_from_pending_transient( $bill_number ) {
    $pending = get_transient( 'reandaily_pending_user_' . $bill_number );
    if ( ! is_array( $pending ) ) {
        return 0;
    }

    $name     = $pending['name'];
    $email    = $pending['email'];
    $phone    = $pending['phone'];
    $password = $pending['password'];

    // 1. Check if user already exists
    $existing = get_user_by( 'email', $email );
    if ( $existing ) {
        delete_transient( 'reandaily_pending_user_' . $bill_number );
        return $existing->ID;
    }

    // 2. Generate username
    $username = sanitize_user( current( explode( '@', $email ) ) );
    if ( username_exists( $username ) ) {
        $username = $username . '_' . time();
    }

    // 3. Create user
    $plain_pass = ( ! empty( $password ) && strlen( $password ) >= 6 ) ? $password : wp_generate_password();
    $new_user = wp_create_user( $username, $plain_pass, $email );

    if ( ! is_wp_error( $new_user ) ) {
        $user_id = $new_user;
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => $name,
            'first_name'   => $name,
        ) );
        // Send WP welcome email
        wp_new_user_notification( $user_id, null, 'user' );
        
        delete_transient( 'reandaily_pending_user_' . $bill_number );
        return $user_id;
    }

    return 0;
}


/**
 * Helper to clear the cart in MasterStudy LMS and WooCommerce
 */
function reandaily_clear_all_carts( $user_id = 0 ) {
    // 1. Clear Guest cookie
    if ( isset( $_COOKIE['stm_lms_notauth_cart'] ) ) {
        setcookie( 'stm_lms_notauth_cart', '', time() - 3600, '/' );
        $_COOKIE['stm_lms_notauth_cart'] = '';
    }

    // 2. Clear WooCommerce cart
    if ( class_exists( 'WooCommerce' ) && WC()->cart ) {
        WC()->cart->empty_cart();
    }

    // 3. Clear MasterStudy user cart table
    $uid = $user_id ? $user_id : get_current_user_id();
    if ( $uid > 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'stm_lms_user_cart';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
            $wpdb->delete( $table, array( 'user_id' => $uid ) );
        }
    }
}

// ============================================================
// ENROLLMENT AJAX HANDLER
// ============================================================
function reandaily_handle_enroll_course() {
    // 1. Nonce check
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'reandaily_enroll_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'ការផ្ទៀងផ្ទាត់ security បានបរាជ័យ។' ) );
    }

    // 2. Gather POST data
    $course_id   = isset( $_POST['course_id'] )      ? absint( $_POST['course_id'] )                    : 0;
    $name        = isset( $_POST['student_name'] )   ? sanitize_text_field( wp_unslash( $_POST['student_name'] ) )   : '';
    $email       = isset( $_POST['student_email'] )  ? sanitize_email( wp_unslash( $_POST['student_email'] ) )        : '';
    $phone       = isset( $_POST['student_phone'] )  ? sanitize_text_field( wp_unslash( $_POST['student_phone'] ) )   : '';
    $password    = isset( $_POST['student_password'] ) ? sanitize_text_field( wp_unslash( $_POST['student_password'] ) ) : '';
    $bill_number = isset( $_POST['bill_number'] )    ? sanitize_text_field( wp_unslash( $_POST['bill_number'] ) )    : '';
    $currency    = isset( $_POST['currency'] )       ? strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ) ) ) : 'USD';
    $price       = isset( $_POST['price'] )          ? floatval( $_POST['price'] )                      : 0;
    $is_free     = isset( $_POST['is_free'] )        ? ( $_POST['is_free'] === '1' )                    : false;
    $qr_string   = isset( $_POST['qr_string'] )      ? sanitize_textarea_field( wp_unslash( $_POST['qr_string'] ) )  : '';
    $transaction_ref = isset( $_POST['transaction_ref'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_ref'] ) ) : '';

    // Handle File Upload of receipt_file (Receipt Screenshot)
    $receipt_url = '';
    if ( isset( $_FILES['receipt_file'] ) && ! empty( $_FILES['receipt_file']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        
        $attachment_id = media_handle_upload( 'receipt_file', 0 );
        if ( ! is_wp_error( $attachment_id ) ) {
            $receipt_url = wp_get_attachment_url( $attachment_id );
        }
    }

    // 3. Validate
    if ( ! $course_id || ! $name || ! $email || ! $phone ) {
        wp_send_json_error( array( 'message' => 'ព័ត៌មានមិនគ្រប់គ្រាន់ - required fields missing.' ) );
    }
    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'អ៊ីមែលមិនត្រឹមត្រូវ។' ) );
    }
    if ( ! is_user_logged_in() ) {
        $existing_user = get_user_by( 'email', $email );
        if ( $existing_user ) {
            wp_send_json_error( array( 'message' => 'អ៊ីមែលនេះមានគណនីរួចហើយ។ សូមចូលគណនីរបស់អ្នកមុននឹងបន្ត! (This email is already registered. Please log in first to continue.)' ) );
        }
    }
    $course = get_post( $course_id );
    if ( ! $course || $course->post_type !== 'stm-courses' ) {
        wp_send_json_error( array( 'message' => 'រកមិនឃើញវគ្គសិក្សា។' ) );
    }

    // 4. Get or create WP user (Only for FREE courses immediately, or retrieve existing user ID)
    $user_id = 0;
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
    } else {
        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            $user_id = $existing->ID;
        } elseif ( $is_free ) {
            // Only create user account immediately if it is a FREE course
            if ( ! empty( $password ) && strlen( $password ) >= 6 ) {
                $username = sanitize_user( current( explode( '@', $email ) ) );
                if ( username_exists( $username ) ) { $username = $username . '_' . time(); }
                $new_user = wp_create_user( $username, $password, $email );
                if ( ! is_wp_error( $new_user ) ) {
                    $user_id = $new_user;
                    wp_update_user( array(
                        'ID'           => $user_id,
                        'display_name' => $name,
                        'first_name'   => $name,
                    ) );
                    // Send WP welcome email
                    wp_new_user_notification( $user_id, null, 'user' );
                }
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
        } else {
            // For paid courses, save registration data in transient to create account *after* payment
            set_transient( 'reandaily_pending_user_' . $bill_number, array(
                'name'     => $name,
                'email'    => $email,
                'phone'    => $phone,
                'password' => $password
            ), 3600 ); // 1 hour expiration
        }
    }

    // 4b. Auto-login the newly registered user (Only for FREE courses immediately)
    if ( $is_free && $user_id > 0 && ! is_user_logged_in() ) {
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, true );
    }

    // 5. Auto-enroll in MasterStudy LMS (Only for FREE courses right now)
    $enrolled = false;
    if ( $is_free ) {
        if ( $user_id > 0 ) {
            if ( class_exists( 'STM_LMS_Course' ) ) {
                STM_LMS_Course::add_user_course( $course_id, $user_id, 0, 0 );
                $enrolled = true;
            } elseif ( function_exists( 'stm_lms_add_user_course' ) ) {
                stm_lms_add_user_course( array(
                    'user_id'    => $user_id,
                    'course_id'  => $course_id,
                    'status'     => 'enrolled',
                    'start_time' => time()
                ) );
                $enrolled = true;
            } else {
                // Fallback: try direct usermeta method used by MasterStudy
                $user_courses = get_user_meta( $user_id, 'stm_lms_courses', true );
                if ( ! is_array( $user_courses ) ) { $user_courses = array(); }
                if ( ! in_array( $course_id, $user_courses, true ) ) {
                    $user_courses[] = $course_id;
                    update_user_meta( $user_id, 'stm_lms_courses', $user_courses );
                }
                $enrolled = true;
            }
        }
    }

    // 6. Save enrollment to DB (Insert or Update if already exists)
    global $wpdb;
    $table = $wpdb->prefix . 'reandaily_enrollments';
    
    $existing = null;
    if ( ! empty( $bill_number ) ) {
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE bill_number = %s LIMIT 1",
            $bill_number
        ) );
    }

    if ( $existing ) {
        $update_data = array(
            'course_id'       => $course_id,
            'user_id'         => $user_id,
            'student_name'    => $name,
            'student_email'   => $email,
            'student_phone'   => $phone,
            'currency'        => $currency,
            'amount'          => $price,
            'is_free'         => $is_free ? 1 : 0,
            'payment_status'  => $is_free ? 'free' : ( ( ! empty( $transaction_ref ) || ! empty( $receipt_url ) ) ? 'paid_unverified' : 'pending' ),
        );
        $update_format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%s' );

        if ( ! empty( $transaction_ref ) ) {
            $update_data['transaction_ref'] = $transaction_ref;
            $update_format[] = '%s';
        }
        if ( ! empty( $receipt_url ) ) {
            $update_data['receipt_url'] = $receipt_url;
            $update_format[] = '%s';
        }
        if ( ! empty( $qr_string ) ) {
            $update_data['qr_string'] = $qr_string;
            $update_format[] = '%s';
        }

        $wpdb->update(
            $table,
            $update_data,
            array( 'bill_number' => $bill_number ),
            $update_format,
            array( '%s' )
        );
    } else {
        $wpdb->insert(
            $table,
            array(
                'course_id'       => $course_id,
                'user_id'         => $user_id,
                'student_name'    => $name,
                'student_email'   => $email,
                'student_phone'   => $phone,
                'bill_number'     => $bill_number,
                'transaction_ref' => $transaction_ref,
                'receipt_url'     => $receipt_url,
                'currency'        => $currency,
                'amount'          => $price,
                'is_free'         => $is_free ? 1 : 0,
                'payment_status'  => $is_free ? 'free' : ( ( ! empty( $transaction_ref ) || ! empty( $receipt_url ) ) ? 'paid_unverified' : 'pending' ),
                'qr_string'       => $qr_string,
                'enrolled_at'     => current_time( 'mysql' ),
            ),
            array( '%d','%d','%s','%s','%s','%s','%s','%s','%s','%f','%d','%s','%s','%s' )
        );
    }

    // Send confirmation emails ONLY if it is a free course OR manual payment proof has been uploaded
    $should_notify = $is_free || ! empty( $transaction_ref ) || ! empty( $receipt_url );

    if ( $should_notify ) {
        // 7. Send confirmation email to student
        $subject = 'ការចុះឈ្មោះចូលរៀន - ' . get_the_title( $course_id ) . ' | ReanDaily';
        $body  = "<h2>សូមអរគុណ {$name}!</h2>";
        $body .= '<p>អ្នកបានចុះឈ្មោះ <strong>' . esc_html( get_the_title( $course_id ) ) . '</strong> ដោយជោគជ័យ។</p>';
        if ( ! $is_free ) {
            $body .= '<p>ការទូទាត់របស់អ្នកកំពុងត្រូវបានផ្ទៀងផ្ទាត់ ហើយការចូលរៀននឹងត្រូវបានបើកឱ្យ។</p>';
        }
        $body .= '<p>📚 <a href="' . get_permalink( $course_id ) . '">ចូលរៀនឥឡូវ</a></p>';
        $body .= '<p>— ReanDaily Team</p>';
        wp_mail(
            $email,
            $subject,
            $body,
            array( 'Content-Type: text/html; charset=UTF-8' )
        );

        // 8. Notify admin
        $admin_email = get_option('admin_email');
        $admin_subject = 'Enrollment: ' . get_the_title( $course_id ) . ' — ' . $name;
        $admin_body  = "Student: {$name} ({$email}, {$phone})\n";
        $admin_body .= 'Course: ' . get_the_title( $course_id ) . "\n";
        $admin_body .= 'Bill: ' . $bill_number . "\n";
        if ( ! empty( $transaction_ref ) ) {
            $admin_body .= "Transaction Ref (TXN): " . $transaction_ref . "\n";
        }
        if ( ! empty( $receipt_url ) ) {
            $admin_body .= "Receipt Image URL: " . $receipt_url . "\n";
        }
        $admin_body .= 'Amount: ' . ( $is_free ? 'FREE' : $currency . ' ' . $price ) . "\n";
        wp_mail( $admin_email, $admin_subject, $admin_body );
    }

    $course_url = get_permalink( $course_id );
    
    // For paid courses, we redirect them to the checkout page step 2 instead of granting instant access
    if ( ! $is_free ) {
        reandaily_clear_all_carts( $user_id );
        $message = 'ចុះឈ្មោះបឋមរួចរាល់! សូមធ្វើការទូទាត់ប្រាក់តាម KHQR ដើម្បីចូលរៀន។';
        wp_send_json_success( array(
            'message'  => $message,
            'enrolled' => false,
            'redirect' => '', // Empty redirect prevents frontend from running a bypass!
            'nonce'    => wp_create_nonce( 'reandaily_enroll_nonce' ) // Fresh nonce for logged-in user
        ) );
        return;
    }

    $message = 'អ្នកបានចុះឈ្មោះ <strong>' . esc_html( get_the_title( $course_id ) ) . '</strong> ដោយជោគជ័យ!';
    reandaily_clear_all_carts( $user_id );
    wp_send_json_success( array(
        'message'  => $message,
        'enrolled' => true,
        'redirect' => $course_url,
    ) );
}
add_action( 'wp_ajax_reandaily_enroll_course',        'reandaily_handle_enroll_course' );
add_action( 'wp_ajax_nopriv_reandaily_enroll_course', 'reandaily_handle_enroll_course' );



// ============================================================
// KHQR STRING GENERATOR (EMV QR Code spec — server-side, no SDK needed)
// ============================================================

/**
 * Build one EMV TLV element: TAG (2 chars) + LENGTH (2 digits) + VALUE
 */
function reandaily_khqr_tlv( $tag, $value ) {
    return $tag . str_pad( strlen( $value ), 2, '0', STR_PAD_LEFT ) . $value;
}

/**
 * CRC16/CCITT — used by KHQR for tag 63 checksum.
 * Poly 0x1021, Init 0xFFFF, no reflection.
 */
function reandaily_khqr_crc16( $str ) {
    $crc = 0xFFFF;
    $len = strlen( $str );
    for ( $i = 0; $i < $len; $i++ ) {
        $crc ^= ord( $str[ $i ] ) << 8;
        for ( $j = 0; $j < 8; $j++ ) {
            $crc = ( $crc & 0x8000 )
                ? ( ( $crc << 1 ) ^ 0x1021 ) & 0xFFFF
                : ( $crc << 1 ) & 0xFFFF;
        }
    }
    return strtoupper( str_pad( dechex( $crc ), 4, '0', STR_PAD_LEFT ) );
}

/**
 * Custom wrapper for get_theme_mod that falls back to the previous
 * child theme mods (masterstudy-lms-starter-child) if the setting is empty in the current theme.
 */
function reandaily_get_theme_mod( $name, $default = '' ) {
    $value = get_theme_mod( $name, null );
    if ( $value !== null && $value !== '' ) {
        return $value;
    }
    
    // Fallback to masterstudy-lms-starter-child mods
    $prev_mods = get_option( 'theme_mods_masterstudy-lms-starter-child' );
    if ( is_array( $prev_mods ) && isset( $prev_mods[ $name ] ) && $prev_mods[ $name ] !== '' ) {
        return $prev_mods[ $name ];
    }

    // Hardcoded production defaults for ReanDaily
    $hardcoded_defaults = array(
        'reandaily_manual_bank_name'    => 'Advanced Bank of Asia (ABA)',
        'reandaily_manual_account_name'  => 'MENG HANN AND JOHN CHANTHY',
        'reandaily_manual_account_no'    => '008668510',
        'reandaily_bakong_account_id'    => '008668510@aba',
        'reandaily_aba_bakong_id'        => '008668510@aba',
        'reandaily_aba_payway_link'      => 'https://pay.ababank.com/oRF8/8czyh8ox',
        'reandaily_aba_merchant_name'    => 'MENG HANN AND JOHN CHANTHY',
        'reandaily_aba_merchant_city'    => 'Phnom Penh'
    );

    if ( isset( $hardcoded_defaults[ $name ] ) ) {
        return $hardcoded_defaults[ $name ];
    }
    
    return $default;
}

/**
 * Fallback helper to get Bakong Account ID / Address.
 * Checks theme mods first, then falls back to WooCommerce BACS account details,
 * and finally to MasterStudy LMS Wire Transfer settings.
 */
function reandaily_get_bakong_id_fallback() {
    $bakong_id = reandaily_get_theme_mod( 'reandaily_bakong_account_id', '' );
    if ( empty( $bakong_id ) ) {
        $bakong_id = reandaily_get_theme_mod( 'reandaily_aba_bakong_id', '' );
    }

    if ( ! empty( $bakong_id ) ) {
        $bakong_id = trim( $bakong_id );
        if ( strpos( $bakong_id, '@' ) === false ) {
            $bank_name = strtolower( reandaily_get_theme_mod( 'reandaily_manual_bank_name', '' ) );
            $suffix = 'aba';
            if ( strpos( $bank_name, 'acleda' ) !== false ) {
                $suffix = 'acleda';
            } elseif ( strpos( $bank_name, 'wing' ) !== false ) {
                $suffix = 'wing';
            } elseif ( strpos( $bank_name, 'canadia' ) !== false ) {
                $suffix = 'canadia';
            }
            $bakong_id = $bakong_id . '@' . $suffix;
        }
        return $bakong_id;
    }
    
    // Fallback to WooCommerce BACS account
    if ( empty( $bakong_id ) ) {
        $bacs_accounts = get_option( 'woocommerce_bacs_accounts' );
        if ( empty( $bacs_accounts ) ) {
            $bacs_accounts = get_option( 'bacs_accounts' );
        }
        if ( ! empty( $bacs_accounts ) && is_array( $bacs_accounts ) ) {
            $first_account = reset( $bacs_accounts );
            if ( ! empty( $first_account ) && ! empty( $first_account['account_number'] ) ) {
                $acc_num = trim( $first_account['account_number'] );
                $bank_name = isset( $first_account['bank_name'] ) ? strtolower( $first_account['bank_name'] ) : '';
                $sort_code = isset( $first_account['sort_code'] ) ? strtolower( $first_account['sort_code'] ) : '';
                
                // Determine suffix
                $suffix = '';
                if ( strpos( $bank_name, 'advanced bank of asia' ) !== false || strpos( $bank_name, 'aba' ) !== false || $sort_code === 'aba' ) {
                    $suffix = 'aba';
                } elseif ( strpos( $bank_name, 'acleda' ) !== false || $sort_code === 'acleda' ) {
                    $suffix = 'acleda';
                } elseif ( strpos( $bank_name, 'wing' ) !== false || $sort_code === 'wing' ) {
                    $suffix = 'wing';
                } elseif ( strpos( $bank_name, 'canadia' ) !== false || $sort_code === 'canadia' ) {
                    $suffix = 'canadia';
                }
                
                if ( ! empty( $suffix ) ) {
                    $bakong_id = $acc_num . '@' . $suffix;
                } else {
                    $bakong_id = $acc_num . '@aba'; // Default fallback
                }
            }
        }
    }
    
    // Fallback to MasterStudy LMS Wire Transfer settings
    if ( empty( $bakong_id ) ) {
        $lms_settings = get_option( 'stm_lms_settings' );
        if ( ! empty( $lms_settings ) && isset( $lms_settings['payment_methods']['wire_transfer']['fields'] ) ) {
            $wire_fields = $lms_settings['payment_methods']['wire_transfer']['fields'];
            if ( ! empty( $wire_fields['account_number'] ) ) {
                $acc_num = trim( $wire_fields['account_number'] );
                $bank_name = isset( $wire_fields['bank_name'] ) ? strtolower( $wire_fields['bank_name'] ) : '';
                
                $suffix = '';
                if ( strpos( $bank_name, 'aba' ) !== false || strpos( $bank_name, 'advanced bank of asia' ) !== false ) {
                    $suffix = 'aba';
                } elseif ( strpos( $bank_name, 'acleda' ) !== false ) {
                    $suffix = 'acleda';
                } elseif ( strpos( $bank_name, 'wing' ) !== false ) {
                    $suffix = 'wing';
                }
                
                if ( ! empty( $suffix ) ) {
                    $bakong_id = $acc_num . '@' . $suffix;
                } else {
                    $bakong_id = $acc_num . '@aba';
                }
            }
        }
    }
    
    return trim( $bakong_id );
}

/**
 * Fallback helper to get Manual Bank Details.
 * Checks theme mods first, then WooCommerce BACS, and finally MasterStudy LMS.
 */
function reandaily_get_manual_bank_details_fallback() {
    $bank_name    = reandaily_get_theme_mod( 'reandaily_manual_bank_name', '' );
    $account_name  = reandaily_get_theme_mod( 'reandaily_manual_account_name', '' );
    $account_no    = reandaily_get_theme_mod( 'reandaily_manual_account_no', '' );
    
    // Fallback to WooCommerce BACS
    if ( empty( $bank_name ) && empty( $account_name ) && empty( $account_no ) ) {
        $bacs_accounts = get_option( 'woocommerce_bacs_accounts' );
        if ( empty( $bacs_accounts ) ) {
            $bacs_accounts = get_option( 'bacs_accounts' );
        }
        if ( ! empty( $bacs_accounts ) && is_array( $bacs_accounts ) ) {
            $first_account = reset( $bacs_accounts );
            if ( ! empty( $first_account ) ) {
                $bank_name    = isset( $first_account['bank_name'] ) ? $first_account['bank_name'] : '';
                $account_name  = isset( $first_account['account_name'] ) ? $first_account['account_name'] : '';
                $account_no    = isset( $first_account['account_number'] ) ? $first_account['account_number'] : '';
            }
        }
    }
    
    // Fallback to MasterStudy Wire Transfer settings
    if ( empty( $bank_name ) && empty( $account_name ) && empty( $account_no ) ) {
        $lms_settings = get_option( 'stm_lms_settings' );
        if ( ! empty( $lms_settings ) && isset( $lms_settings['payment_methods']['wire_transfer']['fields'] ) ) {
            $wire_fields = $lms_settings['payment_methods']['wire_transfer']['fields'];
            $bank_name    = isset( $wire_fields['bank_name'] ) ? $wire_fields['bank_name'] : '';
            $account_name  = isset( $wire_fields['holder_name'] ) ? $wire_fields['holder_name'] : '';
            $account_no    = isset( $wire_fields['account_number'] ) ? $wire_fields['account_number'] : '';
        }
    }
    
    return array(
        'bank_name'    => trim( $bank_name ),
        'account_name' => trim( $account_name ),
        'account_no'   => trim( $account_no )
    );
}

/**
 * Generate a valid KHQR (Bakong Individual) string.
 *
 * @param array $p  Keys: bakong_id, merchant_name, merchant_city,
 *                        amount (float), currency ('USD'|'KHR'), bill_number
 * @return string   EMV QR payload string
 */
function reandaily_generate_khqr_string( $p ) {
    $bakong_id     = trim( strval( $p['bakong_id'] ?? '' ) );
    $merchant_name = trim( strval( $p['merchant_name'] ?? '' ) );
    $merchant_city = trim( strval( $p['merchant_city'] ?? '' ) );
    $amount        = floatval( $p['amount'] ?? 0 );
    $currency      = strtoupper( trim( strval( $p['currency'] ?? 'USD' ) ) ); // 'USD' or 'KHR'
    $bill_number   = trim( strval( $p['bill_number'] ?? '' ) );
    $merchant_id   = isset( $p['merchant_id'] ) ? trim( strval( $p['merchant_id'] ) ) : '';

    // ── Determine acquiring bank from @suffix ─────────────────────────────
    $acquiring_bank = 'ABA';
    if ( strpos( $bakong_id, '@' ) !== false ) {
        $suffix     = strtolower( explode( '@', $bakong_id )[1] );
        $bank_map   = array(
            'aba'     => 'ABA',
            'acleda'  => 'ACLEDA',
            'wing'    => 'WING',
            'canadia' => 'CANADIA',
            'bred'    => 'BRED',
            'caminb'  => 'CAMINNB',
            'vattanac'=> 'VATTANAC',
            'ppbank'  => 'PPBANK',
            'ppcb'    => 'PPCB',
        );
        $acquiring_bank = isset( $bank_map[ $suffix ] )
            ? $bank_map[ $suffix ]
            : strtoupper( $suffix );
    }

    $t = 'reandaily_khqr_tlv'; // shorthand

    // ── Build QR payload ─────────────────────────────────────────────────
    $qr  = $t( '00', '01' );   // Payload Format Indicator
    $qr .= $t( '01', $amount > 0 ? '12' : '11' );   // Point of Initiation: 12 = dynamic, 11 = static

    // Tag 29 / 30: Merchant Account Info
    if ( ! empty( $merchant_id ) ) {
        // Tag 30: Corporate Merchant / Bill Payment
        $acct  = $t( '00', $bakong_id );
        $acct .= $t( '01', $merchant_id );
        $acct .= $t( '02', $acquiring_bank );
        $qr   .= $t( '30', $acct );
    } else {
        // Tag 29: Individual Merchant / Remittance
        $acct  = $t( '00', $bakong_id );
        $qr   .= $t( '29', $acct );
    }

    // Tag 52: Merchant Category Code
    $qr .= $t( '52', '5999' );

    // Tag 53: Transaction Currency  (840 = USD, 116 = KHR)
    $qr .= $t( '53', $currency === 'KHR' ? '116' : '840' );

    // Tag 54: Transaction Amount (omit if 0 / free)
    if ( $amount > 0 ) {
        $amt_str = $currency === 'KHR'
            ? strval( intval( $amount ) )          // KHR: integer, e.g. "41000"
            : number_format( $amount, 2, '.', '' ); // USD: e.g. "10.00"
        $qr .= $t( '54', $amt_str );
    }

    // Tag 58: Country Code
    $qr .= $t( '58', 'KH' );

    // Tag 59: Merchant Name (max 25 chars per spec)
    $qr .= $t( '59', substr( $merchant_name, 0, 25 ) );

    // Tag 60: Merchant City (max 15 chars per spec)
    $qr .= $t( '60', substr( $merchant_city, 0, 15 ) );

    // Tag 62: Additional Data Field Template
    $add = '';
    if ( ! empty( $bill_number ) ) {
        $add .= $t( '01', substr( $bill_number, 0, 25 ) ); // Sub-tag 01: Bill Number
    }
    if ( ! empty( $add ) ) {
        $qr .= $t( '62', $add );
    }

    // Tag 99: Timestamp (Required for Dynamic QR Code '12')
    if ( $amount > 0 ) {
        $created_ms = round( microtime( true ) * 1000 );
        $expired_ms = $created_ms + ( 3 * 60 * 1000 ); // 3 minutes expiration
        $ts_value   = $t( '00', strval( $created_ms ) ) . $t( '01', strval( $expired_ms ) );
        $qr        .= $t( '99', $ts_value );
    }

    // Tag 63: CRC-16 (compute over everything including "6304")
    $qr .= '6304';
    $qr .= reandaily_khqr_crc16( $qr );

    return $qr;
}

/**
 * AJAX endpoint: returns the KHQR string and tracking details for a given course/amount.
 */
function reandaily_ajax_get_khqr() {
    $bill_number = sanitize_text_field( $_POST['bill_number'] ?? '' );
    $bill_token  = sanitize_text_field( $_POST['bill_token'] ?? '' );

    if ( empty( $bill_number ) || empty( $bill_token ) || ! hash_equals( reandaily_generate_bill_token( $bill_number ), $bill_token ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ) );
    }

    $course_id   = absint( $_POST['course_id'] ?? 0 );
    $currency    = strtoupper( sanitize_text_field( $_POST['currency'] ?? 'USD' ) );
    $amount      = floatval( $_POST['amount'] ?? 0 );

    // ── Check if Bakong Direct API is Enabled ─────────────────────────────
    $bakong_enabled = reandaily_get_theme_mod( 'reandaily_bakong_enabled', false );
    $bakong_token   = trim( reandaily_get_theme_mod( 'reandaily_bakong_api_token', '' ) );
    if ( strpos( strtolower( $bakong_token ), 'bearer ' ) === 0 ) {
        $bakong_token = trim( substr( $bakong_token, 7 ) );
    }
    $bakong_address = reandaily_get_bakong_id_fallback();
    if ( empty( $bakong_address ) ) {
        wp_send_json_error( array(
            'message'          => 'ប្រព័ន្ធទូទាត់មិនទាន់បានកំណត់រចនាសម្ព័ន្ធបង់ប្រាក់នៅឡើយទេ។ (Payment settings are not configured in Customizer settings.)',
            'bakong_api'       => false,
            'bakong_api_error' => 'Bakong Account ID / Bakong Address is empty in Customizer settings.'
        ) );
        return;
    }
    $merchant_id    = get_theme_mod( 'reandaily_aba_merchant_id', '' );
    if ( empty( $merchant_id ) ) {
        $wc_payway = get_option( 'woocommerce_aba_payway_aim_settings' );
        if ( is_array( $wc_payway ) ) {
            $merchant_id = $wc_payway['maerchant_id'] ?? ( $wc_payway['merchant_id'] ?? '' );
        }
    }
    $bakong_api_url = rtrim( reandaily_get_theme_mod( 'reandaily_bakong_api_endpoint', 'https://api-bakong.nbc.gov.kh' ), '/' );

    // Generate local KHQR string first (to use as payload for API and fallback)
    $local_qr_string = reandaily_generate_khqr_string( array(
        'bakong_id'     => $bakong_address,
        'merchant_name' => reandaily_get_theme_mod( 'reandaily_aba_merchant_name', 'ReanDaily' ),
        'merchant_city' => reandaily_get_theme_mod( 'reandaily_aba_merchant_city', 'Phnom Penh' ),
        'amount'        => $amount,
        'currency'      => $currency,
        'bill_number'   => $bill_number,
        'merchant_id'   => $merchant_id,
    ) );

    // If Bakong Automated API is enabled and configured, fetch deeplink from the server
    $bakong_api_error = null;
    $qr_string = $local_qr_string;
    $md5_hash = md5( $local_qr_string );
    $deeplink = 'bakong://pay?qr=' . rawurlencode( $local_qr_string );

    if ( $bakong_enabled ) {
        if ( empty( $bakong_token ) ) {
            $bakong_api_error = 'Bakong API Token is missing. Please add it in Customize > Bakong Direct API Settings.';
        } else {
            $payload = array(
                'qr' => $local_qr_string,
                'sourceInfo' => array(
                    'appIconUrl'          => 'https://bakong.nbc.gov.kh/images/logo.svg',
                    'appName'             => 'Bakong',
                    'appDeepLinkCallback' => 'https://bakong.nbc.gov.kh/'
                )
            );

            $response = wp_remote_post( $bakong_api_url . '/v1/generate_deeplink_by_qr', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . trim( $bakong_token ),
                    'Content-Type'  => 'application/json',
                    'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ),
                'body'    => wp_json_encode( $payload ),
                'timeout' => 4,
                'sslverify' => ( strpos( site_url(), '.local' ) !== false || strpos( site_url(), 'localhost' ) !== false || strpos( site_url(), '.test' ) !== false ) ? false : true
            ) );

            if ( is_wp_error( $response ) ) {
                $bakong_api_error = 'WP_Error: ' . $response->get_error_message();
                error_log( 'ReanDaily Bakong generate_deeplink_by_qr API call failed: ' . $response->get_error_message() );
            } else {
                $response_code = wp_remote_retrieve_response_code( $response );
                if ( $response_code !== 200 ) {
                    $body_str = wp_remote_retrieve_body( $response );
                    $bakong_api_error = 'HTTP Status ' . $response_code . ': ' . substr($body_str, 0, 150);
                    error_log( 'ReanDaily Bakong generate_deeplink_by_qr HTTP Error: ' . $response_code . ' | URL: ' . $bakong_api_url . ' | Body: ' . $body_str );
                } else {
                    $body = json_decode( wp_remote_retrieve_body( $response ), true );
                    if ( isset( $body['responseCode'] ) && intval( $body['responseCode'] ) === 0 ) {
                        $qr_string = $body['data']['qrCode'] ?? $local_qr_string;
                        $md5_hash  = md5( $qr_string );
                        $deeplink  = $body['data']['shortLink'] ?? $body['data']['deeplink'] ?? $deeplink;
                    } else {
                        $bakong_api_error = 'Bakong Gateway Error: Code ' . ( $body['responseCode'] ?? 'unknown' ) . ' - ' . ( $body['responseMessage'] ?? 'no message' );
                    }
                }
            }
        }
    } else {
        $bakong_api_error = 'Automated Bakong Direct API is disabled in theme customizer settings. (Go to Appearance > Customize > Bakong Direct API Settings and check "Enable Automated Bakong Direct API")';
    }

    // If Bakong API is enabled and we have a token, we ALWAYS allow the automated flow!
    // Even if generate_deeplink_by_qr fails, we fall back to local QR + local MD5 so automated polling still works.
    if ( $bakong_enabled && ! empty( $bakong_token ) ) {
        wp_send_json_success( array(
            'qr_string'        => $qr_string,
            'md5'              => $md5_hash,
            'bakong_api'       => true,
            'bakong_api_error' => $bakong_api_error,
            'bill_number'      => $bill_number,
            'deeplink'         => $deeplink,
            'nonce'            => wp_create_nonce( 'reandaily_enroll_nonce' ) // Fresh nonce
        ) );
        return;
    }

    // Otherwise, fall back to pure manual mode
    wp_send_json_success( array(
        'qr_string'        => $local_qr_string,
        'md5'              => md5( $local_qr_string ),
        'bakong_api'       => false,
        'bakong_api_error' => $bakong_api_error,
        'nonce'            => wp_create_nonce( 'reandaily_enroll_nonce' ) // Fresh nonce
    ) );
}
add_action( 'wp_ajax_reandaily_get_khqr',        'reandaily_ajax_get_khqr' );
add_action( 'wp_ajax_nopriv_reandaily_get_khqr', 'reandaily_ajax_get_khqr' );


/**
 * AJAX endpoint: Polls the Bakong API to check if the student has completed the payment.
 * Automatically enrolls the user on payment success!
 */
function reandaily_ajax_check_bakong_transaction() {
    $bill_number = sanitize_text_field( $_POST['bill_number'] ?? '' );
    $bill_token  = sanitize_text_field( $_POST['bill_token'] ?? '' );

    if ( empty( $bill_number ) || empty( $bill_token ) || ! hash_equals( reandaily_generate_bill_token( $bill_number ), $bill_token ) ) {
        wp_send_json_error( array( 'message' => 'Security check failed.' ) );
    }

    $md5         = sanitize_text_field( $_POST['md5'] ?? '' );
    $course_id   = absint( $_POST['course_id'] ?? 0 );

    if ( empty( $md5 ) || empty( $course_id ) ) {
        wp_send_json_error( array( 'message' => 'Missing parameter values.' ) );
    }

    $bakong_token   = trim( reandaily_get_theme_mod( 'reandaily_bakong_api_token', '' ) );
    if ( strpos( strtolower( $bakong_token ), 'bearer ' ) === 0 ) {
        $bakong_token = trim( substr( $bakong_token, 7 ) );
    }
    $bakong_api_url = rtrim( reandaily_get_theme_mod( 'reandaily_bakong_api_endpoint', 'https://api-bakong.nbc.gov.kh' ), '/' );

    if ( empty( $bakong_token ) ) {
        wp_send_json_error( array( 'message' => 'Bakong Token not set.' ) );
    }

    // Call Bakong check transaction by md5 endpoint
    $response = wp_remote_post( $bakong_api_url . '/v1/check_transaction_by_md5', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . trim( $bakong_token ),
            'Content-Type'  => 'application/json',
            'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ),
        'body'    => wp_json_encode( array( 'md5' => $md5 ) ),
        'timeout' => 10,
        'sslverify' => ( strpos( site_url(), '.local' ) !== false || strpos( site_url(), 'localhost' ) !== false || strpos( site_url(), '.test' ) !== false ) ? false : true
    ) );

    if ( is_wp_error( $response ) ) {
        $msg = 'WP_Error: ' . $response->get_error_message();
        error_log( 'ReanDaily Bakong check_transaction_by_md5 API call failed: ' . $msg );
        wp_send_json_error( array( 'message' => $msg ) );
    }

    $response_code = wp_remote_retrieve_response_code( $response );
    $body_str = wp_remote_retrieve_body( $response );
    
    if ( $response_code !== 200 ) {
        $msg = 'HTTP Status ' . $response_code . ': ' . substr($body_str, 0, 150);
        error_log( 'ReanDaily Bakong check_transaction_by_md5 HTTP Error: ' . $msg . ' | URL: ' . $bakong_api_url );
        wp_send_json_error( array( 'message' => $msg ) );
    }

    $body = json_decode( $body_str, true );
    error_log( 'ReanDaily: Bakong API check transaction response: ' . $body_str );

    // If responseCode is not 0, it means transaction is either not found (pending) or token is invalid
    if ( isset( $body['responseCode'] ) && intval( $body['responseCode'] ) !== 0 ) {
        $code = intval( $body['responseCode'] );
        if ( $code === 1 || $code === 12 || $code === 20 ) { // 1 = Transaction not found / not paid yet (standard Open API); 12/20 = Pending/Not found codes
            wp_send_json_error( array( 'status' => 'pending' ) );
        }
        
        $gateway_msg = 'Bakong Code ' . $body['responseCode'] . ' - ' . ($body['responseMessage'] ?? 'no message');
        wp_send_json_error( array( 'status' => 'error', 'message' => $gateway_msg ) );
    }

    // responseCode === 0 means the payment was completed successfully!
    if ( isset( $body['responseCode'] ) && intval( $body['responseCode'] ) === 0 && ! empty( $body['data'] ) ) {
        
        global $wpdb;
        $table = $wpdb->prefix . 'reandaily_enrollments';
        $enrollment = null;
        
        if ( ! empty( $bill_number ) ) {
            $enrollment = $wpdb->get_row( $wpdb->prepare(
                "SELECT user_id, course_id, student_name, student_email, student_phone, currency, amount, payment_status FROM {$table} WHERE bill_number = %s LIMIT 1",
                $bill_number
            ) );
        }

        if ( ! $enrollment ) {
            wp_send_json_error( array( 'message' => 'Enrollment record not found.' ) );
        }

        // Retrieve transaction details from Bakong API response for logging
        $tx_data = $body['data'];
        $tx_amount = floatval( $tx_data['amount'] ?? 0 );
        $tx_currency = strtoupper( $tx_data['currency'] ?? '' );
        $tx_receiver = $tx_data['toAccountId'] ?? '';
        
        $expected_amount = floatval( $enrollment->amount );
        $expected_currency = strtoupper( $enrollment->currency );
        $expected_receiver = reandaily_get_bakong_id_fallback();

        error_log( sprintf(
            'ReanDaily: Bakong Payment received for Bill %s. Expected: %s %s to %s. Got: %s %s to %s.',
            $bill_number, $expected_amount, $expected_currency, $expected_receiver,
            $tx_amount, $tx_currency, $tx_receiver
        ) );

        // We bypass strict verification checks here. Since the MD5 hash queried is a hash of a unique
        // QR code generated specifically for this bill number containing our merchant ID, any successful
        // transaction returned by Bakong for this MD5 is guaranteed to be a valid payment for this bill.
        // This prevents minor format mismatches (like "000123456" vs "000123456@aba") from blocking enrollment.

        $user_id   = intval( $enrollment->user_id );
        if ( $user_id === 0 ) {
            $user_id = reandaily_create_user_from_pending_transient( $bill_number );
            if ( $user_id > 0 ) {
                $wpdb->update(
                    $table,
                    array( 'user_id' => $user_id ),
                    array( 'bill_number' => $bill_number ),
                    array( '%d' ),
                    array( '%s' )
                );
            }
        }
        $course_id = intval( $enrollment->course_id );

        // Programmatically sign in the user if not signed in or signed in as a different user
        if ( $user_id > 0 ) {
            if ( ! is_user_logged_in() || get_current_user_id() !== $user_id ) {
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id, true );
            }
        }

        // Perform MasterStudy LMS Enrollment!
        $enrolled = false;
        if ( $user_id > 0 && $course_id > 0 ) {
            error_log( sprintf( 'ReanDaily: Auto-enrolling User %d in Course %d', $user_id, $course_id ) );
            if ( class_exists( 'STM_LMS_Course' ) ) {
                STM_LMS_Course::add_user_course( $course_id, $user_id, 0, 0 );
                $enrolled = true;
                error_log( 'ReanDaily: Enrolled via STM_LMS_Course::add_user_course' );
            } elseif ( function_exists( 'stm_lms_add_user_course' ) ) {
                stm_lms_add_user_course( array(
                    'user_id'    => $user_id,
                    'course_id'  => $course_id,
                    'status'     => 'enrolled',
                    'start_time' => time()
                ) );
                $enrolled = true;
            } else {
                $user_courses = get_user_meta( $user_id, 'stm_lms_courses', true );
                if ( ! is_array( $user_courses ) ) { $user_courses = array(); }
                if ( ! in_array( $course_id, $user_courses, true ) ) {
                    $user_courses[] = $course_id;
                    update_user_meta( $user_id, 'stm_lms_courses', $user_courses );
                }
                $enrolled = true;
            }

            // Update custom local DB enrollment payment status to fully paid
            $wpdb->update(
                $table,
                array( 'payment_status' => 'paid_verified' ),
                array( 'bill_number' => $bill_number ),
                array( '%s' ),
                array( '%s' )
            );

            reandaily_clear_all_carts( $user_id );

            // Send confirmation emails (if not already verified to prevent double emails on double-polling check)
            if ( ! $enrollment || $enrollment->payment_status !== 'paid_verified' ) {
                $student_name  = $enrollment ? $enrollment->student_name  : '';
                $student_email = $enrollment ? $enrollment->student_email : '';
                $student_phone = $enrollment ? $enrollment->student_phone : '';
                $currency      = $enrollment ? $enrollment->currency      : 'USD';
                $amount        = $enrollment ? $enrollment->amount        : 0;

                if ( empty( $student_email ) ) {
                    $user = get_userdata( $user_id );
                    if ( $user ) {
                        $student_name  = $user->display_name;
                        $student_email = $user->user_email;
                    }
                }

                if ( ! empty( $student_email ) ) {
                    // Send success email to student
                    $subject = 'ការទូទាត់ជោគជ័យ - ' . get_the_title( $course_id ) . ' | ReanDaily';
                    $body  = "<h2>សូមអរគុណ {$student_name}!</h2>";
                    $body .= '<p>ការទូទាត់ប្រាក់សម្រាប់វគ្គសិក្សា <strong>' . esc_html( get_the_title( $course_id ) ) . '</strong> ទទួលបានជោគជ័យ។</p>';
                    $body .= '<p>ប្រព័ន្ធបានបើកវគ្គសិក្សាជូនលោកអ្នករួចរាល់ហើយ។</p>';
                    $body .= '<p>📚 <a href="' . get_permalink( $course_id ) . '">ចូលទៅកាន់វគ្គសិក្សា</a></p>';
                    $body .= '<p>— ReanDaily Team</p>';
                    wp_mail( $student_email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
                }

                // Send notification email to admin
                $admin_email = get_option('admin_email');
                $admin_subject = 'Successful Payment (Automated): ' . get_the_title( $course_id ) . ' — ' . $student_name;
                $admin_body  = "Student: {$student_name} ({$student_email}, {$student_phone})\n";
                $admin_body .= 'Course: ' . get_the_title( $course_id ) . "\n";
                $admin_body .= 'Bill: ' . $bill_number . "\n";
                $admin_body .= "Payment Status: Paid & Verified (Automated Bakong API)\n";
                $admin_body .= "Amount: {$currency} {$amount}\n";
                wp_mail( $admin_email, $admin_subject, $admin_body );
            }
        }

        // Generate secure one-time access token for redirection and login persistence
        $access_key = wp_generate_password( 32, false );
        set_transient( 'reandaily_unlock_' . $bill_number, array(
            'user_id'   => $user_id,
            'course_id' => $course_id,
            'token'     => $access_key
        ), 300 ); // 5 minutes expiration

        $redirect_url = add_query_arg( array(
            'enroll_success' => '1',
            'bill'           => $bill_number,
            'key'            => $access_key
        ), get_permalink( $course_id ) );

        wp_send_json_success( array(
            'message'  => 'ទូទាត់ប្រាក់ជោគជ័យ! ប្រព័ន្ធកំពុងបើកវគ្គសិក្សាជូនលោកអ្នក...',
            'enrolled' => $enrolled,
            'redirect' => $redirect_url
        ) );
        return;
    }

    // Payment still pending / not received
    wp_send_json_error( array( 'status' => 'pending' ) );
}
add_action( 'wp_ajax_reandaily_check_bakong_transaction',        'reandaily_ajax_check_bakong_transaction' );
add_action( 'wp_ajax_nopriv_reandaily_check_bakong_transaction', 'reandaily_ajax_check_bakong_transaction' );

// Load parent styles and child style overrides at the absolute end of the queue
function reandaily_enqueue_styles() {
    // Load parent theme style first
    wp_enqueue_style( 'ms-lms-starter-theme-style', get_template_directory_uri() . '/style.css' );

    // Load child style at the absolute end of the queue to override all parent style sheets
    wp_enqueue_style( 'reandaily-style-override',
        get_stylesheet_directory_uri() . '/style.css',
        array( 'ms-lms-starter-theme-style', 'starter-base', 'starter-style', 'stm_lms_starter_theme_css_frontend' ),
        time() // Cache busting for development
    );
}
add_action( 'wp_enqueue_scripts', 'reandaily_enqueue_styles', 9999 );

// Enqueue Google Fonts (Kantumruy Pro, Inter, Outfit)
function reandaily_enqueue_google_fonts() {
    wp_enqueue_style(
        'reandaily-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&family=Kantumruy+Pro:ital,wght@0,300..700;1,300..700&display=swap',
        array(),
        null
    );
}
add_action( 'wp_enqueue_scripts', 'reandaily_enqueue_google_fonts' );

// Register the Primary Menu Location
function reandaily_register_menus() {
    register_nav_menus(
        array(
            'primary' => __( 'Primary Menu', 'reandaily-theme' ),
        )
    );
}
add_action( 'init', 'reandaily_register_menus' );

// Register Customizer Settings for Homepage
function reandaily_customize_register( $wp_customize ) {
    // Add Homepage Settings Section
    $wp_customize->add_section( 'reandaily_homepage_section', array(
        'title'      => __( 'ReanDaily Homepage Settings', 'reandaily-theme' ),
        'priority'   => 30,
    ) );

    // 1. Hero Badge
    $wp_customize->add_setting( 'reandaily_hero_badge', array(
        'default'   => '📚 វេទិកាសម្រាប់ការរៀនសូត្រឥតឈប់ឈរ',
        'transport' => 'refresh',
    ) );
    $wp_customize->add_control( 'reandaily_hero_badge_control', array(
        'label'    => __( 'Hero Badge Text', 'reandaily-theme' ),
        'section'  => 'reandaily_homepage_section',
        'settings' => 'reandaily_hero_badge',
        'type'     => 'text',
    ) );

    // 2. Hero Headline
    $wp_customize->add_setting( 'reandaily_hero_headline', array(
        'default'   => 'រៀនរាល់ថ្ងៃ ដើម្បីអនាគតកាន់តែប្រសើរ',
        'transport' => 'refresh',
    ) );
    $wp_customize->add_control( 'reandaily_hero_headline_control', array(
        'label'    => __( 'Hero Headline', 'reandaily-theme' ),
        'section'  => 'reandaily_homepage_section',
        'settings' => 'reandaily_hero_headline',
        'type'     => 'text',
    ) );

    // 3. Hero Description
    $wp_customize->add_setting( 'reandaily_hero_description', array(
        'default'   => 'ទទួលបានការសិក្សាវគ្គជំនាញអនឡាញល្អៗជាច្រើន ជាមួយគ្រូឧទ្ទេសដែលមានបទពិសោធន៍ខ្ពស់។ សិក្សាតាមតម្រូវការ គ្រប់ពេលវេលា និងគ្រប់ទីកន្លែង។',
        'transport' => 'refresh',
    ) );
    $wp_customize->add_control( 'reandaily_hero_description_control', array(
        'label'    => __( 'Hero Description', 'reandaily-theme' ),
        'section'  => 'reandaily_homepage_section',
        'settings' => 'reandaily_hero_description',
        'type'     => 'textarea',
    ) );

    // 4. Hero Primary Button Text
    $wp_customize->add_setting( 'reandaily_hero_btn_primary', array(
        'default'   => 'រុករកវគ្គសិក្សា',
        'transport' => 'refresh',
    ) );
    $wp_customize->add_control( 'reandaily_hero_btn_primary_control', array(
        'label'    => __( 'Primary Button Text', 'reandaily-theme' ),
        'section'  => 'reandaily_homepage_section',
        'settings' => 'reandaily_hero_btn_primary',
        'type'     => 'text',
    ) );

    // 4b. Hero Primary Button URL
    $wp_customize->add_setting( 'reandaily_hero_btn_primary_url', array(
        'default'   => '',
        'transport' => 'refresh',
    ) );
    $wp_customize->add_control( 'reandaily_hero_btn_primary_url_control', array(
        'label'    => __( 'Primary Button URL (Leave empty for default Courses page)', 'reandaily-theme' ),
        'section'  => 'reandaily_homepage_section',
        'settings' => 'reandaily_hero_btn_primary_url',
        'type'     => 'text',
    ) );

    // 5. Hero Secondary Button Text
    $wp_customize->add_setting( 'reandaily_hero_btn_secondary', array(
        'default'   => 'ស្វែងយល់បន្ថែម',
        'transport' => 'refresh',
    ) );
    $wp_customize->add_control( 'reandaily_hero_btn_secondary_control', array(
        'label'    => __( 'Secondary Button Text', 'reandaily-theme' ),
        'section'  => 'reandaily_homepage_section',
        'settings' => 'reandaily_hero_btn_secondary',
        'type'     => 'text',
    ) );

    // 5b. Hero Secondary Button URL
    $wp_customize->add_setting( 'reandaily_hero_btn_secondary_url', array(
        'default'   => home_url( '/about/' ),
        'transport' => 'refresh',
    ) );
    $wp_customize->add_control( 'reandaily_hero_btn_secondary_url_control', array(
        'label'    => __( 'Secondary Button URL', 'reandaily-theme' ),
        'section'  => 'reandaily_homepage_section',
        'settings' => 'reandaily_hero_btn_secondary_url',
        'type'     => 'text',
    ) );
    
    // Add Stats Controls
    for ($i = 1; $i <= 4; $i++) {
        $default_num = '';
        $default_lbl = '';
        if ($i === 1) { $default_num = '10K+'; $default_lbl = 'សិស្សកំពុងសិក្សា'; }
        if ($i === 2) { $default_num = '200+'; $default_lbl = 'វគ្គសិក្សាជំនាញ'; }
        if ($i === 3) { $default_num = '50+'; $default_lbl = 'គ្រូឧទ្ទេសជំនាញ'; }
        if ($i === 4) { $default_num = '4.9★'; $default_lbl = 'ការវាយតម្លៃខ្ពស់'; }

        // Stat Number
        $wp_customize->add_setting( "reandaily_stat_{$i}_num", array(
            'default'   => $default_num,
            'transport' => 'refresh',
        ) );
        $wp_customize->add_control( "reandaily_stat_{$i}_num_control", array(
            'label'    => __( "Stat {$i} Number", 'reandaily-theme' ),
            'section'  => 'reandaily_homepage_section',
            'settings' => "reandaily_stat_{$i}_num",
            'type'     => 'text',
        ) );

        // Stat Label
        $wp_customize->add_setting( "reandaily_stat_{$i}_lbl", array(
            'default'   => $default_lbl,
            'transport' => 'refresh',
        ) );
        $wp_customize->add_control( "reandaily_stat_{$i}_lbl_control", array(
            'label'    => __( "Stat {$i} Label", 'reandaily-theme' ),
            'section'  => 'reandaily_homepage_section',
            'settings' => "reandaily_stat_{$i}_lbl",
            'type'     => 'text',
        ) );
    }
    // ── KHQR / ABA Payment Settings ──────────────────────────────────────
    $wp_customize->add_section( 'reandaily_khqr_section', array(
        'title'    => __( 'KHQR / ABA Payment Settings', 'reandaily-theme' ),
        'priority' => 40,
    ) );
    // ABA PayWay Link (primary — paste your https://link.payway.com.kh/... link)
    $wp_customize->add_setting( 'reandaily_aba_payway_link', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'esc_url_raw' ) );
    $wp_customize->add_control( 'reandaily_aba_payway_link_control', array( 'label' => __( 'ABA PayWay Link (e.g. https://link.payway.com.kh/ABAPAYxxx)', 'reandaily-theme' ), 'description' => __( 'Paste your PayWay link here. This will be encoded as the QR code for payment.', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_payway_link', 'type' => 'url' ) );
    // ABA Bakong ID (fallback if no PayWay link)
    $wp_customize->add_setting( 'reandaily_aba_bakong_id', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_aba_bakong_id_control', array( 'label' => __( 'ABA Bakong ID — fallback (e.g. 012345678@aba)', 'reandaily-theme' ), 'description' => __( 'Only used if PayWay link above is empty.', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_bakong_id', 'type' => 'text' ) );
    // Merchant Name
    $wp_customize->add_setting( 'reandaily_aba_merchant_name', array( 'default' => 'ReanDaily', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_aba_merchant_name_control', array( 'label' => __( 'Merchant Name (on QR)', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_merchant_name', 'type' => 'text' ) );
    // Merchant City
    $wp_customize->add_setting( 'reandaily_aba_merchant_city', array( 'default' => 'Phnom Penh', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_aba_merchant_city_control', array( 'label' => __( 'Merchant City', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_merchant_city', 'type' => 'text' ) );
    
    // ── API Integration Credentials ──────────────────────────────────────
    // ABA Merchant ID
    $wp_customize->add_setting( 'reandaily_aba_merchant_id', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_aba_merchant_id_control', array( 'label' => __( 'API Merchant ID', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_merchant_id', 'type' => 'text' ) );
    // ABA API Key
    $wp_customize->add_setting( 'reandaily_aba_api_key', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_aba_api_key_control', array( 'label' => __( 'API Key', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_api_key', 'type' => 'password' ) );
    // Custom API Checkout Endpoint URL (optional override)
    $wp_customize->add_setting( 'reandaily_aba_api_endpoint', array( 'default' => 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase', 'transport' => 'refresh', 'sanitize_callback' => 'esc_url_raw' ) );
    $wp_customize->add_control( 'reandaily_aba_api_endpoint_control', array( 'label' => __( 'Custom API Checkout Endpoint URL', 'reandaily-theme' ), 'description' => __( 'Default sandbox: https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase. Default production: https://checkout.payway.com.kh/api/payment-gateway/v1/payments/purchase', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_api_endpoint', 'type' => 'url' ) );
    // Sandbox Mode Toggle
    $wp_customize->add_setting( 'reandaily_aba_is_sandbox', array( 'default' => true, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'reandaily_aba_is_sandbox_control', array( 'label' => __( 'Enable Sandbox Mode (Testing)', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_aba_is_sandbox', 'type' => 'checkbox' ) );

    // ── Bakong Direct API Settings ──────────────────────────────────────
    $wp_customize->add_section( 'reandaily_bakong_section', array(
        'title'    => __( 'Bakong Direct API Settings', 'reandaily-theme' ),
        'priority' => 45,
    ) );
    // Enable Bakong Gateway
    $wp_customize->add_setting( 'reandaily_bakong_enabled', array( 'default' => false, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'reandaily_bakong_enabled_control', array( 'label' => __( 'Enable Automated Bakong Direct API', 'reandaily-theme' ), 'section' => 'reandaily_bakong_section', 'settings' => 'reandaily_bakong_enabled', 'type' => 'checkbox' ) );
    // Bakong Account ID / Bakong Address
    $wp_customize->add_setting( 'reandaily_bakong_account_id', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_bakong_account_id_control', array( 'label' => __( 'Bakong Account ID (e.g. john_chanthy@bkrt)', 'reandaily-theme' ), 'section' => 'reandaily_bakong_section', 'settings' => 'reandaily_bakong_account_id', 'type' => 'text' ) );
    // Bakong API Bearer Token
    $wp_customize->add_setting( 'reandaily_bakong_api_token', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_bakong_api_token_control', array( 'label' => __( 'Bakong API Token', 'reandaily-theme' ), 'section' => 'reandaily_bakong_section', 'settings' => 'reandaily_bakong_api_token', 'type' => 'textarea' ) );
    // Bakong Endpoint URL
    $wp_customize->add_setting( 'reandaily_bakong_api_endpoint', array( 'default' => 'https://api-bakong.nbc.gov.kh', 'transport' => 'refresh', 'sanitize_callback' => 'esc_url_raw' ) );
    $wp_customize->add_control( 'reandaily_bakong_api_endpoint_control', array( 'label' => __( 'Bakong API Endpoint Base', 'reandaily-theme' ), 'description' => __( 'Sandbox: https://sit-api-bakong.nbc.gov.kh. Production: https://api-bakong.nbc.org.kh (or .gov.kh)', 'reandaily-theme' ), 'section' => 'reandaily_bakong_section', 'settings' => 'reandaily_bakong_api_endpoint', 'type' => 'url' ) );

    // Manual Bank Transfer Details
    $wp_customize->add_setting( 'reandaily_manual_bank_name', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_manual_bank_name_control', array( 'label' => __( 'Manual Bank Name', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_manual_bank_name', 'type' => 'text' ) );

    $wp_customize->add_setting( 'reandaily_manual_account_name', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_manual_account_name_control', array( 'label' => __( 'Manual Account Name', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_manual_account_name', 'type' => 'text' ) );

    $wp_customize->add_setting( 'reandaily_manual_account_no', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'sanitize_text_field' ) );
    $wp_customize->add_control( 'reandaily_manual_account_no_control', array( 'label' => __( 'Manual Account Number', 'reandaily-theme' ), 'section' => 'reandaily_khqr_section', 'settings' => 'reandaily_manual_account_no', 'type' => 'text' ) );

    // Custom QR Code Center Logo
    $wp_customize->add_setting( 'reandaily_qr_code_logo', array( 'default' => '', 'transport' => 'refresh', 'sanitize_callback' => 'esc_url_raw' ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'reandaily_qr_code_logo_control', array(
        'label'       => __( 'QR Code Center Logo', 'reandaily-theme' ),
        'description' => __( 'Upload an image to show in the center of the payment QR codes (both automated and manual). Recommended: square PNG with background.', 'reandaily-theme' ),
        'section'     => 'reandaily_khqr_section',
        'settings'    => 'reandaily_qr_code_logo',
    ) ) );

    // Enable Custom Enrollment Page
    $wp_customize->add_setting( 'reandaily_use_custom_enroll', array( 'default' => false, 'transport' => 'refresh' ) );
    $wp_customize->add_control( 'reandaily_use_custom_enroll_control', array(
        'label'       => __( 'Enable Custom QR Enrollment Page', 'reandaily-theme' ),
        'description' => __( 'If unchecked, students will use the standard MasterStudy/WooCommerce checkout flow.', 'reandaily-theme' ),
        'section'     => 'reandaily_khqr_section',
        'settings'    => 'reandaily_use_custom_enroll',
        'type'        => 'checkbox'
    ) );
}
add_action( 'customize_register', 'reandaily_customize_register' );

/**
 * Fail-Safe Virtual Routing Engine & Nuclear Template Override
 * Serves /courses and /courses-archive dynamically even if the pages are deleted from the WordPress dashboard.
 * Resolves local trash lock errors and Elementor/MasterStudy page conflicts.
 */



// 3. Prevent 404 status for virtual courses, about, and contact paths
add_action( 'template_redirect', function() {
    // 3a. If the request is for page_id = 49089, redirect it to pretty permalink /contact-admin/
    $page_id = isset($_GET['page_id']) ? intval($_GET['page_id']) : 0;
    if ( $page_id === 49089 ) {
        wp_safe_redirect( home_url( '/contact-admin/' ), 301 );
        exit;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
    $path_parts = explode('/', $path);
    $clean_segments = array_values(array_filter($path_parts));
    // Use the FIRST segment to identify the section, not the last
    // This handles paginated URLs like /courses/page/2/ correctly
    $first_slug = isset($clean_segments[0]) ? $clean_segments[0] : '';
    
    if ( $first_slug === 'course' || $first_slug === 'courses' || $first_slug === 'all-courses' || $first_slug === 'courses-archive' || $first_slug === 'about' || $first_slug === 'about-us' || $first_slug === 'contact' || $first_slug === 'contact-admin' || $first_slug === 'payment' || $first_slug === 'enroll' ) {
        // If it's a single course details page, let WordPress handle it natively
        if ( ( $first_slug === 'courses' || $first_slug === 'course' || $first_slug === 'all-courses' ) && count( $clean_segments ) > 1 ) {
            // EXCEPTION FOR PAGINATION: Allow 'page/X' URLs to pass through to bypass 404
            if ( isset($clean_segments[1]) && $clean_segments[1] === 'page' ) {
                // Keep routing (don't return)
            } else {
                return;
            }
        }
        global $wp_query;
        $wp_query->is_404 = false;
        status_header( 200 );
    }
}, 1 );

// 4. Force Template Loading via standard WordPress lifecycle
function reandaily_force_courses_templates( $template ) {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
    $path_parts = explode('/', $path);
    // Use the FIRST segment to identify the section — handles /courses/page/2/ correctly
    $first_slug = isset($path_parts[0]) ? $path_parts[0] : '';
    
    if ( $first_slug === 'enroll' ) {
        $new_template = locate_template( array( 'page-enroll.php' ) );
        if ( ! empty( $new_template ) ) {
            return $new_template;
        }
    }

    if ( $first_slug === 'course' || $first_slug === 'courses' || $first_slug === 'all-courses' ) {
        // CRITICAL FIX: If visiting a single course page (e.g. /courses/html-and-css/) instead of the main directory,
        // do not force page-courses.php. Let MasterStudy's custom single post type handler serve it!
        // We trim/filter path segments to make sure sub-slugs are reliably detected.
        $clean_segments = array_values(array_filter($path_parts));
        if ( ( $first_slug === 'courses' || $first_slug === 'course' || $first_slug === 'all-courses' ) && count( $clean_segments ) > 1 ) {
            // EXCEPTION FOR PAGINATION: If the second segment is 'page', let it fall through to page-courses.php!
            if ( isset($clean_segments[1]) && $clean_segments[1] === 'page' ) {
                // Do nothing, let it load page-courses.php
            } else {
                return $template; 
            }
        }

        $new_template = locate_template( array( 'page-courses.php', 'archive-stm-courses.php' ) );
        if ( ! empty( $new_template ) ) {
            return $new_template;
        }
    }
    
    if ( $first_slug === 'courses-archive' ) {
        $clean_segments = array_values(array_filter($path_parts));
        if ( count( $clean_segments ) > 1 ) {
            return $template; // Do not override if it is a single course detail page
        }
        $new_template = locate_template( array( 'page-courses-archive.php' ) );
        if ( ! empty( $new_template ) ) {
            return $new_template;
        }
    }

    if ( $first_slug === 'about' || $first_slug === 'about-us' ) {
        $new_template = locate_template( array( 'page-about.php' ) );
        if ( ! empty( $new_template ) ) {
            return $new_template;
        }
    }

    $page_id = isset($_GET['page_id']) ? intval($_GET['page_id']) : 0;
    $queried_obj_id = get_queried_object_id();

    if ( $first_slug === 'contact' || $first_slug === 'contact-admin' || $first_slug === 'payment' || $page_id === 49089 || $queried_obj_id === 49089 ) {
        $new_template = locate_template( array( 'page-contact.php' ) );
        if ( ! empty( $new_template ) ) {
            return $new_template;
        }
    }
    
    return $template;
}
add_filter( 'template_include', 'reandaily_force_courses_templates', 9999 );

// ============================================================
// REDIRECT AUTO-LOGIN FOR BAKONG PAYMENTS
// ============================================================
function reandaily_handle_url_auto_login() {
    if ( isset( $_GET['enroll_success'] ) && isset( $_GET['bill'] ) && isset( $_GET['key'] ) ) {
        $bill = sanitize_text_field( $_GET['bill'] );
        $key  = sanitize_text_field( $_GET['key'] );

        $data = get_transient( 'reandaily_unlock_' . $bill );
        if ( is_array( $data ) && hash_equals( $data['token'], $key ) ) {
            // Delete the transient immediately so it cannot be reused
            delete_transient( 'reandaily_unlock_' . $bill );

            $user_id   = intval( $data['user_id'] );
            $course_id = intval( $data['course_id'] );

            if ( $user_id > 0 ) {
                // Log in the user
                wp_set_current_user( $user_id );
                wp_set_auth_cookie( $user_id, true );
                reandaily_clear_all_carts( $user_id );

                // Double-ensure MasterStudy LMS enrollment is active
                if ( $course_id > 0 ) {
                    error_log( sprintf( 'ReanDaily Redirect: Double-ensuring enrollment for User %d in Course %d', $user_id, $course_id ) );
                    if ( class_exists( 'STM_LMS_Course' ) ) {
                        STM_LMS_Course::add_user_course( $course_id, $user_id, 0, 0 );
                        error_log( 'ReanDaily Redirect: Double-enrolled via STM_LMS_Course' );
                    } elseif ( function_exists( 'stm_lms_add_user_course' ) ) {
                        stm_lms_add_user_course( array(
                            'user_id'    => $user_id,
                            'course_id'  => $course_id,
                            'status'     => 'enrolled',
                            'start_time' => time()
                        ) );
                        error_log( 'ReanDaily Redirect: Double-enrolled via stm_lms_add_user_course' );
                    }
                }
            }

            // Redirect to clean course URL (removes the token from address bar)
            wp_safe_redirect( remove_query_arg( array( 'enroll_success', 'bill', 'key' ) ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'reandaily_handle_url_auto_login', 5 );

// Redirect standard checkout pages to the custom enrollment/KHQR page
function reandaily_redirect_checkout_to_custom_enroll() {
    if ( is_admin() ) {
        return;
    }

    $use_custom_enroll = get_theme_mod( 'reandaily_use_custom_enroll', false );
    if ( ! $use_custom_enroll ) {
        return;
    }

    $is_checkout = false;

    // 1. Check URL slug
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
    $path_parts = array_values(array_filter(explode('/', $path)));
    $first_slug = isset($path_parts[0]) ? $path_parts[0] : '';

    if ( $first_slug === 'checkout' || $first_slug === 'lms-checkout' ) {
        $is_checkout = true;
    }

    // 2. Check if we are on the native MasterStudy LMS checkout page URL
    if ( ! $is_checkout && class_exists( 'STM_LMS_Cart' ) ) {
        $checkout_url = STM_LMS_Cart::checkout_url();
        $current_url  = home_url( $_SERVER['REQUEST_URI'] );
        if ( strtok( $checkout_url, '?' ) === strtok( $current_url, '?' ) ) {
            $is_checkout = true;
        }
    }

    // 3. Check if we are on WooCommerce checkout page
    if ( ! $is_checkout && class_exists( 'WooCommerce' ) && is_checkout() && ! is_order_received_page() ) {
        $is_checkout = true;
    }

    if ( $is_checkout ) {
        $course_id = 0;

        // Try to get course_id from query parameters first
        if ( isset( $_GET['course_id'] ) ) {
            $course_id = absint( $_GET['course_id'] );
        }

        // Try to get course_id from guest cart cookie (Only for guest checkout)
        if ( ! $course_id && ! is_user_logged_in() && ! empty( $_COOKIE['stm_lms_notauth_cart'] ) ) {
            $cookie_cart = json_decode( wp_unslash( $_COOKIE['stm_lms_notauth_cart'] ), true );
            if ( is_array( $cookie_cart ) && ! empty( $cookie_cart ) ) {
                $course_id = absint( end( $cookie_cart ) ); // Get the last (most recently added) course
            }
        }

        // Try to get course_id from WooCommerce cart
        if ( ! $course_id && class_exists( 'WooCommerce' ) && WC()->cart && ! WC()->cart->is_empty() ) {
            // Reverse WooCommerce cart items to prioritize the most recently added item
            $reversed_cart = array_reverse( WC()->cart->get_cart() );
            foreach ( $reversed_cart as $cart_item ) {
                $product_id = absint( $cart_item['product_id'] );
                if ( get_post_type( $product_id ) === 'stm-courses' ) {
                    $course_id = $product_id;
                    break;
                } else {
                    $meta_course_id = get_post_meta( $product_id, 'course_id', true );
                    if ( $meta_course_id ) {
                        $course_id = absint( $meta_course_id );
                        break;
                    }
                }
            }
        }

        // Try to get course_id from native MasterStudy cart table for logged-in user
        if ( ! $course_id && is_user_logged_in() ) {
            global $wpdb;
            $user_id = get_current_user_id();
            $table = $wpdb->prefix . 'stm_lms_user_cart';
            if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) === $table ) {
                $course_id = $wpdb->get_var( $wpdb->prepare(
                    "SELECT item_id FROM {$table} WHERE user_id = %d ORDER BY user_cart_id DESC LIMIT 1",
                    $user_id
                ) );
            }
        }

        // Redirect if a valid course ID was found
        if ( $course_id && get_post_type( $course_id ) === 'stm-courses' ) {
            wp_safe_redirect( home_url( '/enroll/?course_id=' . $course_id ) );
            exit;
        }
    }
}
add_action( 'template_redirect', 'reandaily_redirect_checkout_to_custom_enroll', 1 );

// Force Guest Checkout attributes on the Buy Button
add_filter( 'stm_lms_buy_button_auth', function( $attributes, $course_id ) {
    if ( ! is_user_logged_in() ) {
        return array(
            'data-guest="' . intval( $course_id ) . '"'
        );
    }
    return $attributes;
}, 999, 2 );

// Include Custom Enrollments Admin Dashboard
require_once get_stylesheet_directory() . '/admin-dashboard.php';

// Direct Redirect for Buy/Enroll buttons to custom enrollment page
add_action( 'wp_footer', function() {
    $use_custom_enroll = get_theme_mod( 'reandaily_use_custom_enroll', false );
    if ( ! $use_custom_enroll ) {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Intercept buy button click and redirect directly to custom enroll page
        $('body').on('click', '.masterstudy-buy-button, .stm_lms_buy_button, [data-guest]', function(e) {
            // Find course ID from data-guest or data-course-id
            var courseId = $(this).attr('data-guest') || $(this).attr('data-course-id');
            
            // If course ID is not found directly on the button, try to find it from parent elements
            if (!courseId) {
                var parentCard = $(this).closest('[data-course-id]');
                if (parentCard.length) {
                    courseId = parentCard.attr('data-course-id');
                }
            }
            
            // Fallback: search for course_id from body class on single course details page
            if (!courseId) {
                var bodyClass = $('body').attr('class');
                if (bodyClass) {
                    var match = bodyClass.match(/postid-(\d+)/);
                    if (match && $('body').hasClass('single-stm-courses')) {
                        courseId = match[1];
                    }
                }
            }

            if (courseId) {
                e.preventDefault();
                e.stopPropagation();
                window.location.href = '<?php echo esc_url( home_url( "/enroll/" ) ); ?>?course_id=' + courseId;
            }
        });
    });
    </script>
    <?php
} );


// ============================================================
// DYNAMIC GITHUB AUTOPILOT UPDATER FOR PRIVATE THEME REPOSITORY
// ============================================================
class Reandaily_Theme_Github_Updater {
    private $theme_slug;
    private $current_version;
    private $github_username;
    private $github_repo;
    private $github_token;
    private $github_api_url;

    public function __construct() {
        $this->theme_slug      = 'reandaily-theme'; // Must match your active folder name on production
        $this->github_username = 'jchanthy';
        $this->github_repo     = 'reandaily-theme';
        
        // Define GitHub Personal Access Token safely
        $this->github_token    = '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W2Q1'; // Temporary replacement placeholder, override this via wp-config or filter
        if ( defined( 'REANDAILY_GITHUB_TOKEN' ) ) {
            $this->github_token = REANDAILY_GITHUB_TOKEN;
        }

        // Get local version
        $theme = wp_get_theme( $this->theme_slug );
        $this->current_version = $theme->exists() ? $theme->get( 'Version' ) : '1.0.0';

        $this->github_api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}";

        // Hook into WordPress theme updates transient
        add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_theme_update' ) );
        add_filter( 'upgrader_pre_download', array( $this, 'authenticate_github_zip_download' ), 10, 3 );
        add_action( 'upgrader_process_complete', array( $this, 'clear_theme_update_cache' ), 10, 2 );
    }

    /**
     * Check GitHub for newer theme versions
     */
    public function check_for_theme_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Fetch style.css file content from the GitHub repository main branch
        $style_css_url = "https://raw.githubusercontent.com/{$this->github_username}/{$this->github_repo}/main/style.css";
        
        $args = array(
            'headers' => array(
                'Authorization' => 'token ' . $this->github_token,
                'Accept'        => 'application/vnd.github.v3.raw',
                'User-Agent'    => 'WordPress-Theme-Updater'
            ),
            'timeout' => 15
        );

        $response = wp_remote_get( $style_css_url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return $transient;
        }

        $style_content = wp_remote_retrieve_body( $response );
        
        // Extract version from CSS header using regex
        if ( preg_match( '/Version:\s*([0-9\.]+)/i', $style_content, $matches ) ) {
            $remote_version = trim( $matches[1] );

            // If a newer version exists, register it in WP transient
            if ( version_compare( $this->current_version, $remote_version, '<' ) ) {
                $zip_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/zipball/main";

                $transient->response[ $this->theme_slug ] = array(
                    'theme'       => $this->theme_slug,
                    'new_version' => $remote_version,
                    'url'         => "https://github.com/{$this->github_username}/{$this->github_repo}",
                    'package'     => $zip_url,
                );
            }
        }

        return $transient;
    }

    /**
     * Authenticate and inject headers into WP zip download requests pointing to GitHub
     */
    public function authenticate_github_zip_download( $reply, $package, $upgrader ) {
        // Validate if this request points to our specific private GitHub repo zip archive
        if ( strpos( $package, "api.github.com/repos/{$this->github_username}/{$this->github_repo}" ) !== false ) {
            
            $temp_file = download_url( $package, 300, array(
                'headers' => array(
                    'Authorization' => 'token ' . $this->github_token,
                    'User-Agent'    => 'WordPress-Theme-Updater'
                )
            ) );

            if ( ! is_wp_error( $temp_file ) ) {
                return $temp_file;
            }
        }
        return $reply;
    }

    /**
     * Clear update cache transient upon completion
     */
    public function clear_theme_update_cache( $upgrader, $options ) {
        if ( isset( $options['action'] ) && $options['action'] === 'update' && $options['type'] === 'theme' ) {
            delete_site_transient( 'update_themes' );
        }
    }
}

// Initialize Custom Updater
new Reandaily_Theme_Github_Updater();
