<?php
/**
 * Theme footer template for ReanDaily.
 */
?>
<footer id="custom-footer" class="premium-footer">
    <div class="container footer-container">
        <!-- Brand Info -->
        <div class="footer-brand">
            <?php 
            $logo_url = '';
            if ( has_custom_logo() ) {
                $custom_logo_id = get_theme_mod( 'custom_logo' );
                $logo_image = wp_get_attachment_image_src( $custom_logo_id, 'full' );
                if ( $logo_image ) {
                    $logo_url = $logo_image[0];
                }
            } else {
                $customizer_logo_url = get_theme_mod( 'ms_lms_starter_logo' );
                if ( $customizer_logo_url ) {
                    $logo_url = $customizer_logo_url;
                }
            }

            if ( $logo_url ) : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="footer-logo-img-wrap" rel="home">
                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php bloginfo( 'name' ); ?>" class="footer-logo-img">
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="footer-logo"><?php bloginfo('name'); ?></a>
            <?php endif; ?>
            <p class="footer-tagline">រៀនរាល់ថ្ងៃ ដើម្បីអនាគតកាន់តែប្រសើរ</p>
        </div>

        <!-- Social Connections -->
        <div class="footer-socials">
            <h5 class="social-title">តាមដានពួកយើង</h5>
            <div class="social-icons">
                <!-- Telegram -->
                <a href="https://t.me/reandaily" class="social-btn telegram" target="_blank" aria-label="Telegram">
                    <span class="icon">✈</span>
                    <span class="lbl">Telegram</span>
                </a>
                <!-- Facebook -->
                <a href="https://facebook.com/reandaily" class="social-btn facebook" target="_blank" aria-label="Facebook">
                    <span class="icon">f</span>
                    <span class="lbl">Facebook</span>
                </a>
                <!-- YouTube -->
                <a href="https://youtube.com/reandaily" class="social-btn youtube" target="_blank" aria-label="YouTube">
                    <span class="icon">▶</span>
                    <span class="lbl">YouTube</span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="footer-bottom">
        <div class="container footer-bottom-container">
            <p class="footer-copyright">&copy; <?php echo date('Y'); ?> <span class="site-name-highlight"><?php bloginfo('name'); ?></span>. រក្សាសិទ្ធិគ្រប់យ៉ាង។</p>
        </div>
    </div>
</footer>


<?php wp_footer(); ?>
</body>
</html>
