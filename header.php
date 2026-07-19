<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header id="custom-header">
    <div class="container">
        <div class="logo">
            <?php 
            if ( has_custom_logo() ) {
                the_custom_logo();
            } else {
                $customizer_logo_url = get_theme_mod( 'ms_lms_starter_logo' );
                if ( $customizer_logo_url ) {
                    ?>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="custom-logo-link" rel="home">
                        <img src="<?php echo esc_url( $customizer_logo_url ); ?>" alt="<?php bloginfo( 'name' ); ?>" class="custom-logo">
                    </a>
                    <?php
                } else {
                    ?>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="custom-logo-text"><?php bloginfo( 'name' ); ?></a>
                    <?php
                }
            }
            ?>
        </div>
        
        <div class="header-right">
            <nav class="navigation">
                <?php
                // Display the WordPress menu, fallback to HTML links if not set
                if ( has_nav_menu( 'primary' ) ) {
                    wp_nav_menu( array(
                        'theme_location' => 'primary',
                        'menu_class'     => 'main-menu',
                        'container'      => false,
                    ) );
                } else {
                    echo '<ul class="main-menu">';
                    echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">ទំព័រដើម</a></li>';
                    echo '<li><a href="' . esc_url( home_url( '/all-courses/' ) ) . '">វគ្គសិក្សាទាំងអស់</a></li>';
                    echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '">អំពីយើង</a></li>';
                    echo '<li><a href="' . esc_url( home_url( '/contact/' ) ) . '">ទំនាក់ទំនង</a></li>';
                    echo '</ul>';
                }
                ?>
            </nav>
            
            <?php if ( defined( 'MS_LMS_VERSION' ) ) : ?>
                <div class="header-auth-wrap">
                    <?php get_template_part( 'templates/header/parts/authorization-links' ); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

