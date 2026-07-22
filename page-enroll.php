<?php
/**
 * Template Name: Course Enrollment Page
 *
 * Handles course enrollment with KHQR payment for ReanDaily.
 * URL pattern: /enroll/?course_id=123
 */
get_header();

// ── 1. Gather course data ──────────────────────────────────────────────────
$course_id    = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$course       = $course_id ? get_post( $course_id ) : null;
$is_valid     = $course && $course->post_type === 'stm-courses' && $course->post_status === 'publish';

// ── 2. If course not found ─────────────────────────────────────────────────
if ( ! $is_valid ) : ?>
<section style="padding: 120px 20px; text-align: center;">
    <span style="font-size: 60px;">😕</span>
    <h1 style="font-size: 28px; color: #0f172a; margin: 20px 0 10px;">មិនឃើញវគ្គសិក្សា</h1>
    <p style="color: #64748b; margin-bottom: 30px;">សូមត្រលប់ទៅជ្រើសរើសវគ្គសិក្សាម្ដងទៀត។</p>
    <a href="<?php echo esc_url( home_url('/courses/') ); ?>" style="background: linear-gradient(135deg,#007bff,#00f2fe); color:#fff; padding:14px 32px; border-radius:8px; text-decoration:none; font-weight:700; font-size:16px;">
        ← ត្រលប់ទៅ វគ្គសិក្សា
    </a>
</section>
<?php get_footer(); return; endif;

// ── 3. Course meta ─────────────────────────────────────────────────────────
$price_raw    = get_post_meta( $course_id, 'price', true );
$price_usd    = floatval( $price_raw );

$is_sale_active = false;
if ( class_exists( 'STM_LMS_Helpers' ) && method_exists( 'STM_LMS_Helpers', 'is_sale_price_active' ) ) {
    $is_sale_active = STM_LMS_Helpers::is_sale_price_active( $course_id );
} else {
    $sale_price = get_post_meta( $course_id, 'sale_price', true );
    $is_sale_active = ( $sale_price !== '' && $sale_price !== null && floatval( $sale_price ) > 0 );
}

if ( $is_sale_active ) {
    $sale_price_meta = get_post_meta( $course_id, 'sale_price', true );
    if ( $sale_price_meta !== '' && $sale_price_meta !== null ) {
        $price_usd = floatval( $sale_price_meta );
    }
}

$is_free      = ( $price_raw === '' || $price_raw === null || $price_usd <= 0 );
$currency_opt = get_post_meta( $course_id, '_khqr_currency', true ); // 'USD' or 'KHR'
$currency     = $currency_opt ? strtoupper( $currency_opt ) : 'USD';
$price_khr_rate = 4100; // approx exchange rate
$price_khr    = round( $price_usd * $price_khr_rate );

$thumb        = get_the_post_thumbnail_url( $course_id, 'medium' );
$course_title = get_the_title( $course_id );
$course_url   = get_permalink( $course_id );

// ABA KHQR / PayWay settings (configurable in Customizer)
$payway_link   = function_exists( 'reandaily_get_theme_mod' ) ? reandaily_get_theme_mod( 'reandaily_aba_payway_link', '' ) : get_theme_mod( 'reandaily_aba_payway_link', '' );   // e.g. https://link.payway.com.kh/ABAPAYy3451718p
$bakong_id     = function_exists( 'reandaily_get_bakong_id_fallback' ) ? reandaily_get_bakong_id_fallback() : '';
$merchant_name = function_exists( 'reandaily_get_theme_mod' ) ? reandaily_get_theme_mod( 'reandaily_aba_merchant_name', 'ReanDaily' ) : get_theme_mod( 'reandaily_aba_merchant_name', 'ReanDaily' );
$merchant_city = function_exists( 'reandaily_get_theme_mod' ) ? reandaily_get_theme_mod( 'reandaily_aba_merchant_city', 'Phnom Penh' ) : get_theme_mod( 'reandaily_aba_merchant_city', 'Phnom Penh' );
$use_payway    = ! empty( $payway_link ); // TRUE = use PayWay URL as QR; FALSE = fallback to KHQR

$manual_details = function_exists( 'reandaily_get_manual_bank_details_fallback' ) 
    ? reandaily_get_manual_bank_details_fallback() 
    : array( 'bank_name' => '', 'account_name' => '', 'account_no' => '' );

$manual_bank_name    = $manual_details['bank_name'];
$manual_account_name  = $manual_details['account_name'];
$manual_account_no    = $manual_details['account_no'];

// Unique bill number for this session
$bill_number  = 'RD-' . $course_id . '-' . time();

// Check if user is already enrolled via MasterStudy
$already_enrolled = false;
if ( is_user_logged_in() ) {
    $user_id = get_current_user_id();
    if ( function_exists( 'stm_lms_get_user_course' ) ) {
        $user_course = stm_lms_get_user_course( $user_id, $course_id, array( 'status' ) );
        $already_enrolled = ! empty( $user_course );
    }
}

// ── 4. Retrieve Server Public IP (for whitelisting diagnostics) ───────────
$server_ip = $_SERVER['SERVER_ADDR'] ?? 'Unknown';
$ip_response = wp_remote_get( 'https://api.ipify.org', array( 'timeout' => 2 ) );
if ( ! is_wp_error( $ip_response ) && wp_remote_retrieve_response_code( $ip_response ) === 200 ) {
    $server_ip = trim( wp_remote_retrieve_body( $ip_response ) );
}

// ── 5. Retrieve Custom/Site Logo for QR Code ────────────────────────────────
$qr_logo_url = function_exists( 'reandaily_get_theme_mod' ) ? reandaily_get_theme_mod( 'reandaily_qr_code_logo', '' ) : get_theme_mod( 'reandaily_qr_code_logo', '' );

if ( empty( $qr_logo_url ) ) {
    if ( has_custom_logo() ) {
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        $logo_image = wp_get_attachment_image_src( $custom_logo_id, 'full' );
        if ( $logo_image ) {
            $qr_logo_url = $logo_image[0];
        }
    }
}
if ( empty( $qr_logo_url ) ) {
    $customizer_logo_url = get_theme_mod( 'ms_lms_starter_logo' );
    if ( $customizer_logo_url ) {
        $qr_logo_url = $customizer_logo_url;
    }
}
if ( empty( $qr_logo_url ) ) {
    // Check if theme logo.png exists, otherwise fall back to Bakong logo SVG
    $theme_logo_path = get_stylesheet_directory() . '/logo.png';
    if ( file_exists( $theme_logo_path ) ) {
        $qr_logo_url = get_stylesheet_directory_uri() . '/logo.png';
    } else {
        $qr_logo_url = get_stylesheet_directory_uri() . '/bakong-logo.svg';
    }
}
?>

<!-- ── Enrollment Page ──────────────────────────────────────────────────── -->
<div class="enroll-page">

    <!-- Banner -->
    <section class="enroll-banner">
        <div class="container">
            <span class="banner-badge">🎓 ចុះឈ្មោះចូលរៀន</span>
            <h1><?php echo esc_html( $course_title ); ?></h1>
        </div>
    </section>

    <!-- Main Content -->
    <div class="enroll-container">

        <?php if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
        <!-- Admin Warning Banner to prevent testing confusion -->
        <div class="admin-test-warning" style="grid-column: 1 / -1; background: #fffbeb; border: 1px solid #fef3c7; border-radius: 16px; padding: 20px; margin-bottom: 30px; display: flex; gap: 16px; font-family: 'Kantumruy Pro', sans-serif !important; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.05); align-items: flex-start;">
            <div style="font-size: 28px; line-height: 1;">⚠️</div>
            <div style="font-size: 14px; color: #b45309; line-height: 1.7; width: 100%;">
                <strong style="font-size: 16px; display: block; margin-bottom: 6px; color: #92400e; font-weight: 800;">ចំណាំសម្រាប់ការសាកល្បង (Developer / Admin Test Notice)</strong>
                លោកអ្នកកំពុងសាកល្បងជាមួយគណនី <strong>Administrator</strong>។ គណនី Admin អាចចូលអានមេរៀនគ្រប់វគ្គសិក្សាទាំងអស់ដោយស្វ័យប្រវត្តិតាមលំនាំដើមរបស់ MasterStudy LMS ដោយមិនចាំបាច់បង់ប្រាក់ឡើយ។<br>
                <span style="font-weight: 700; color: #78350f;">ដើម្បីសាកល្បងលំហូររឹតបន្តឹង paywall ពិតប្រាកដរបស់សិស្ស៖</span><br>
                ១. សូមប្រើប្រាស់មុខងារ <strong>Incognito Window (Private Browsing)</strong> រួចបំពេញព័ត៌មានដើម្បីបង្កើតគណនីសិស្សថ្មី<br>
                ២. ឬចុះឈ្មោះគណនីសិស្សធម្មតា (Role: Subscriber) ដើម្បីសាកល្បង។
                <div class="admin-config-diagnostics" style="margin-top: 15px; padding: 15px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; color: #334155; font-size: 13px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                    <strong style="color: #0f172a; display: block; margin-bottom: 8px; font-size: 14px;">⚙️ Configuration Diagnostics (Admin Only):</strong>
                    <div style="margin-bottom: 4px;">• <b>Automated ABA PayWay Gateway:</b> <span style="color:#16a34a;font-weight:700;">ACTIVE (Sandbox Credentials Configured)</span></div>
                    <div style="margin-bottom: 4px;">• <b>Merchant ID:</b> <code>ec462060</code></div>
                    <div style="margin-bottom: 4px;">• <b>Bank Account:</b> <code>008 668 510 (MENG HANN AND JOHN CHANTHY)</code></div>
                    <div style="margin-bottom: 4px;">• <b>API Endpoint:</b> <code>https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase</code></div>
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px dashed #e2e8f0; color: #475569;">
                        🌐 <b>Server Public IP:</b> <code style="font-size: 14px; font-weight: 700; color: #0f172a; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"><?php echo esc_html( $server_ip ); ?></code>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Left: Course Info Card -->
        <div class="enroll-course-card">
            <div class="course-thumb">
                <?php if ( $thumb ) : ?>
                    <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $course_title ); ?>">
                <?php else : ?>
                    <div class="course-thumb-placeholder">🎓</div>
                <?php endif; ?>
                <?php if ( ! $is_free ) : ?>
                    <div class="price-badge">
                        <?php if ( $currency === 'KHR' ) : ?>
                            ៛<?php echo number_format( $price_khr ); ?>
                        <?php else : ?>
                            $<?php echo number_format( $price_usd, 2 ); ?>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="price-badge free-badge">FREE</div>
                <?php endif; ?>
            </div>
            <div class="course-info-body">
                <h2><?php echo esc_html( $course_title ); ?></h2>
                <div class="course-info-rows">
                    <div class="info-row">
                        <span class="info-icon">💰</span>
                        <span class="info-label">តម្លៃ</span>
                        <span class="info-val">
                            <?php if ( $is_free ) : ?>
                                <strong style="color:#10b981;">FREE</strong>
                            <?php elseif ( $currency === 'KHR' ) : ?>
                                <strong>៛<?php echo number_format( $price_khr ); ?></strong>
                            <?php else : ?>
                                <strong>$<?php echo number_format( $price_usd, 2 ); ?> USD</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-icon">💳</span>
                        <span class="info-label">ការទូទាត់</span>
                        <span class="info-val"><?php echo $is_free ? 'ឥតគិតថ្លៃ' : 'KHQR / Bakong'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-icon">✅</span>
                        <span class="info-label">ការចូលរៀន</span>
                        <span class="info-val">ភ្លាមៗ</span>
                    </div>
                </div>
                <a href="<?php echo esc_url( $course_url ); ?>" class="view-course-link">👁 មើលព័ត៌មានវគ្គ</a>
            </div>
        </div>

        <!-- Right: Enrollment Form + KHQR -->
        <div class="enroll-form-wrapper">

            <?php if ( $already_enrolled ) : ?>
            <!-- Already Enrolled -->
            <div class="enroll-success-box">
                <div class="success-icon">🎉</div>
                <h2>អ្នកបានចុះឈ្មោះរួចហើយ!</h2>
                <p>អ្នកបានចូលរៀន <strong><?php echo esc_html($course_title); ?></strong> ហើយ។</p>
                <a href="<?php echo esc_url( $course_url ); ?>" class="btn-enroll-submit">ចូលរៀនឥឡូវ →</a>
            </div>

            <?php else : ?>
            <!-- STEP 1: Student Info Form -->
            <div id="enroll-step-1" class="enroll-step active">
                <div class="step-header">
                    <div class="step-indicator">
                        <div class="step-dot active">1</div>
                        <div class="step-line <?php echo $is_free ? 'free' : ''; ?>"></div>
                        <div class="step-dot <?php echo $is_free ? 'hidden' : ''; ?>">2</div>
                        <?php if ( ! $is_free ) : ?>
                        <div class="step-line"></div>
                        <div class="step-dot">3</div>
                        <?php endif; ?>
                    </div>
                    <h2 class="step-title">ព័ត៌មានសិស្ស</h2>
                </div>

                <form id="enroll-form" novalidate>
                    <?php wp_nonce_field( 'reandaily_enroll_nonce', 'enroll_nonce' ); ?>
                    <input type="hidden" name="course_id" value="<?php echo esc_attr( $course_id ); ?>">
                    <input type="hidden" name="bill_number" value="<?php echo esc_attr( $bill_number ); ?>">
                    <input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>">
                    <input type="hidden" name="price" value="<?php echo esc_attr( $price_usd ); ?>">
                    <input type="hidden" name="is_free" value="<?php echo $is_free ? '1' : '0'; ?>">

                    <div class="form-group">
                        <label for="student_name">ឈ្មោះពេញ <span class="req">*</span></label>
                        <input type="text" id="student_name" name="student_name" 
                               placeholder="ឧ. សុខ ដារ៉ា"
                               value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->display_name) : ''; ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="student_email">អ៊ីមែល <span class="req">*</span></label>
                        <input type="email" id="student_email" name="student_email" 
                               placeholder="yourname@email.com"
                               value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="student_phone">លេខទូរស័ព្ទ <span class="req">*</span></label>
                        <input type="tel" id="student_phone" name="student_phone" 
                               placeholder="0XX XXX XXX"
                               required>
                    </div>

                    <?php if ( ! is_user_logged_in() ) : ?>
                    <div class="form-group">
                        <label for="student_password">ពាក្យសម្ងាត់ (សម្រាប់បង្កើតគណនី)</label>
                        <input type="password" id="student_password" name="student_password" 
                               placeholder="យ៉ាងហោចណាស់ 6 តួអក្សរ">
                        <small>ចាកចេញទទេប្រសិនបើអ្នកមានគណនីហើយ ឬ <a href="<?php echo wp_login_url( get_permalink() . '?course_id=' . $course_id ); ?>">ចូលគណនីនៅទីនេះ</a></small>
                    </div>
                    <?php endif; ?>

                    <div id="form-error" class="form-error hidden"></div>

                    <button type="submit" id="btn-next" class="btn-enroll-submit">
                        <?php echo $is_free ? 'ចុះឈ្មោះឥឡូវ 🎓' : 'បន្ត → បង់ប្រាក់'; ?>
                        <span class="btn-loader hidden">⏳</span>
                    </button>
                </form>
            </div>

            <?php if ( ! $is_free ) : ?>
            <!-- STEP 2: KHQR Payment -->
            <div id="enroll-step-2" class="enroll-step hidden">
                <div class="step-header">
                    <div class="step-indicator">
                        <div class="step-dot done">✓</div>
                        <div class="step-line done"></div>
                        <div class="step-dot active">2</div>
                        <div class="step-line"></div>
                        <div class="step-dot">3</div>
                    </div>
                    <h2 class="step-title">ទូទាត់ប្រាក់</h2>
                </div>

                <div class="khqr-payment-box">
                    <!-- Bank logos -->
                    <div class="bank-logos">
                        <span class="bank-logo-badge aba">ABA</span>
                        <span class="bank-logo-badge acleda">ACLEDA</span>
                        <span class="bank-logo-badge wing">WING</span>
                        <span class="bank-logo-badge bakong">BAKONG</span>
                        <span class="bank-more">+ ធនាគារទាំងអស់</span>
                    </div>

                    <!-- Amount display -->
                    <div class="payment-amount">
                        <div class="amount-label">ចំនួនទឹកប្រាក់ត្រូវបង់</div>
                        <div class="amount-value" id="display-amount">
                            <?php if ( $currency === 'KHR' ) : ?>
                                ៛<span id="pay-amount-num"><?php echo number_format( $price_khr ); ?></span>
                            <?php else : ?>
                                $<span id="pay-amount-num"><?php echo number_format( $price_usd, 2 ); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="amount-currency"><?php echo esc_html( $currency ); ?></div>
                    </div>

                    <!-- CSS Style for Tabs -->
                    <style>
                        .payment-tabs {
                            display: flex;
                            border: 1px solid #cbd5e1;
                            border-radius: 12px;
                            overflow: hidden;
                            margin-bottom: 25px;
                            background: #f1f5f9;
                        }
                        .payment-tab {
                            flex: 1;
                            padding: 14px 20px;
                            border: none;
                            font-size: 14px;
                            font-weight: 700;
                            color: #475569;
                            background: #f1f5f9;
                            cursor: pointer;
                            transition: all 0.25s ease;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 8px;
                            outline: none;
                            font-family: 'Kantumruy Pro', sans-serif !important;
                        }
                        .payment-tab:first-child {
                            border-right: 1px solid #cbd5e1;
                        }
                        .payment-tab.active {
                            background: #ffffff !important;
                            color: #005a9c !important;
                            font-weight: 800 !important;
                            box-shadow: inset 0 -2px 0 #005a9c;
                        }
                        .payment-tab:hover:not(.active) {
                            background: #e2e8f0;
                            color: #0f172a;
                        }
                        .payment-method-section {
                            display: none;
                        }
                    </style>

                    <!-- Toggle Tabs completely hidden to show only the active setup configured in admin -->

                    <!-- 1. AUTOMATED KHQR PAYMENT SECTION -->
                    <div id="section-auto-pay" class="payment-method-section" style="display: block;">
                        <!-- QR Code Strategy -->
                        <div class="qr-wrapper">
                            <div id="khqr-loading" class="qr-loading">
                                <div class="qr-spinner"></div>
                                <p>កំពុងបង្កើត QR Code...</p>
                            </div>
                            <div id="khqr-canvas-wrap" class="hidden" style="text-align: center; margin-top: 15px;">
                                <div class="qr-frame" style="display: inline-block; padding: 16px; background: #ffffff; border: 1.5px solid #e2e8f0; border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);">
                                    <div id="khqr-img-container" style="display: flex; justify-content: center; align-items: center; margin: 0 auto; width: 220px; height: 220px; border-radius: 8px; overflow: hidden; background: #ffffff; position: relative;"></div>
                                </div>
                                <button type="button" id="btn-download-qr" class="btn-download" style="display: flex; align-items: center; justify-content: center; gap: 8px; margin: 12px auto 0 auto; background: #f1f5f9; border: none; padding: 8px 16px; border-radius: 8px; color: #475569; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s;">
                                    📥 ទាញយក QR Code (Download)
                                </button>
                            </div>
                        </div>
                        <!-- Mobile Banking App Button -->
                        <div class="payment-app-buttons" style="display: flex; flex-direction: column; gap: 12px; max-width: 340px; margin: 15px auto 20px auto; font-family: 'Kantumruy Pro', sans-serif !important;">
                            <a href="bakong://pay?qr=" 
                               id="btn-bakong-mobile"
                               class="btn-bakong-tap"
                               style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 14px 20px; font-size: 14.5px; font-weight: 700; color: #ffffff; background: linear-gradient(135deg, #005a9c 0%, #003b68 100%); border-radius: 10px; text-decoration: none; box-shadow: 0 4px 15px rgba(0, 90, 156, 0.2); transition: all 0.2s;"
                               target="_blank" rel="noopener">
                                <span style="font-size: 18px;">💳</span>
                                <span>បើកកម្មវិធី ABA Mobile / Scan KHQR ទូទាត់</span>
                            </a>
                        </div>

                        <!-- Instructions for Auto Payment -->
                        <div class="payment-steps" style="margin-top: 20px;">
                            <div class="pay-step">
                                <div class="pay-step-num">1</div>
                                <div class="pay-step-text">បើកកម្មវិធីធនាគារណាមួយ (Bakong, ABA, Acleda...) រួចស្កេន <b>QR Code</b> ខាងលើ។</div>
                            </div>
                            <div class="pay-step">
                                <div class="pay-step-num">2</div>
                                <div class="pay-step-text">ប្រព័ន្ធនឹងផ្ទៀងផ្ទាត់ការទូទាត់ដោយស្វ័យប្រវត្តិភ្លាមៗ បន្ទាប់ពីលោកអ្នកផ្ទេរប្រាក់រួចរាល់។</div>
                            </div>
                        </div>

                        <!-- Timer -->
                        <div class="qr-timer" style="margin-top: 15px;">
                            <span>⏱</span>
                            <span id="qr-countdown">QR Code នឹងផុតកំណត់ក្នុង <strong id="timer-val">03:00</strong></span>
                        </div>

                        <!-- Live Verification Status -->
                        <div id="polling-status-box" style="margin: 20px auto 10px auto; max-width: 340px; padding: 12px 18px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; display: flex; align-items: center; justify-content: center; gap: 10px; font-family: 'Kantumruy Pro', sans-serif !important;">
                            <div class="status-spinner" style="width: 16px; height: 16px; border: 2.5px solid #16a34a; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                            <span style="font-size: 13.5px; font-weight: 700; color: #15803d;">ប្រព័ន្ធកំពុងរង់ចាំការទូទាត់ និងផ្ទៀងផ្ទាត់ស្វ័យប្រវត្តិ...</span>
                        </div>
                        
                        <!-- Diagnostic Debug Box (Visible to all for troubleshooting payment verification issues) -->
                        <div id="bakong-api-debug-info" style="display:none; margin: 15px auto; max-width: 340px; padding: 12px 18px; background: #fee2e2; border: 1px solid #fca5a5; border-radius: 10px; color: #991b1b; font-size: 13px; font-family: 'Kantumruy Pro', sans-serif !important; white-space: pre-wrap; line-height: 1.5; text-align: left;">
                            <strong style="color: #b91c1c; display: block; margin-bottom: 4px; font-size: 13.5px;">🔴 Payment Gateway Diagnostic Info:</strong>
                            <span id="bakong-api-debug-message"></span>
                        </div>
                        
                        <style>
                            @keyframes spin { 100% { transform: rotate(360deg); } }
                        </style>
                    </div>

                    <!-- 2. MANUAL PAYMENT SECTION -->
                    <div id="section-manual-pay" class="payment-method-section" style="display: none; text-align: left;">
                        <?php 
                        // Check if a custom Bakong ID is set in the Customizer settings
                        $configured_bakong_id = function_exists( 'reandaily_get_bakong_id_fallback' ) ? reandaily_get_bakong_id_fallback() : '';

                        $has_bakong_id = ! empty( $configured_bakong_id );
                        $manual_qr_payload = '';
                        $aba_pay_deep_link = '';

                        if ( $has_bakong_id ) {
                            // If Bakong ID is set, generate a universal KHQR code for all banks
                            $manual_qr_payload = reandaily_generate_khqr_string( array(
                                'bakong_id'     => $configured_bakong_id,
                                'merchant_name' => $merchant_name,
                                'merchant_city' => $merchant_city,
                                'amount'        => $currency === 'KHR' ? $price_khr : $price_usd,
                                'currency'      => $currency,
                                'bill_number'   => $bill_number,
                            ) );
                            $aba_pay_deep_link = 'bakong://pay?qr=' . rawurlencode( $manual_qr_payload );
                        } else {
                            // FALLBACK: Use ABA PayWay base URL if set
                            $payway_base = ! empty( $payway_link ) ? $payway_link : '';
                            if ( ! empty( $payway_base ) ) {
                                $course_slug = sanitize_title( $course_title );
                                $checkout_desc = 'ReanDaily-' . substr($course_slug, 0, 15) . '-' . $bill_number;
                                $is_aba_shortlink = ( strpos( $payway_base, 'pay.ababank.com' ) !== false || strpos( $payway_base, 'ababank.com' ) !== false );
                                if ( $is_aba_shortlink ) {
                                    $manual_qr_payload = $payway_base;
                                    $aba_pay_deep_link = $payway_base;
                                } else {
                                    $manual_qr_payload = add_query_arg( array(
                                        'amount' => $currency === 'KHR' ? intval($price_khr) : floatval($price_usd),
                                        'desc'   => $checkout_desc
                                    ), $payway_base );
                                    $aba_pay_deep_link = $manual_qr_payload;
                                }
                            }
                        }
                        ?>

                        <?php if ( ! empty( $manual_qr_payload ) ) : ?>
                            <div style="text-align: center; margin: 15px 0;">
                                <p style="font-size: 14px; font-weight: 700; color: #334155; margin-bottom: 10px;">
                                    <?php if ( $has_bakong_id ) : ?>
                                        ស្កេន QR ដើម្បីទូទាត់ប្រាក់ (ស្កេនបានគ្រប់ធនាគារ)៖
                                    <?php else : ?>
                                        ស្កេន QR ដើម្បីទូទាត់ប្រាក់ (សម្រាប់កម្មវិធី ABA Mobile)៖
                                    <?php endif; ?>
                                </p>
                                
                                <div class="qr-frame-wrapper" style="display: inline-block; padding: 20px; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 16px; box-shadow: 0 10px 25px rgba(0, 90, 156, 0.08);">
                                    <?php if ( wp_is_mobile() && ! empty( $aba_pay_deep_link ) ) : ?>
                                        <a href="<?php echo esc_url( $aba_pay_deep_link ); ?>" target="_blank" rel="noopener" style="display: block; cursor: pointer; text-decoration: none;" title="ចុចទីនេះដើម្បីទូទាត់ផ្ទាល់លើកម្មវិធី ABA Mobile (Click to pay directly via ABA Mobile)">
                                    <?php endif; ?>
                                        <div id="manual-qr-container" style="display: flex; justify-content: center; align-items: center; margin: 0 auto; width: 240px; height: 240px; border-radius: 8px; overflow: hidden; background: #ffffff; position: relative;"></div>
                                    <?php if ( wp_is_mobile() && ! empty( $aba_pay_deep_link ) ) : ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <p style="font-size: 12.5px; color: #64748b; margin-top: 10px;">
                                    <?php if ( $has_bakong_id ) : ?>
                                        (ស្កេន QR ខាងលើជាមួយកម្មវិធី <b>ធនាគារណាមួយ (ABA, Bakong, Wing, Acleda...)</b> ដើម្បីទូទាត់ប្រាក់ ឬចុចលើរូបដើម្បីទូទាត់ផ្ទាល់តាម Bakong)
                                    <?php else : ?>
                                        (ស្កេន QR ខាងលើជាមួយកម្មវិធី <b>ABA Mobile</b> ដើម្បីទូទាត់ប្រាក់ ឬចុចលើរូបដើម្បីទូទាត់ផ្ទាល់)
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <!-- Dynamic Payment Display Card -->
                        <?php if ( ! empty( $manual_bank_name ) || ! empty( $manual_account_name ) || ! empty( $manual_account_no ) ) : ?>
                            <div class="bank-details-card" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 18px; margin: 15px 0; text-align: left; font-family: 'Kantumruy Pro', sans-serif !important;">
                                <h4 style="margin: 0 0 10px 0; font-size: 15px; color: #0f172a; font-weight: 800; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">ℹ️ ព័ត៌មានគណនីបង់ប្រាក់</h4>
                                <div style="font-size: 14px; color: #334155; line-height: 1.8;">
                                    <?php if ( ! empty( $manual_bank_name ) ) : ?>
                                        <div>🏦 <b>ធនាគារ៖</b> <?php echo esc_html( $manual_bank_name ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $manual_account_name ) ) : ?>
                                        <div>👤 <b>ឈ្មោះគណនី៖</b> <?php echo esc_html( $manual_account_name ); ?></div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $manual_account_no ) ) : ?>
                                        <div>លេខគណនី៖ <strong style="font-size: 16px; color: #005a9c; letter-spacing: 0.05em;"><?php echo esc_html( $manual_account_no ); ?></strong></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else : ?>
                            <div style="padding: 20px; background: #fee2e2; border: 1.5px solid #fca5a5; border-radius: 12px; color: #991b1b; display: block; font-size: 14px; font-weight: 700; text-align: center; margin: 15px 0; font-family: 'Kantumruy Pro', sans-serif !important;">
                                ⚠️ មិនទាន់មានព័ត៌មានគណនីធនាគារសម្រាប់ការបង់ប្រាក់នៅឡើយទេ។ សូមទាក់ទងមកកាន់យើងខ្ញុំដើម្បីទទួលបានព័ត៌មានលម្អិត។
                            </div>
                        <?php endif; ?>

                        <!-- Instructions for Manual Payment -->
                        <div class="payment-steps">
                            <div class="pay-step">
                                <div class="pay-step-num">1</div>
                                <div class="pay-step-text">សូមធ្វើការផ្ទេរប្រាក់ទៅកាន់គណនីធនាគារខាងលើ ឬស្កេន QR Code ខាងលើ។</div>
                            </div>
                            <div class="pay-step">
                                <div class="pay-step-num">2</div>
                                <div class="pay-step-text">បន្ទាប់ពីផ្ទេរប្រាក់ជោគជ័យ សូមបញ្ចូល <b>លេខយោងប្រតិបត្តិការ (Transaction Ref / TXN)</b> និង/ឬ បង្ហោះរូបភាពបង្កាន់ដៃ រួចចុចប៊ូតុង <b>"ផ្ញើបង្កាន់ដៃបញ្ជាក់"</b> ខាងក្រោម។</div>
                            </div>
                        </div>

                        <!-- Manual Payment Verification Fields -->
                        <div id="manual-payment-inputs-container" style="margin-top: 25px; border-top: 1px dashed #cbd5e1; padding-top: 20px; text-align: left; font-family: 'Kantumruy Pro', sans-serif !important;">
                            <h4 style="margin: 0 0 15px 0; font-size: 15px; color: #0f172a; font-weight: 800;">📝 បញ្ជាក់ការផ្ទេរប្រាក់របស់អ្នក (Payment Verification)</h4>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="transaction_ref" style="display: block; font-weight: 700; color: #334155; margin-bottom: 6px; font-size: 13.5px;">លេខយោងប្រតិបត្តិការ (Transaction Ref / TXN) <span style="color:#dc2626;">*</span></label>
                                <input type="text" id="transaction_ref" placeholder="ឧ. 123456 (លេខយោង ឬ លេខកូដបញ្ជាក់)" style="width: 100%; padding: 12px; border: 1.5px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; font-family: 'Kantumruy Pro', sans-serif !important;" required>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 10px;">
                                <label for="receipt_file" style="display: block; font-weight: 700; color: #334155; margin-bottom: 6px; font-size: 13.5px;">រូបភាពបង្កាន់ដៃបង់ប្រាក់ (Receipt Screenshot) (ស្រចិត្ត)</label>
                                <input type="file" id="receipt_file" accept="image/*" style="width: 100%; font-size: 13px; font-family: 'Kantumruy Pro', sans-serif !important;">
                            </div>
                        </div>
                    </div>

                    <div id="payment-error" class="form-error hidden"></div>
                </div>

                <button id="btn-confirm-payment" class="btn-enroll-submit" style="margin-top: 20px;">
                    🔄 ពិនិត្យការទូទាត់ស្វ័យប្រវត្តិ
                    <span class="btn-loader hidden">⏳</span>
                </button>
                <button id="btn-back-step1" class="btn-back">← ត្រលប់ក្រោយ</button>
            </div>
            <?php endif; ?>

            <!-- STEP 3: Success -->
            <div id="enroll-step-3" class="enroll-step hidden">
                <div class="enroll-success-box">
                    <div class="success-anim">
                        <div class="success-circle">
                            <svg viewBox="0 0 52 52" class="checkmark-svg">
                                <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                                <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                            </svg>
                        </div>
                    </div>
                    <h2 id="success-title">ចុះឈ្មោះបានជោគជ័យ! 🎉</h2>
                    <p id="success-message">អ្នកបានចុះឈ្មោះ <strong><?php echo esc_html( $course_title ); ?></strong> ដោយជោគជ័យ។</p>
                    <div id="success-actions">
                        <a href="<?php echo esc_url( $course_url ); ?>" class="btn-enroll-submit">ចូលរៀនឥឡូវ →</a>
                        <a href="<?php echo esc_url( home_url('/courses/') ); ?>" class="btn-back" style="margin-top:10px; display:block; text-align:center;">← ត្រលប់ទៅ វគ្គសិក្សា</a>
                    </div>
                </div>
            </div>

            <?php endif; // end already_enrolled check ?>
        </div><!-- .enroll-form-wrapper -->
    </div><!-- .enroll-container -->
</div><!-- .enroll-page -->
<?php 
// Fetch existing user details if logged in to auto-bypass Step 1 form
$user_name = '';
$user_email = '';
$user_phone = '';

if ( is_user_logged_in() ) {
    $current_user = wp_get_current_user();
    $user_name  = $current_user->display_name;
    $user_email = $current_user->user_email;
    
    // Try to get phone from latest enrollment
    global $wpdb;
    $table = $wpdb->prefix . 'reandaily_enrollments';
    $user_phone = $wpdb->get_var( $wpdb->prepare(
        "SELECT student_phone FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
        $current_user->ID
    ) );
    
    // Fallbacks for phone
    if ( empty( $user_phone ) ) {
        $user_phone = get_user_meta( $current_user->ID, 'billing_phone', true );
    }
    if ( empty( $user_phone ) ) {
        $user_phone = get_user_meta( $current_user->ID, 'phone', true );
    }
    if ( empty( $user_phone ) ) {
        $user_phone = '000000000'; // Default fallback
    }
}
?>
<?php if ( ! $is_free && ! $already_enrolled ) : ?>
<script>
(function() {
    // ── Config (passed from PHP) ─────────────────────────────────────────
/**
 * QRCode.js v1.0.0
 * David Shim / MIT License
 */
var QRCode;!function(){function a(a){this.mode=c.MODE_8BIT_BYTE,this.data=a,this.parsedData=[];for(var b=[],d=0,e=this.data.length;e>d;d++){var f=this.data.charCodeAt(d);f>65536?(b[0]=240|(1835008&f)>>>18,b[1]=128|(258048&f)>>>12,b[2]=128|(4032&f)>>>6,b[3]=128|63&f):f>2048?(b[0]=224|(61440&f)>>>12,b[1]=128|(4032&f)>>>6,b[2]=128|63&f):f>128?(b[0]=192|(1984&f)>>>6,b[1]=128|63&f):b[0]=f,this.parsedData=this.parsedData.concat(b)}this.parsedData.length!=this.data.length&&(this.parsedData.unshift(191),this.parsedData.unshift(187),this.parsedData.unshift(239))}function b(a,b){this.typeNumber=a,this.errorCorrectLevel=b,this.modules=null,this.moduleCount=0,this.dataCache=null,this.dataList=[]}function i(a,b){if(void 0==a.length)throw new Error(a.length+"/"+b);for(var c=0;c<a.length&&0==a[c];)c++;this.num=new Array(a.length-c+b);for(var d=0;d<a.length-c;d++)this.num[d]=a[d+c]}function j(a,b){this.totalCount=a,this.dataCount=b}function k(){this.buffer=[],this.length=0}function m(){return"undefined"!=typeof CanvasRenderingContext2D}function n(){var a=!1,b=navigator.userAgent;return/android/i.test(b)&&(a=!0,aMat=b.toString().match(/android ([0-9]\.[0-9])/i),aMat&&aMat[1]&&(a=parseFloat(aMat[1]))),a}function r(a,b){for(var c=1,e=s(a),f=0,g=l.length;g>=f;f++){var h=0;switch(b){case d.L:h=l[f][0];break;case d.M:h=l[f][1];break;case d.Q:h=l[f][2];break;case d.H:h=l[f][3]}if(h>=e)break;c++}if(c>l.length)throw new Error("Too long data");return c}function s(a){var b=encodeURI(a).toString().replace(/\%[0-9a-fA-F]{2}/g,"a");return b.length+(b.length!=a?3:0)}a.prototype={getLength:function(){return this.parsedData.length},write:function(a){for(var b=0,c=this.parsedData.length;c>b;b++)a.put(this.parsedData[b],8)}},b.prototype={addData:function(b){var c=new a(b);this.dataList.push(c),this.dataCache=null},isDark:function(a,b){if(0>a||this.moduleCount<=a||0>b||this.moduleCount<=b)throw new Error(a+","+b);return this.modules[a][b]},getModuleCount:function(){return this.moduleCount},make:function(){this.makeImpl(!1,this.getBestMaskPattern())},makeImpl:function(a,c){this.moduleCount=4*this.typeNumber+17,this.modules=new Array(this.moduleCount);for(var d=0;d<this.moduleCount;d++){this.modules[d]=new Array(this.moduleCount);for(var e=0;e<this.moduleCount;e++)this.modules[d][e]=null}this.setupPositionProbePattern(0,0),this.setupPositionProbePattern(this.moduleCount-7,0),this.setupPositionProbePattern(0,this.moduleCount-7),this.setupPositionAdjustPattern(),this.setupTimingPattern(),this.setupTypeInfo(a,c),this.typeNumber>=7&&this.setupTypeNumber(a),null==this.dataCache&&(this.dataCache=b.createData(this.typeNumber,this.errorCorrectLevel,this.dataList)),this.mapData(this.dataCache,c)},setupPositionProbePattern:function(a,b){for(var c=-1;7>=c;c++)if(!(-1>=a+c||this.moduleCount<=a+c))for(var d=-1;7>=d;d++)-1>=b+d||this.moduleCount<=b+d||(this.modules[a+c][b+d]=c>=0&&6>=c&&(0==d||6==d)||d>=0&&6>=d&&(0==c||6==c)||c>=2&&4>=c&&d>=2&&4>=d?!0:!1)},getBestMaskPattern:function(){for(var a=0,b=0,c=0;8>c;c++){this.makeImpl(!0,c);var d=f.getLostPoint(this);(0==c||a>d)&&(a=d,b=c)}return b},createMovieClip:function(a,b,c){var d=a.createEmptyMovieClip(b,c),e=1;this.make();for(var f=0;f<this.modules.length;f++)for(var g=f*e,h=0;h<this.modules[f].length;h++){var i=h*e,j=this.modules[f][h];j&&(d.beginFill(0,100),d.moveTo(i,g),d.lineTo(i+e,g),d.lineTo(i+e,g+e),d.lineTo(i,g+e),d.endFill())}return d},setupTimingPattern:function(){for(var a=8;a<this.moduleCount-8;a++)null==this.modules[a][6]&&(this.modules[a][6]=0==a%2);for(var b=8;b<this.moduleCount-8;b++)null==this.modules[6][b]&&(this.modules[6][b]=0==b%2)},setupPositionAdjustPattern:function(){for(var a=f.getPatternPosition(this.typeNumber),b=0;b<a.length;b++)for(var c=0;c<a.length;c++){var d=a[b],e=a[c];if(null==this.modules[d][e])for(var g=-2;2>=g;g++)for(var h=-2;2>=h;h++)this.modules[d+g][e+h]=-2==g||2==g||-2==h||2==h||0==g&&0==h?!0:!1}},setupTypeNumber:function(a){for(var b=f.getBCHTypeNumber(this.typeNumber),c=0;18>c;c++){var d=!a&&1==(1&b>>c);this.modules[Math.floor(c/3)][c%3+this.moduleCount-8-3]=d}for(var c=0;18>c;c++){var d=!a&&1==(1&b>>c);this.modules[c%3+this.moduleCount-8-3][Math.floor(c/3)]=d}},setupTypeInfo:function(a,b){for(var c=this.errorCorrectLevel<<3|b,d=f.getBCHTypeInfo(c),e=0;15>e;e++){var g=!a&&1==(1&d>>e);6>e?this.modules[e][8]=g:8>e?this.modules[e+1][8]=g:this.modules[this.moduleCount-15+e][8]=g}for(var e=0;15>e;e++){var g=!a&&1==(1&d>>e);8>e?this.modules[8][this.moduleCount-e-1]=g:9>e?this.modules[8][15-e-1+1]=g:this.modules[8][15-e-1]=g}this.modules[this.moduleCount-8][8]=!a},mapData:function(a,b){for(var c=-1,d=this.moduleCount-1,e=7,g=0,h=this.moduleCount-1;h>0;h-=2)for(6==h&&h--;;){for(var i=0;2>i;i++)if(null==this.modules[d][h-i]){var j=!1;g<a.length&&(j=1==(1&a[g]>>>e));var k=f.getMask(b,d,h-i);k&&(j=!j),this.modules[d][h-i]=j,e--,-1==e&&(g++,e=7)}if(d+=c,0>d||this.moduleCount<=d){d-=c,c=-c;break}}}},b.PAD0=236,b.PAD1=17,b.createData=function(a,c,d){for(var e=j.getRSBlocks(a,c),g=new k,h=0;h<d.length;h++){var i=d[h];g.put(i.mode,4),g.put(i.getLength(),f.getLengthInBits(i.mode,a)),i.write(g)}for(var l=0,h=0;h<e.length;h++)l+=e[h].dataCount;if(g.getLengthInBits()>8*l)throw new Error("code length overflow. ("+g.getLengthInBits()+">"+8*l+")");for(g.getLengthInBits()+4<=8*l&&g.put(0,4);0!=g.getLengthInBits()%8;)g.putBit(!1);for(;;){if(g.getLengthInBits()>=8*l)break;if(g.put(b.PAD0,8),g.getLengthInBits()>=8*l)break;g.put(b.PAD1,8)}return b.createBytes(g,e)},b.createBytes=function(a,b){for(var c=0,d=0,e=0,g=new Array(b.length),h=new Array(b.length),j=0;j<b.length;j++){var k=b[j].dataCount,l=b[j].totalCount-k;d=Math.max(d,k),e=Math.max(e,l),g[j]=new Array(k);for(var m=0;m<g[j].length;m++)g[j][m]=255&a.buffer[m+c];c+=k;var n=f.getErrorCorrectPolynomial(l),o=new i(g[j],n.getLength()-1),p=o.mod(n);h[j]=new Array(n.getLength()-1);for(var m=0;m<h[j].length;m++){var q=m+p.getLength()-h[j].length;h[j][m]=q>=0?p.get(q):0}}for(var r=0,m=0;m<b.length;m++)r+=b[m].totalCount;for(var s=new Array(r),t=0,m=0;d>m;m++)for(var j=0;j<b.length;j++)m<g[j].length&&(s[t++]=g[j][m]);for(var m=0;e>m;m++)for(var j=0;j<b.length;j++)m<h[j].length&&(s[t++]=h[j][m]);return s};for(var c={MODE_NUMBER:1,MODE_ALPHA_NUM:2,MODE_8BIT_BYTE:4,MODE_KANJI:8},d={L:1,M:0,Q:3,H:2},e={PATTERN000:0,PATTERN001:1,PATTERN010:2,PATTERN011:3,PATTERN100:4,PATTERN101:5,PATTERN110:6,PATTERN111:7},f={PATTERN_POSITION_TABLE:[[],[6,18],[6,22],[6,26],[6,30],[6,34],[6,22,38],[6,24,42],[6,26,46],[6,28,50],[6,30,54],[6,32,58],[6,34,62],[6,26,46,66],[6,26,48,70],[6,26,50,74],[6,30,54,78],[6,30,56,82],[6,30,58,86],[6,34,62,90],[6,28,50,72,94],[6,26,50,74,98],[6,30,54,78,102],[6,28,54,80,106],[6,32,58,84,110],[6,30,58,86,114],[6,34,62,90,118],[6,26,50,74,98,122],[6,30,54,78,102,126],[6,26,52,78,104,130],[6,30,56,82,108,134],[6,34,60,86,112,138],[6,30,58,86,114,142],[6,34,62,90,118,146],[6,30,54,78,102,126,150],[6,24,50,76,102,128,154],[6,28,54,80,106,132,158],[6,32,58,84,110,136,162],[6,26,54,82,110,138,166],[6,30,58,86,114,142,170]],G15:1335,G18:7973,G15_MASK:21522,getBCHTypeInfo:function(a){for(var b=a<<10;f.getBCHDigit(b)-f.getBCHDigit(f.G15)>=0;)b^=f.G15<<f.getBCHDigit(b)-f.getBCHDigit(f.G15);return(a<<10|b)^f.G15_MASK},getBCHTypeNumber:function(a){for(var b=a<<12;f.getBCHDigit(b)-f.getBCHDigit(f.G18)>=0;)b^=f.G18<<f.getBCHDigit(b)-f.getBCHDigit(f.G18);return a<<12|b},getBCHDigit:function(a){for(var b=0;0!=a;)b++,a>>>=1;return b},getPatternPosition:function(a){return f.PATTERN_POSITION_TABLE[a-1]},getMask:function(a,b,c){switch(a){case e.PATTERN000:return 0==(b+c)%2;case e.PATTERN001:return 0==b%2;case e.PATTERN010:return 0==c%3;case e.PATTERN011:return 0==(b+c)%3;case e.PATTERN100:return 0==(Math.floor(b/2)+Math.floor(c/3))%2;case e.PATTERN101:return 0==b*c%2+b*c%3;case e.PATTERN110:return 0==(b*c%2+b*c%3)%2;case e.PATTERN111:return 0==(b*c%3+(b+c)%2)%2;default:throw new Error("bad maskPattern:"+a)}},getErrorCorrectPolynomial:function(a){for(var b=new i([1],0),c=0;a>c;c++)b=b.multiply(new i([1,g.gexp(c)],0));return b},getLengthInBits:function(a,b){if(b>=1&&10>b)switch(a){case c.MODE_NUMBER:return 10;case c.MODE_ALPHA_NUM:return 9;case c.MODE_8BIT_BYTE:return 8;case c.MODE_KANJI:return 8;default:throw new Error("mode:"+a)}else if(27>b)switch(a){case c.MODE_NUMBER:return 12;case c.MODE_ALPHA_NUM:return 11;case c.MODE_8BIT_BYTE:return 16;case c.MODE_KANJI:return 10;default:throw new Error("mode:"+a)}else{if(!(41>b))throw new Error("type:"+b);switch(a){case c.MODE_NUMBER:return 14;case c.MODE_ALPHA_NUM:return 13;case c.MODE_8BIT_BYTE:return 16;case c.MODE_KANJI:return 12;default:throw new Error("mode:"+a)}}},getLostPoint:function(a){for(var b=a.getModuleCount(),c=0,d=0;b>d;d++)for(var e=0;b>e;e++){for(var f=0,g=a.isDark(d,e),h=-1;1>=h;h++)if(!(0>d+h||d+h>=b))for(var i=-1;1>=i;i++)0>e+i||e+i>=b||(0!=h||0!=i)&&g==a.isDark(d+h,e+i)&&f++;f>5&&(c+=3+f-5)}for(var d=0;b-1>d;d++)for(var e=0;b-1>e;e++){var j=0;a.isDark(d,e)&&j++,a.isDark(d+1,e)&&j++,a.isDark(d,e+1)&&j++,a.isDark(d+1,e+1)&&j++,(0==j||4==j)&&(c+=3)}for(var d=0;b>d;d++)for(var e=0;b-6>e;e++)a.isDark(d,e)&&!a.isDark(d,e+1)&&a.isDark(d,e+2)&&a.isDark(d,e+3)&&a.isDark(d,e+4)&&!a.isDark(d,e+5)&&a.isDark(d,e+6)&&(c+=40);for(var e=0;b>e;e++)for(var d=0;b-6>d;d++)a.isDark(d,e)&&!a.isDark(d+1,e)&&a.isDark(d+2,e)&&a.isDark(d+3,e)&&a.isDark(d+4,e)&&!a.isDark(d+5,e)&&a.isDark(d+6,e)&&(c+=40);for(var k=0,e=0;b>e;e++)for(var d=0;b>d;d++)a.isDark(d,e)&&k++;var l=Math.abs(100*k/b/b-50)/5;return c+=10*l}},g={glog:function(a){if(1>a)throw new Error("glog("+a+")");return g.LOG_TABLE[a]},gexp:function(a){for(;0>a;)a+=255;for(;a>=256;)a-=255;return g.EXP_TABLE[a]},EXP_TABLE:new Array(256),LOG_TABLE:new Array(256)},h=0;8>h;h++)g.EXP_TABLE[h]=1<<h;for(var h=8;256>h;h++)g.EXP_TABLE[h]=g.EXP_TABLE[h-4]^g.EXP_TABLE[h-5]^g.EXP_TABLE[h-6]^g.EXP_TABLE[h-8];for(var h=0;255>h;h++)g.LOG_TABLE[g.EXP_TABLE[h]]=h;i.prototype={get:function(a){return this.num[a]},getLength:function(){return this.num.length},multiply:function(a){for(var b=new Array(this.getLength()+a.getLength()-1),c=0;c<this.getLength();c++)for(var d=0;d<a.getLength();d++)b[c+d]^=g.gexp(g.glog(this.get(c))+g.glog(a.get(d)));return new i(b,0)},mod:function(a){if(this.getLength()-a.getLength()<0)return this;for(var b=g.glog(this.get(0))-g.glog(a.get(0)),c=new Array(this.getLength()),d=0;d<this.getLength();d++)c[d]=this.get(d);for(var d=0;d<a.getLength();d++)c[d]^=g.gexp(g.glog(a.get(d))+b);return new i(c,0).mod(a)}},j.RS_BLOCK_TABLE=[[1,26,19],[1,26,16],[1,26,13],[1,26,9],[1,44,34],[1,44,28],[1,44,22],[1,44,16],[1,70,55],[1,70,44],[2,35,17],[2,35,13],[1,100,80],[2,50,32],[2,50,24],[4,25,9],[1,134,108],[2,67,43],[2,33,15,2,34,16],[2,33,11,2,34,12],[2,86,68],[4,43,27],[4,43,19],[4,43,15],[2,98,78],[4,49,31],[2,32,14,4,33,15],[4,39,13,1,40,14],[2,121,97],[2,60,38,2,61,39],[4,40,18,2,41,19],[4,40,14,2,41,15],[2,146,116],[3,58,36,2,59,37],[4,36,16,4,37,17],[4,36,12,4,37,13],[2,86,68,2,87,69],[4,69,43,1,70,44],[6,43,19,2,44,20],[6,43,15,2,44,16],[4,101,81],[1,80,50,4,81,51],[4,50,22,4,51,23],[3,36,12,8,37,13],[2,116,92,2,117,93],[6,58,36,2,59,37],[4,46,20,6,47,21],[7,42,14,4,43,15],[4,133,107],[8,59,37,1,60,38],[8,44,20,4,45,21],[12,33,11,4,34,12],[3,145,115,1,146,116],[4,64,40,5,65,41],[11,36,16,5,37,17],[11,36,12,5,37,13],[5,109,87,1,110,88],[5,65,41,5,66,42],[5,54,24,7,55,25],[11,36,12],[5,122,98,1,123,99],[7,73,45,3,74,46],[15,43,19,2,44,20],[3,45,15,13,46,16],[1,135,107,5,136,108],[10,74,46,1,75,47],[1,50,22,15,51,23],[2,42,14,17,43,15],[5,150,120,1,151,121],[9,69,43,4,70,44],[17,50,22,1,51,23],[2,42,14,19,43,15],[3,141,113,4,142,114],[3,70,44,11,71,45],[17,47,21,4,48,22],[9,39,13,16,40,14],[3,135,107,5,136,108],[3,67,41,13,68,42],[15,54,24,5,55,25],[15,43,15,10,44,16],[4,144,116,4,145,117],[17,68,42],[17,50,22,6,51,23],[19,46,16,6,47,17],[2,139,111,7,140,112],[17,74,46],[7,54,24,16,55,25],[34,37,13],[4,151,121,5,152,122],[4,75,47,14,76,48],[11,54,24,14,55,25],[16,45,15,14,46,16],[6,147,117,4,148,118],[6,73,45,14,74,46],[11,54,24,16,55,25],[30,46,16,2,47,17],[8,132,106,4,133,107],[8,75,47,13,76,48],[7,54,24,22,55,25],[22,45,15,13,46,16],[10,142,114,2,143,115],[19,74,46,4,75,47],[28,50,22,6,51,23],[33,46,16,4,47,17],[8,152,122,4,153,123],[22,73,45,3,74,46],[8,53,23,26,54,24],[12,45,15,28,46,16],[3,147,117,10,148,118],[3,73,45,23,74,46],[4,54,24,31,55,25],[11,45,15,31,46,16],[7,146,116,7,147,117],[21,73,45,7,74,46],[1,53,23,37,54,24],[19,45,15,26,46,16],[5,145,115,10,146,116],[19,75,47,10,76,48],[15,54,24,25,55,25],[23,45,15,25,46,16],[13,145,115,3,146,116],[2,74,46,29,75,47],[42,54,24,1,55,25],[23,45,15,28,46,16],[17,145,115],[10,74,46,23,75,47],[10,54,24,35,55,25],[19,45,15,35,46,16],[17,145,115,1,146,116],[14,74,46,21,75,47],[29,54,24,19,55,25],[11,45,15,46,46,16],[13,145,115,6,146,116],[14,74,46,23,75,47],[44,54,24,7,55,25],[59,46,16,1,47,17],[12,151,121,7,152,122],[12,75,47,26,76,48],[39,54,24,14,55,25],[22,45,15,41,46,16],[6,151,121,14,152,122],[6,75,47,34,76,48],[46,54,24,10,55,25],[2,45,15,64,46,16],[17,152,122,4,153,123],[29,74,46,14,75,47],[49,54,24,10,55,25],[24,45,15,46,46,16],[4,152,122,18,153,123],[13,74,46,32,75,47],[48,54,24,14,55,25],[42,45,15,32,46,16],[20,147,117,4,148,118],[40,75,47,7,76,48],[43,54,24,22,55,25],[10,45,15,67,46,16],[19,148,118,6,149,119],[18,75,47,31,76,48],[34,54,24,34,55,25],[20,45,15,61,46,16]],j.getRSBlocks=function(a,b){var c=j.getRsBlockTable(a,b);if(void 0==c)throw new Error("bad rs block @ typeNumber:"+a+"/errorCorrectLevel:"+b);for(var d=c.length/3,e=[],f=0;d>f;f++)for(var g=c[3*f+0],h=c[3*f+1],i=c[3*f+2],k=0;g>k;k++)e.push(new j(h,i));return e},j.getRsBlockTable=function(a,b){switch(b){case d.L:return j.RS_BLOCK_TABLE[4*(a-1)+0];case d.M:return j.RS_BLOCK_TABLE[4*(a-1)+1];case d.Q:return j.RS_BLOCK_TABLE[4*(a-1)+2];case d.H:return j.RS_BLOCK_TABLE[4*(a-1)+3];default:return void 0}},k.prototype={get:function(a){var b=Math.floor(a/8);return 1==(1&this.buffer[b]>>>7-a%8)},put:function(a,b){for(var c=0;b>c;c++)this.putBit(1==(1&a>>>b-c-1))},getLengthInBits:function(){return this.length},putBit:function(a){var b=Math.floor(this.length/8);this.buffer.length<=b&&this.buffer.push(0),a&&(this.buffer[b]|=128>>>this.length%8),this.length++}};var l=[[17,14,11,7],[32,26,20,14],[53,42,32,24],[78,62,46,34],[106,84,60,44],[134,106,74,58],[154,122,86,64],[192,152,108,84],[230,180,130,98],[271,213,151,119],[321,251,177,137],[367,287,203,155],[425,331,241,177],[458,362,258,194],[520,412,292,220],[586,450,322,250],[644,504,364,280],[718,560,394,310],[792,624,442,338],[858,666,482,382],[929,711,509,403],[1003,779,565,439],[1091,857,611,461],[1171,911,661,511],[1273,997,715,535],[1367,1059,751,593],[1465,1125,805,625],[1528,1190,868,658],[1628,1264,908,698],[1732,1370,982,742],[1840,1452,1030,790],[1952,1538,1112,842],[2068,1628,1168,898],[2188,1722,1228,958],[2303,1809,1283,983],[2431,1911,1351,1051],[2563,1989,1423,1093],[2699,2099,1499,1139],[2809,2213,1579,1219],[2953,2331,1663,1273]],o=function(){var a=function(a,b){this._el=a,this._htOption=b};return a.prototype.draw=function(a){function g(a,b){var c=document.createElementNS("http://www.w3.org/2000/svg",a);for(var d in b)b.hasOwnProperty(d)&&c.setAttribute(d,b[d]);return c}var b=this._htOption,c=this._el,d=a.getModuleCount();Math.floor(b.width/d),Math.floor(b.height/d),this.clear();var h=g("svg",{viewBox:"0 0 "+String(d)+" "+String(d),width:"100%",height:"100%",fill:b.colorLight});h.setAttributeNS("http://www.w3.org/2000/xmlns/","xmlns:xlink","http://www.w3.org/1999/xlink"),c.appendChild(h),h.appendChild(g("rect",{fill:b.colorDark,width:"1",height:"1",id:"template"}));for(var i=0;d>i;i++)for(var j=0;d>j;j++)if(a.isDark(i,j)){var k=g("use",{x:String(i),y:String(j)});k.setAttributeNS("http://www.w3.org/1999/xlink","href","#template"),h.appendChild(k)}},a.prototype.clear=function(){for(;this._el.hasChildNodes();)this._el.removeChild(this._el.lastChild)},a}(),p="svg"===document.documentElement.tagName.toLowerCase(),q=p?o:m()?function(){function a(){this._elImage.src=this._elCanvas.toDataURL("image/png"),this._elImage.style.display="block",this._elCanvas.style.display="none"}function d(a,b){var c=this;if(c._fFail=b,c._fSuccess=a,null===c._bSupportDataURI){var d=document.createElement("img"),e=function(){c._bSupportDataURI=!1,c._fFail&&_fFail.call(c)},f=function(){c._bSupportDataURI=!0,c._fSuccess&&c._fSuccess.call(c)};return d.onabort=e,d.onerror=e,d.onload=f,d.src="data:image/gif;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==",void 0}c._bSupportDataURI===!0&&c._fSuccess?c._fSuccess.call(c):c._bSupportDataURI===!1&&c._fFail&&c._fFail.call(c)}if(this._android&&this._android<=2.1){var b=1/window.devicePixelRatio,c=CanvasRenderingContext2D.prototype.drawImage;CanvasRenderingContext2D.prototype.drawImage=function(a,d,e,f,g,h,i,j){if("nodeName"in a&&/img/i.test(a.nodeName))for(var l=arguments.length-1;l>=1;l--)arguments[l]=arguments[l]*b;else"undefined"==typeof j&&(arguments[1]*=b,arguments[2]*=b,arguments[3]*=b,arguments[4]*=b);c.apply(this,arguments)}}var e=function(a,b){this._bIsPainted=!1,this._android=n(),this._htOption=b,this._elCanvas=document.createElement("canvas"),this._elCanvas.width=b.width,this._elCanvas.height=b.height,a.appendChild(this._elCanvas),this._el=a,this._oContext=this._elCanvas.getContext("2d"),this._bIsPainted=!1,this._elImage=document.createElement("img"),this._elImage.style.display="none",this._el.appendChild(this._elImage),this._bSupportDataURI=null};return e.prototype.draw=function(a){var b=this._elImage,c=this._oContext,d=this._htOption,e=a.getModuleCount(),f=d.width/e,g=d.height/e,h=Math.round(f),i=Math.round(g);b.style.display="none",this.clear();for(var j=0;e>j;j++)for(var k=0;e>k;k++){var l=a.isDark(j,k),m=k*f,n=j*g;c.strokeStyle=l?d.colorDark:d.colorLight,c.lineWidth=1,c.fillStyle=l?d.colorDark:d.colorLight,c.fillRect(m,n,f,g),c.strokeRect(Math.floor(m)+.5,Math.floor(n)+.5,h,i),c.strokeRect(Math.ceil(m)-.5,Math.ceil(n)-.5,h,i)}this._bIsPainted=!0},e.prototype.makeImage=function(){this._bIsPainted&&d.call(this,a)},e.prototype.isPainted=function(){return this._bIsPainted},e.prototype.clear=function(){this._oContext.clearRect(0,0,this._elCanvas.width,this._elCanvas.height),this._bIsPainted=!1},e.prototype.round=function(a){return a?Math.floor(1e3*a)/1e3:a},e}():function(){var a=function(a,b){this._el=a,this._htOption=b};return a.prototype.draw=function(a){for(var b=this._htOption,c=this._el,d=a.getModuleCount(),e=Math.floor(b.width/d),f=Math.floor(b.height/d),g=['<table style="border:0;border-collapse:collapse;">'],h=0;d>h;h++){g.push("<tr>");for(var i=0;d>i;i++)g.push('<td style="border:0;border-collapse:collapse;padding:0;margin:0;width:'+e+"px;height:"+f+"px;background-color:"+(a.isDark(h,i)?b.colorDark:b.colorLight)+';"></td>');g.push("</tr>")}g.push("</table>"),c.innerHTML=g.join("");var j=c.childNodes[0],k=(b.width-j.offsetWidth)/2,l=(b.height-j.offsetHeight)/2;k>0&&l>0&&(j.style.margin=l+"px "+k+"px")},a.prototype.clear=function(){this._el.innerHTML=""},a}();QRCode=function(a,b){if(this._htOption={width:256,height:256,typeNumber:4,colorDark:"#000000",colorLight:"#ffffff",correctLevel:d.H},"string"==typeof b&&(b={text:b}),b)for(var c in b)this._htOption[c]=b[c];"string"==typeof a&&(a=document.getElementById(a)),this._android=n(),this._el=a,this._oQRCode=null,this._oDrawing=new q(this._el,this._htOption),this._htOption.text&&this.makeCode(this._htOption.text)},QRCode.prototype.makeCode=function(a){this._oQRCode=new b(r(a,this._htOption.correctLevel),this._htOption.correctLevel),this._oQRCode.addData(a),this._oQRCode.make(),this._el.title=a,this._oDrawing.draw(this._oQRCode),this.makeImage()},QRCode.prototype.makeImage=function(){"function"==typeof this._oDrawing.makeImage&&(!this._android||this._android>=3)&&this._oDrawing.makeImage()},QRCode.prototype.clear=function(){this._oDrawing.clear()},QRCode.CorrectLevel=d}();

    const CONFIG = {
        paywayLink  : <?php echo wp_json_encode( $payway_link ); ?>,   // ABA PayWay URL (primary)
        usePayway   : <?php echo $use_payway ? 'true' : 'false'; ?>,
        bakongId    : <?php echo wp_json_encode( function_exists( 'reandaily_get_bakong_id_fallback' ) ? reandaily_get_bakong_id_fallback() : '' ); ?>,
        merchantName: <?php echo wp_json_encode( $merchant_name ); ?>,
        merchantCity: <?php echo wp_json_encode( $merchant_city ); ?>,
        amount      : <?php echo $currency === 'KHR' ? intval($price_khr) : floatval($price_usd); ?>,
        currency    : <?php echo wp_json_encode( strtoupper( $currency ) ); ?>,
        billNumber  : <?php echo wp_json_encode( $bill_number ); ?>,
        billToken   : <?php echo wp_json_encode( reandaily_generate_bill_token( $bill_number ) ); ?>,
        courseId    : <?php echo (int) $course_id; ?>,
        ajaxUrl     : <?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>,
        nonce       : <?php echo wp_json_encode( wp_create_nonce('reandaily_enroll_nonce') ); ?>,
        bakongApiEnabled: <?php echo ( function_exists( 'reandaily_get_theme_mod' ) ? reandaily_get_theme_mod( 'reandaily_bakong_enabled', false ) : get_theme_mod( 'reandaily_bakong_enabled', false ) ) ? 'true' : 'false'; ?>,
        isLoggedIn  : <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
        isAdmin     : <?php echo current_user_can( 'manage_options' ) ? 'true' : 'false'; ?>,
        userName    : <?php echo wp_json_encode( $user_name ); ?>,
        userEmail   : <?php echo wp_json_encode( $user_email ); ?>,
        userPhone   : <?php echo wp_json_encode( $user_phone ); ?>,
        manualQrPayload: <?php echo wp_json_encode( $manual_qr_payload ); ?>,
        manualDeepLink : <?php echo wp_json_encode( $aba_pay_deep_link ); ?>,
        serverIp       : <?php echo wp_json_encode( $server_ip ); ?>,
        qrLogoUrl      : <?php echo wp_json_encode( esc_url( $qr_logo_url ) ); ?>
    };

    let enrollmentData = {};
    let qrTimerInterval = null;
    let qrString = '';
    let isBakongApiActive = false; // Dynamically track whether automated API is active or fell back to manual
    let activeTab = 'auto'; // Tracks the active tab ('auto' or 'manual')

    function formatDebugMessage(message) {
        if (!message) return '';
        const lowerMsg = message.toLowerCase();
        if (lowerMsg.includes('403') || lowerMsg.includes('forbidden')) {
            const ipStr = CONFIG.serverIp ? CONFIG.serverIp : 'Unknown';
            return '🔴 HTTP Status 403 Forbidden:\n' +
                   '⚠️ អាសយដ្ឋាន Server IP របស់លោកអ្នក (' + ipStr + ') ត្រូវបានបិទដោយ Bakong API Firewall (WAF Block)។\n' +
                   'សូមចម្លង Server IP នេះ រួចផ្ញើទៅកាន់ក្រុមការងារបច្ចេកទេស Bakong/NBC ដើម្បី Whitelist IP នេះជាមុនសិន ទើបអាចប្រើប្រាស់ Automated API នេះបាន។\n\n' +
                   'Your WordPress server public IP (' + ipStr + ') is blocked by the Bakong API Firewall. ' +
                   'Please copy this IP and send it to the Bakong support team to whitelist it in order to use the automated API.';
        }
        return message;
    }

    // ── DOM refs ─────────────────────────────────────────────────────────
    const step1        = document.getElementById('enroll-step-1');
    const step2        = document.getElementById('enroll-step-2');
    const step3        = document.getElementById('enroll-step-3');
    const form         = document.getElementById('enroll-form');
    const btnNext      = document.getElementById('btn-next');
    const btnConfirm   = document.getElementById('btn-confirm-payment');
    const btnBack      = document.getElementById('btn-back-step1');
    const formError    = document.getElementById('form-error');
    const payError     = document.getElementById('payment-error');

    // Tab switcher DOM elements
    const tabAuto       = document.getElementById('tab-auto-pay');
    const tabManual     = document.getElementById('tab-manual-pay');
    const sectionAuto   = document.getElementById('section-auto-pay');
    const sectionManual = document.getElementById('section-manual-pay');
    const tabContainer  = document.getElementById('payment-method-tabs');

    // Render manual fallback QR code if container and payload exist
    const manualContainer = document.getElementById('manual-qr-container');
    if (manualContainer && CONFIG.manualQrPayload) {
        manualContainer.innerHTML = '';
        new QRCode(manualContainer, {
            text: CONFIG.manualQrPayload,
            width: 240,
            height: 240,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
        if (CONFIG.qrLogoUrl) {
            const logo = document.createElement('img');
            logo.src = CONFIG.qrLogoUrl;
            logo.style.position = 'absolute';
            logo.style.top = '50%';
            logo.style.left = '50%';
            logo.style.transform = 'translate(-50%, -50%)';
            logo.style.width = '46px';
            logo.style.height = '46px';
            logo.style.objectFit = 'contain';
            logo.style.background = '#ffffff';
            logo.style.padding = '3px';
            logo.style.borderRadius = '8px';
            logo.style.boxShadow = '0 2px 6px rgba(0,0,0,0.15)';
            logo.style.zIndex = '10';
            manualContainer.appendChild(logo);
        }
    }

    // ── Tab switching logic ──────────────────────────────────────────────
    function switchTab(tab) {
        activeTab = tab;
        if (tab === 'auto') {
            if (tabAuto) tabAuto.classList.add('active');
            if (tabManual) tabManual.classList.remove('active');
            if (sectionAuto) sectionAuto.style.display = 'block';
            if (sectionManual) sectionManual.style.display = 'none';
            if (btnConfirm) {
                btnConfirm.style.display = 'none'; // Hide the check button for auto-payment
            }
            // Start polling if MD5 is generated and API is active
            if (isBakongApiActive && md5HashValue) {
                startTransactionPolling(md5HashValue);
            }
        } else {
            if (tabManual) tabManual.classList.add('active');
            if (tabAuto) tabAuto.classList.remove('active');
            if (sectionManual) sectionManual.style.display = 'block';
            if (sectionAuto) sectionAuto.style.display = 'none';
            if (btnConfirm) {
                btnConfirm.style.display = 'block'; // Show the submit button for manual payment
                btnConfirm.innerHTML = '✅ ផ្ញើបង្កាន់ដៃបញ្ជាក់ → រួចរាល់ <span class="btn-loader hidden">⏳</span>';
            }
            // Stop polling to reduce unnecessary API requests
            if (transactionPollInterval) {
                clearInterval(transactionPollInterval);
            }
        }
    }

    if (tabAuto) {
        tabAuto.addEventListener('click', () => switchTab('auto'));
    }
    if (tabManual) {
        tabManual.addEventListener('click', () => switchTab('manual'));
    }

    // ── Step transitions ─────────────────────────────────────────────────
    function showStep(n) {
        [step1, step2, step3].forEach((s, i) => {
            if (!s) return;
            s.classList.toggle('active', i+1 === n);
            s.classList.toggle('hidden', i+1 !== n);
        });
    }

    let transactionPollInterval = null;
    let md5HashValue = '';

    // ── KHQR / PayWay QR Generation ───────────────────────────────────────
    function generateKHQR() {
        const load = document.getElementById('khqr-loading');
        const wrap = document.getElementById('khqr-canvas-wrap');
        if (!load || !wrap) return;

        load.classList.remove('hidden');
        load.innerHTML = '<div class="qr-spinner"></div><p>កំពុងបង្កើត QR Code...</p>';
        wrap.classList.add('hidden');

        // Always call AJAX endpoint to fetch the correct KHQR string (from Bakong API or generated locally)
        const fd = new FormData();
        fd.append('action',      'reandaily_get_khqr');
        fd.append('nonce',       CONFIG.nonce);
        fd.append('course_id',   CONFIG.courseId);
        fd.append('currency',    CONFIG.currency);
        fd.append('amount',      CONFIG.amount);
        fd.append('bill_number', CONFIG.billNumber);
        fd.append('bill_token',  CONFIG.billToken);

        fetch(CONFIG.ajaxUrl, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data && data.data.qr_string) {
                    qrString = data.data.qr_string;
                    md5HashValue = data.data.md5;
                    isBakongApiActive = data.data.bakong_api; // Store active state dynamically
                    
                    renderQR(qrString);
                    startQRTimer();

                    // Update Bakong deep link button dynamically
                    const bakongMobileBtn = document.getElementById('btn-bakong-mobile');
                    if (bakongMobileBtn) {
                        if (data.data.deeplink) {
                            bakongMobileBtn.href = data.data.deeplink;
                        } else {
                            bakongMobileBtn.href = 'bakong://pay?qr=' + encodeURIComponent(qrString);
                        }
                    }

                    // Always present automated QR Code view (PayWay / KHQR)
                    switchTab('auto');

                    // Hide debug error box
                    const debugDiv = document.getElementById('bakong-api-debug-info');
                    if (debugDiv) debugDiv.style.display = 'none';
                } else {
                    const msg = (data.data && data.data.message) ? data.data.message : 'មិនអាចបង្កើត QR Code បានទេ។';
                    renderFallbackQR(msg);
                    switchTab('auto');
                }
            })
            .catch(err => {
                renderFallbackQR('Network error: ' + err.message);
                switchTab('auto');

                // Show catch network errors
                const debugDiv = document.getElementById('bakong-api-debug-info');
                const debugMsg = document.getElementById('bakong-api-debug-message');
                const errText = 'AJAX Catch Error: ' + err.message;
                const is403 = errText.toLowerCase().includes('403') || errText.toLowerCase().includes('forbidden');
                if (CONFIG.isAdmin || is403) {
                    if (debugDiv && debugMsg) {
                        debugMsg.innerText = formatDebugMessage(errText);
                        debugDiv.style.display = 'block';
                    }
                }
            });
    }


    // Render QR using local QRCode.js library
    function renderQR(str) {
        const container = document.getElementById('khqr-img-container');
        const wrap  = document.getElementById('khqr-canvas-wrap');
        const load  = document.getElementById('khqr-loading');

        if (container) {
            container.innerHTML = ''; // Clear previous
            new QRCode(container, {
                text: str,
                width: 220,
                height: 220,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });

            // Append custom site logo in the center
            if (CONFIG.qrLogoUrl) {
                const logo = document.createElement('img');
                logo.src = CONFIG.qrLogoUrl;
                logo.style.position = 'absolute';
                logo.style.top = '50%';
                logo.style.left = '50%';
                logo.style.transform = 'translate(-50%, -50%)';
                logo.style.width = '42px';
                logo.style.height = '42px';
                logo.style.objectFit = 'contain';
                logo.style.background = '#ffffff';
                logo.style.padding = '3px';
                logo.style.borderRadius = '8px';
                logo.style.boxShadow = '0 2px 6px rgba(0,0,0,0.15)';
                logo.style.zIndex = '10';
                container.appendChild(logo);
            }
        }

        if (load) load.classList.add('hidden');
        if (wrap) wrap.classList.remove('hidden');
    }

    // Download QR Code logic
    const btnDownloadQR = document.getElementById('btn-download-qr');
    if (btnDownloadQR) {
        btnDownloadQR.addEventListener('click', function() {
            const imgEl = document.getElementById('khqr-img');
            if (!imgEl || !imgEl.src) return;
            
            fetch(imgEl.src)
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'ReanDaily-Course-' + CONFIG.courseId + '-QR.png';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                })
                .catch(() => alert('មិនអាចទាញយកបានទេ។ សូម Screenshot អេក្រង់នេះផ្ទាល់! (Could not download, please screenshot instead)'));
        });
    }

    function renderFallbackQR(msg) {
        const m = msg || 'QR Code generation failed.';
        document.getElementById('khqr-loading').innerHTML =
            '<p style="color:#dc2626;font-weight:700;padding:10px;">' +
            '⚠️ ' + m + '</p>';
    }

    // ── Bakong Transaction Live Polling Loop ────────────────────────────────
    function startTransactionPolling(hash) {
        if (!CONFIG.bakongApiEnabled || !hash) return;
        
        // Clear any existing polling loop first
        if (transactionPollInterval) clearInterval(transactionPollInterval);
        
        // Poll the WordPress check endpoint every 3 seconds
        transactionPollInterval = setInterval(() => {
            const formData = new FormData();
            formData.append('action',      'reandaily_check_bakong_transaction');
            formData.append('nonce',       CONFIG.nonce);
            formData.append('md5',         hash);
            formData.append('course_id',   CONFIG.courseId);
            formData.append('bill_number', CONFIG.billNumber);
            formData.append('bill_token',  CONFIG.billToken);

            fetch(CONFIG.ajaxUrl, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.data && res.data.redirect) {
                        // PAYMENT SUCCESS! Stop timers, show success step
                        clearInterval(qrTimerInterval);
                        clearInterval(transactionPollInterval);
                        
                        if (res.data.message) {
                            document.getElementById('success-message').innerHTML = res.data.message;
                        }
                        showStep(3);
                        
                        // Automatically redirect to study portal
                        setTimeout(() => {
                            window.location.href = res.data.redirect;
                        }, 2500);
                    } else if (!res.success && res.data && res.data.message) {
                        // Show polling error for diagnostic purposes
                        const debugDiv = document.getElementById('bakong-api-debug-info');
                        const debugMsg = document.getElementById('bakong-api-debug-message');
                        if (debugDiv && debugMsg) {
                            debugMsg.innerText = formatDebugMessage('Polling Error: ' + res.data.message);
                            debugDiv.style.display = 'block';
                        }
                    }
                })
                .catch(err => console.log('Polling status error:', err));
        }, 3000);
    }

    // ── QR Timer (3 min) ────────────────────────────────────────────────
    function startQRTimer() {
        let seconds = 3 * 60;
        const timerEl = document.getElementById('timer-val');
        qrTimerInterval = setInterval(() => {
            seconds--;
            const m = String(Math.floor(seconds / 60)).padStart(2, '0');
            const s = String(seconds % 60).padStart(2, '0');
            if (timerEl) timerEl.textContent = m + ':' + s;
            if (seconds <= 0) {
                clearInterval(qrTimerInterval);
                if (transactionPollInterval) clearInterval(transactionPollInterval);
                if (timerEl) timerEl.textContent = 'ផុតកំណត់';
                if (document.getElementById('khqr-canvas-wrap'))
                    document.getElementById('khqr-canvas-wrap').style.opacity = '0.3';
                document.getElementById('qr-countdown').textContent = '⚠️ QR Code ផុតកំណត់ — សូម refresh ទំព័រ';
            }
        }, 1000);
    }

    // ── Form submit → Step 2 ─────────────────────────────────────────────
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const name     = form.student_name.value.trim();
        const email    = form.student_email.value.trim();
        const phone    = form.student_phone.value.trim();
        const password = form.student_password ? form.student_password.value : '';

        // Validation
        if (!name || !email || !phone) {
            showError(formError, 'សូមបំពេញព័ត៌មានចាំបាច់ទាំងអស់ (*)')
            return;
        }
        if (!/\S+@\S+\.\S+/.test(email)) {
            showError(formError, 'អ៊ីមែលមិនត្រឹមត្រូវ');
            return;
        }

        hideError(formError);
        enrollmentData = { name, email, phone, password };

        // Disable button and show loading indicator
        btnNext.disabled = true;
        const loader = btnNext.querySelector('.btn-loader');
        if (loader) loader.classList.remove('hidden');

        // Create the user and pending enrollment record on the server first!
        const formData = new FormData();
        formData.append('action', 'reandaily_enroll_course');
        formData.append('nonce', CONFIG.nonce);
        formData.append('course_id', CONFIG.courseId);
        formData.append('bill_number', CONFIG.billNumber);
        formData.append('currency', CONFIG.currency.toUpperCase());
        formData.append('price', CONFIG.amount);
        formData.append('is_free', '0');
        formData.append('student_name', enrollmentData.name);
        formData.append('student_email', enrollmentData.email);
        formData.append('student_phone', enrollmentData.phone);
        formData.append('student_password', enrollmentData.password || '');

        fetch(CONFIG.ajaxUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                btnNext.disabled = false;
                if (loader) loader.classList.add('hidden');
                if (res.success) {
                    // Nonce is verified via secure guest fallback on the server.
                    // Move to step 2 (payment)
                    showStep(2);
                    generateKHQR();
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : 'មានបញ្ហា។ សូមព្យាយាមម្តងទៀត។';
                    showError(formError, msg);
                }
            })
            .catch(err => {
                btnNext.disabled = false;
                if (loader) loader.classList.add('hidden');
                showError(formError, 'Network error. Please try again.');
            });
    });

    // ── Back button ──────────────────────────────────────────────────────
    if (btnBack) {
        btnBack.addEventListener('click', () => {
            clearInterval(qrTimerInterval);
            if (transactionPollInterval) clearInterval(transactionPollInterval);
            showStep(1);
        });
    }

    // ── Payment confirmation → AJAX ──────────────────────────────────────
    if (btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            setLoading(btnConfirm, true);
            hideError(payError);

            // IF ACTIVE TAB IS AUTOMATED AND BAKONG API IS ACTIVE: Checking transaction verification strictly on click!
            if (activeTab === 'auto' && isBakongApiActive && md5HashValue) {
                const formData = new FormData();
                formData.append('action',      'reandaily_check_bakong_transaction');
                formData.append('nonce',       CONFIG.nonce);
                formData.append('md5',         md5HashValue);
                formData.append('course_id',   CONFIG.courseId);
                formData.append('bill_number', CONFIG.billNumber);
                formData.append('bill_token',  CONFIG.billToken);

                fetch(CONFIG.ajaxUrl, { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(res => {
                        setLoading(btnConfirm, false);
                        if (res.success && res.data && res.data.redirect) {
                            clearInterval(qrTimerInterval);
                            if (transactionPollInterval) clearInterval(transactionPollInterval);
                            
                            if (res.data.message) {
                                document.getElementById('success-message').innerHTML = res.data.message;
                            }
                            showStep(3);
                            setTimeout(() => { window.location.href = res.data.redirect; }, 2500);
                        } else {
                            // Payment not yet found in the bank ledger or an API error occurred
                            const defaultMsg = '❌ ប្រព័ន្ធមិនទាន់ទទួលបានការទូទាត់ប្រាក់ពីគណនីរបស់អ្នកឡើយ។ សូមរង់ចាំ ១០វិនាទី រួចចុចម្តងទៀត! (Payment not found in ledger yet, please wait 10 seconds and try again)';
                            const errMsg = (res.data && res.data.message) ? '❌ API Verification Info: ' + res.data.message : defaultMsg;
                            showError(payError, errMsg);
                        }
                    })
                    .catch(() => {
                        setLoading(btnConfirm, false);
                        showError(payError, 'ការតភ្ជាប់ Network ទៅកាន់ធនាគារមានបញ្ហា។ សូមព្យាយាមម្តងទៀត។');
                    });
                return;
            }

            // IF ACTIVE TAB IS MANUAL (Fallback / Manual Transfer mode): Create the pending account to verify later
            const refInput = document.getElementById('transaction_ref');
            const fileInput = document.getElementById('receipt_file');
            
            if (refInput && !refInput.value.trim()) {
                showError(payError, 'សូមបញ្ចូលលេខយោងប្រតិបត្តិការជាមុនសិន! (Please enter the transaction reference number)');
                setLoading(btnConfirm, false);
                return;
            }
            
            const formData = new FormData();
            formData.append('action',      'reandaily_enroll_course');
            formData.append('nonce',       CONFIG.nonce);
            formData.append('course_id',   CONFIG.courseId);
            formData.append('bill_number', CONFIG.billNumber);
            formData.append('currency',    CONFIG.currency.toUpperCase());
            formData.append('price',       CONFIG.amount);
            formData.append('is_free',     '0');
            formData.append('student_name',  enrollmentData.name);
            formData.append('student_email', enrollmentData.email);
            formData.append('student_phone', enrollmentData.phone);
            formData.append('student_password', enrollmentData.password || '');
            formData.append('qr_string',   qrString);
            formData.append('transaction_ref', refInput ? refInput.value.trim() : '');
            if (fileInput && fileInput.files.length > 0) {
                formData.append('receipt_file', fileInput.files[0]);
            }

            fetch(CONFIG.ajaxUrl, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    setLoading(btnConfirm, false);
                    if (data.success) {
                        clearInterval(qrTimerInterval);
                        if (transactionPollInterval) clearInterval(transactionPollInterval);
                        
                        // Explicitly render manual pending confirmation instructions to user
                        const successTitle = document.getElementById('success-title');
                        if (successTitle) {
                            successTitle.innerHTML = '⌛️ រង់ចាំការផ្ទៀងផ្ទាត់ការទូទាត់ប្រាក់';
                        }
                        
                        document.getElementById('success-message').innerHTML = '📋 <b>ព័ត៌មានទូទាត់របស់អ្នកត្រូវបានរក្សាទុក!</b><br><span style="font-size:14.5px;color:#475569;display:block;margin-top:12px;line-height:1.7;">សូមអរគុណ! ការផ្ទេរប្រាក់របស់អ្នកកំពុងត្រូវបានផ្ទៀងផ្ទាត់ដោយអ្នកសម្របសម្រួល (Admin)។ វគ្គសិក្សានឹងបើកជូនលោកអ្នកក្នុងពេលឆាប់ៗ (ចន្លោះពី ៥ ទៅ ១៥នាទី) បន្ទាប់ពីការពិនិត្យជោគជ័យ!<br><br>📧 អ៊ីមែលបញ្ជាក់ការចុះឈ្មោះបឋមត្រូវបានផ្ញើទៅកាន់ប្រអប់សំបុត្ររបស់អ្នករួចរាល់។</span>';
                        
                        // Update icon to beautiful hourglass pending animation
                        const animWrap = document.querySelector('.success-anim');
                        if (animWrap) {
                            animWrap.innerHTML = `
                                <div class="pending-circle" style="width:80px; height:80px; margin:0 auto 20px; border-radius:50%; background:#eff6ff; border:3px dashed #3b82f6; display:flex; align-items:center; justify-content:center; animation: spin-dashed 12s linear infinite;">
                                    <span style="font-size:40px;">⏳</span>
                                </div>
                                <style>
                                    @keyframes spin-dashed { 100% { transform: rotate(360deg); } }
                                </style>
                            `;
                        }
                        
                        // Hide success redirects actions and only keep dynamic confirmation screen open
                        const successActions = document.getElementById('success-actions');
                        if (successActions) {
                            successActions.innerHTML = '<a href="' + CONFIG.ajaxUrl.replace("/wp-admin/admin-ajax.php", "/courses/") + '" class="btn-enroll-submit" style="background:#005a9c; box-shadow: 0 4px 12px rgba(0, 90, 156, 0.2);">← ត្រលប់ទៅទំព័រវគ្គសិក្សាវិញ</a>' +
                                                     '<a href="' + CONFIG.ajaxUrl.replace("/wp-admin/admin-ajax.php", "/profile/") + '" class="btn-back" style="margin-top:12px; display:block; text-align:center;">📥 ចូលទៅកាន់គណនីរបស់ខ្ញុំ</a>';
                        }
                        
                        showStep(3);
                    } else {
                        const msg = (data.data && data.data.message) ? data.data.message : 'មានបញ្ហាក្នុងការចុះឈ្មោះ។ សូមទាក់ទងក្រុមការងារ។';
                        showError(payError, msg);
                    }
                })
                .catch(() => {
                    setLoading(btnConfirm, false);
                    showError(payError, 'ការតភ្ជាប់ Network មានបញ្ហា។ សូមព្យាយាមម្ដងទៀត។');
                });
        });
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    function showError(el, msg) {
        el.textContent = msg;
        el.classList.remove('hidden');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    function hideError(el) {
        el.textContent = '';
        el.classList.add('hidden');
    }
    function setLoading(btn, loading) {
        const loader = btn.querySelector('.btn-loader');
        btn.disabled = loading;
        if (loader) loader.classList.toggle('hidden', !loading);
    }

    // If user is logged in, auto-submit Step 1 to show QR code immediately!
    if (CONFIG.isLoggedIn) {
        const step1Content = document.getElementById('enroll-step-1');
        if (step1Content) {
            step1Content.innerHTML = `
                <div class="auto-checkout-loader" style="text-align: center; padding: 40px 20px; font-family: 'Kantumruy Pro', sans-serif !important;">
                    <div class="qr-spinner" style="width: 40px; height: 40px; border: 4px solid #005a9c; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px auto;"></div>
                    <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">កំពុងរៀបចំការទូទាត់ប្រាក់...</h3>
                    <p style="font-size: 13.5px; color: #64748b; margin: 0;">សូមរង់ចាំមួយភ្លែត ប្រព័ន្ធកំពុងបង្កើត KHQR សម្រាប់គណនីរបស់អ្នក។</p>
                </div>
            `;
        }
        autoSubmitCheckout();
    }

    function autoSubmitCheckout() {
        enrollmentData = {
            name: CONFIG.userName,
            email: CONFIG.userEmail,
            phone: CONFIG.userPhone,
            password: ''
        };

        const formData = new FormData();
        formData.append('action', 'reandaily_enroll_course');
        formData.append('nonce', CONFIG.nonce);
        formData.append('course_id', CONFIG.courseId);
        formData.append('bill_number', CONFIG.billNumber);
        formData.append('currency', CONFIG.currency.toUpperCase());
        formData.append('price', CONFIG.amount);
        formData.append('is_free', '0');
        formData.append('student_name', enrollmentData.name);
        formData.append('student_email', enrollmentData.email);
        formData.append('student_phone', enrollmentData.phone);
        formData.append('student_password', '');

        fetch(CONFIG.ajaxUrl, { method: 'POST', body: formData })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    showStep(2);
                    generateKHQR();
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : 'មានបញ្ហា។ សូមព្យាយាមម្តងទៀត។';
                    const step1Content = document.getElementById('enroll-step-1');
                    if (step1Content) {
                        step1Content.innerHTML = `<div class="form-error" style="display:block;">⚠️ ${msg}</div>`;
                    }
                }
            })
            .catch(err => {
                const step1Content = document.getElementById('enroll-step-1');
                if (step1Content) {
                    step1Content.innerHTML = `<div class="form-error" style="display:block;">⚠️ Connection error. Please reload.</div>`;
                }
            });
    }

})();
</script>

<?php else : ?>
<!-- Free course — submit form directly via AJAX -->
<script src="<?php echo includes_url('js/jquery/jquery.min.js'); ?>"></script>
<script>
(function() {
    const form      = document.getElementById('enroll-form');
    const btnNext   = document.getElementById('btn-next');
    const formError = document.getElementById('form-error');
    const step1     = document.getElementById('enroll-step-1');
    const step3     = document.getElementById('enroll-step-3');
    const ajaxUrl   = <?php echo wp_json_encode( admin_url('admin-ajax.php') ); ?>;

    if (!form) return;

    // Auto submit free course checkout if logged in!
    const isLoggedIn = <?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
    if (isLoggedIn) {
        if (step1) {
            step1.innerHTML = `
                <div class="auto-checkout-loader" style="text-align: center; padding: 40px 20px; font-family: 'Kantumruy Pro', sans-serif !important;">
                    <div class="qr-spinner" style="width: 40px; height: 40px; border: 4px solid #005a9c; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px auto;"></div>
                    <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">កំពុងចុះឈ្មោះចូលរៀន...</h3>
                    <p style="font-size: 13.5px; color: #64748b; margin: 0;">សូមរង់ចាំមួយភ្លែត ប្រព័ន្ធកំពុងបើកវគ្គសិក្សាជូនគណនីរបស់អ្នក។</p>
                </div>
                <style>
                    @keyframes spin { 100% { transform: rotate(360deg); } }
                </style>
            `;
        }
        
        const data = new FormData();
        data.append('action', 'reandaily_enroll_course');
        data.append('nonce', <?php echo wp_json_encode( wp_create_nonce('reandaily_enroll_nonce') ); ?>);
        data.append('course_id', form.course_id.value);
        data.append('bill_number', form.bill_number.value);
        data.append('currency', form.currency.value);
        data.append('price', form.price.value);
        data.append('is_free', '1');
        data.append('student_name', form.student_name.value);
        data.append('student_email', form.student_email.value);
        data.append('student_phone', <?php 
            global $wpdb;
            $current_user = wp_get_current_user();
            $table = $wpdb->prefix . 'reandaily_enrollments';
            $user_phone = $wpdb->get_var( $wpdb->prepare(
                "SELECT student_phone FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT 1",
                $current_user->ID
            ) );
            if ( empty( $user_phone ) ) {
                $user_phone = get_user_meta( $current_user->ID, 'billing_phone', true );
            }
            if ( empty( $user_phone ) ) {
                $user_phone = get_user_meta( $current_user->ID, 'phone', true );
            }
            if ( empty( $user_phone ) ) {
                $user_phone = '000000000';
            }
            echo wp_json_encode( $user_phone );
        ?>);
        data.append('student_password', '');

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    if (step1) step1.classList.add('hidden');
                    step3.classList.remove('hidden');
                    step3.classList.add('active');
                    if (res.data && res.data.message)
                        document.getElementById('success-message').innerHTML = res.data.message;
                    if (res.data && res.data.redirect)
                        setTimeout(() => window.location.href = res.data.redirect, 1500);
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : 'មានបញ្ហា។ សូមព្យាយាមមួយ ម្ដងទៀត។';
                    if (step1) {
                        step1.innerHTML = `<div class="form-error" style="display:block;">⚠️ ${msg}</div>`;
                    }
                }
            })
            .catch(() => {
                if (step1) {
                    step1.innerHTML = `<div class="form-error" style="display:block;">⚠️ Connection error. Please reload.</div>`;
                }
            });
        return;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const name  = form.student_name.value.trim();
        const email = form.student_email.value.trim();
        const phone = form.student_phone.value.trim();

        if (!name || !email || !phone) {
            formError.textContent = 'សូមបំពេញព័ត៌មានចាំបាច់ទាំងអស់ (*)';
            formError.classList.remove('hidden');
            return;
        }
        formError.classList.add('hidden');

        btnNext.disabled = true;
        btnNext.querySelector('.btn-loader').classList.remove('hidden');

        const data = new FormData(form);
        data.append('action', 'reandaily_enroll_course');
        data.append('student_name', name);
        data.append('student_email', email);
        data.append('student_phone', phone);
        data.append('student_password', form.student_password ? form.student_password.value : '');

        fetch(ajaxUrl, { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                btnNext.disabled = false;
                btnNext.querySelector('.btn-loader').classList.add('hidden');
                if (res.success) {
                    step1.classList.add('hidden');
                    step3.classList.remove('hidden');
                    step3.classList.add('active');
                    if (res.data && res.data.message)
                        document.getElementById('success-message').innerHTML = res.data.message;
                    if (res.data && res.data.redirect)
                        setTimeout(() => window.location.href = res.data.redirect, 2000);
                } else {
                    const msg = (res.data && res.data.message) ? res.data.message : 'មានបញ្ហា។ សូមព្យាយាមមួយ ម្ដងទៀត។';
                    formError.textContent = msg;
                    formError.classList.remove('hidden');
                }
            })
            .catch(() => {
                btnNext.disabled = false;
                btnNext.querySelector('.btn-loader').classList.add('hidden');
                formError.textContent = 'Network error. Please try again.';
                formError.classList.remove('hidden');
            });
    });
})();
</script>
<?php endif; ?>

<?php get_footer(); ?>
