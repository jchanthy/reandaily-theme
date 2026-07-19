<?php get_header(); ?>

<main id="main-content" class="homepage">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container hero-container">
            <div class="hero-text">
                <span class="hero-badge"><?php echo esc_html( get_theme_mod( 'reandaily_hero_badge', '📚 វេទិកាសម្រាប់ការរៀនសូត្រឥតឈប់ឈរ' ) ); ?></span>
                <h1><?php echo esc_html( get_theme_mod( 'reandaily_hero_headline', 'រៀនរាល់ថ្ងៃ ដើម្បីអនាគតកាន់តែប្រសើរ' ) ); ?></h1>
                <p><?php echo esc_html( get_theme_mod( 'reandaily_hero_description', 'ទទួលបានការសិក្សាវគ្គជំនាញអនឡាញល្អៗជាច្រើន ជាមួយគ្រូឧទ្ទេសដែលមានបទពិសោធន៍ខ្ពស់។ សិក្សាតាមតម្រូវការ គ្រប់ពេលវេលា និងគ្រប់ទីកន្លែង។' ) ); ?></p>
                <div class="hero-ctas">
                    <?php 
                    $primary_url = get_theme_mod( 'reandaily_hero_btn_primary_url', '' );
                    if ( empty( $primary_url ) ) {
                        $courses_archive = get_post_type_archive_link( 'stm-courses' );
                        $primary_url = $courses_archive ? $courses_archive : home_url( '/all-courses/' );
                    }
                    ?>
                    <a href="<?php echo esc_url( $primary_url ); ?>" class="btn btn-primary"><?php echo esc_html( get_theme_mod( 'reandaily_hero_btn_primary', 'រុករកវគ្គសិក្សា' ) ); ?></a>
                    
                    <?php 
                    $secondary_url = get_theme_mod( 'reandaily_hero_btn_secondary_url', home_url( '/about/' ) );
                    ?>
                    <a href="<?php echo esc_url( $secondary_url ); ?>" class="btn btn-secondary"><?php echo esc_html( get_theme_mod( 'reandaily_hero_btn_secondary', 'ស្វែងយល់បន្ថែម' ) ); ?></a>
                </div>
            </div>
            <div class="hero-graphic">
                <div class="graphic-card">
                    <div class="glow-bg"></div>
                    <div class="dashboard-widget">
                        <?php 
                        if ( is_user_logged_in() ) : 
                            $current_user = wp_get_current_user();
                            $user_id = $current_user->ID;
                            
                            global $wpdb;
                            $table_name = $wpdb->prefix . 'stm_lms_user_courses';
                            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
                            
                            $enrolled_count = 0;
                            $avg_progress = 0;
                            
                            if ( $table_exists ) {
                                // Query using string-matching for maximum robustness
                                $user_courses_data = $wpdb->get_results( "SELECT progress_percent FROM {$table_name} WHERE user_id = '{$user_id}'" );
                                
                                if ( ! empty( $user_courses_data ) ) {
                                    $enrolled_count = count( $user_courses_data );
                                    $total_progress = 0;
                                    foreach ( $user_courses_data as $row ) {
                                        $total_progress += intval( $row->progress_percent );
                                    }
                                    $avg_progress = round( $total_progress / $enrolled_count );
                                }
                            } else {
                                // Fallback if table doesn't exist
                                $user_courses = get_user_meta( $user_id, 'stm_lms_courses', true );
                                if ( is_array( $user_courses ) ) {
                                    $enrolled_count = count( $user_courses );
                                }
                            }
                            
                            $display_title = 'ស្ថិតិសិក្សារបស់អ្នក';
                            $display_tag   = 'គណនីសិក្សា';
                            $stat_1_label  = 'វគ្គសិក្សាកំពុងរៀន';
                            $stat_1_value  = $enrolled_count . ' វគ្គ';
                            
                            $stat_2_label  = 'វឌ្ឍនភាពសិក្សា';
                            $stat_2_value  = $avg_progress . '%';
                        else :
                            // For Guest users, display the active platform-wide stats dynamically!
                            $display_title = 'ស្ថិតិសិក្សារួមប្រចាំថ្ងៃ';
                            $display_tag   = 'បច្ចុប្បន្ន';
                            
                            // Query actual database to show live numbers
                            $course_counts = wp_count_posts( 'stm-courses' );
                            $active_courses = isset( $course_counts->publish ) ? intval( $course_counts->publish ) : 0;
                            
                            $user_counts = count_users();
                            $db_students = isset( $user_counts['total_users'] ) ? intval( $user_counts['total_users'] ) : 0;
                            
                            $stat_1_label  = 'វគ្គសិក្សាសរុប';
                            $stat_1_value  = $active_courses . ' វគ្គ';
                            
                            $stat_2_label  = 'សិស្សកំពុងរៀន';
                            $stat_2_value  = number_format( $db_students ) . ' នាក់';
                        endif;
                        ?>
                        <div class="widget-header">
                            <div class="widget-title">
                                <span class="pulse-dot"></span>
                                <h5><?php echo esc_html( $display_title ); ?></h5>
                            </div>
                            <span class="widget-tag"><?php echo esc_html( $display_tag ); ?></span>
                        </div>
                        
                        <!-- Premium SVG Chart -->
                        <div class="widget-chart">
                            <svg viewBox="0 0 300 120" class="svg-chart">
                                <defs>
                                    <linearGradient id="chart-glow" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#007bff" stop-opacity="0.3"></stop>
                                        <stop offset="100%" stop-color="#007bff" stop-opacity="0"></stop>
                                    </linearGradient>
                                </defs>
                                <!-- Chart Area Fill -->
                                <path d="M 0 100 L 0 50 Q 50 20 100 70 T 200 40 T 300 20 L 300 100 Z" fill="url(#chart-glow)"></path>
                                <!-- Chart Line -->
                                <path d="M 0 50 Q 50 20 100 70 T 200 40 T 300 20" fill="none" stroke="#007bff" stroke-width="4" stroke-linecap="round"></path>
                                <!-- Grid Lines -->
                                <line x1="0" y1="100" x2="300" y2="100" stroke="rgba(226, 232, 240, 0.5)" stroke-width="1"></line>
                                <line x1="0" y1="60" x2="300" y2="60" stroke="rgba(226, 232, 240, 0.2)" stroke-width="1" stroke-dasharray="4"></line>
                                <!-- Tooltip Highlight Dot -->
                                <circle cx="200" cy="40" r="6" fill="#007bff" stroke="#ffffff" stroke-width="2"></circle>
                            </svg>
                        </div>
                        
                        <div class="widget-footer">
                            <div class="footer-stat">
                                <span class="stat-label"><?php echo esc_html( $stat_1_label ); ?></span>
                                <span class="stat-value"><?php echo esc_html( $stat_1_value ); ?></span>
                            </div>
                            <div class="footer-stat">
                                <span class="stat-label"><?php echo esc_html( $stat_2_label ); ?></span>
                                <span class="stat-value text-green"><?php echo esc_html( $stat_2_value ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Bar -->
    <?php
    global $wpdb;

    // 1. Dynamic Students Count
    // Count all active students in WordPress users table
    $user_counts = count_users();
    $db_students = isset( $user_counts['total_users'] ) ? intval( $user_counts['total_users'] ) : 0;
    $display_students = number_format( $db_students );

    // 2. Dynamic Courses Count
    $course_counts = wp_count_posts( 'stm-courses' );
    $db_courses = isset( $course_counts->publish ) ? intval( $course_counts->publish ) : 0;
    $display_courses = number_format( $db_courses );

    // 3. Dynamic Instructors Count
    // Count unique authors who published courses, fallback to stm_lms_instructor role count
    $db_instructors = $wpdb->get_var( "SELECT COUNT(DISTINCT post_author) FROM {$wpdb->posts} WHERE post_type = 'stm-courses' AND post_status = 'publish'" );
    $db_instructors = intval( $db_instructors );
    if ( $db_instructors === 0 && isset( $user_counts['avail_roles']['stm_lms_instructor'] ) ) {
        $db_instructors = intval( $user_counts['avail_roles']['stm_lms_instructor'] );
    }
    $display_instructors = number_format( max( 1, $db_instructors ) );

    // 4. Dynamic Rating Count
    // Calculate average from comments metadata key 'mark' used by MasterStudy LMS review comments
    $avg_rating = $wpdb->get_var( "SELECT AVG(meta_value) FROM {$wpdb->commentmeta} WHERE meta_key = 'mark'" );
    $display_rating = $avg_rating ? number_format( floatval( $avg_rating ), 1 ) : '4.9';
    $display_rating .= '★';
    ?>
    <section class="stats-section">
        <div class="container stats-container">
            <div class="stat-item">
                <h3><?php echo esc_html( $display_students ); ?></h3>
                <p>សិស្សកំពុងសិក្សា</p>
            </div>
            <div class="stat-item">
                <h3><?php echo esc_html( $display_courses ); ?></h3>
                <p>វគ្គសិក្សាជំនាញ</p>
            </div>
            <div class="stat-item">
                <h3><?php echo esc_html( $display_instructors ); ?></h3>
                <p>គ្រូឧទ្ទេសជំនាញ</p>
            </div>
            <div class="stat-item">
                <h3><?php echo esc_html( $display_rating ); ?></h3>
                <p>ការវាយតម្លៃខ្ពស់</p>
            </div>
        </div>
    </section>

    <!-- Categories Grid -->
    <section id="categories" class="categories-section">
        <div class="container">
            <div class="section-title">
                <h2>ស្វែងរកតាមប្រភេទវគ្គសិក្សា</h2>
                <p>ជ្រើសរើសជំនាញដែលអ្នកចង់សិក្សា ដើម្បីចាប់ផ្តើមអភិវឌ្ឍខ្លួនឯង</p>
            </div>
            <div class="categories-grid">
                <?php
                // Get course categories from MasterStudy LMS taxonomy, ordered by number of courses (highest first)
                $categories = get_terms( 'stm_lms_course_taxonomy', array(
                    'hide_empty' => false, // Show all so they can build out categories
                    'orderby'    => 'count',
                    'order'      => 'DESC',
                ) );

                // Emoji icon mapping based on slug keywords
                $icon_map = array(
                    'web-development'  => '💻',
                    'development'      => '💻',
                    'digital-marketing'=> '📈',
                    'marketing'        => '📈',
                    'graphic-design'   => '🎨',
                    'design'           => '🎨',
                    'language'         => '🗣️',
                    'english'          => '🗣️',
                    'business'         => '💼',
                    'finance'          => '💵',
                    'photography'      => '📷',
                    'video'            => '📹',
                );

                if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
                    // Limit to top 4 categories for layout consistency
                    $display_cats = array_slice( $categories, 0, 4 );
                    foreach ( $display_cats as $cat ) {
                        // Find matching emoji or default to graduation hat
                        $emoji = '🎓';
                        foreach ( $icon_map as $slug_key => $icon ) {
                            if ( strpos( strtolower( $cat->slug ), $slug_key ) !== false ) {
                                $emoji = $icon;
                                break;
                            }
                        }
                        
                        // Generate dynamic link to our catalog page
                        $courses_archive = get_post_type_archive_link( 'stm-courses' );
                        $catalog_base = $courses_archive ? $courses_archive : home_url( '/all-courses/' );
                        $cat_link = add_query_arg( 'category', $cat->slug, $catalog_base );
                        
                        // Get description or fallback
                        $desc = ! empty( $cat->description ) ? $cat->description : sprintf( 'វគ្គសិក្សាទាក់ទងនឹង %s គុណភាពខ្ពស់', $cat->name );
                        ?>
                        <div class="category-card" style="cursor: pointer;" onclick="window.location.href='<?php echo esc_url( $cat_link ); ?>'">
                            <span class="cat-icon"><?php echo esc_html( $emoji ); ?></span>
                            <h4><?php echo esc_html( $cat->name ); ?></h4>
                            <p><?php echo esc_html( $desc ); ?></p>
                        </div>
                        <?php
                    }
                } else {
                    // Fallback placeholders if no categories exist in database yet
                    $mock_cats = array(
                        array( 'name' => 'Web Development', 'emoji' => '💻', 'desc' => 'HTML, CSS, JavaScript, React & WordPress', 'slug' => 'web-development' ),
                        array( 'name' => 'Digital Marketing', 'emoji' => '📈', 'desc' => 'SEO, Social Media, Content & Ads', 'slug' => 'digital-marketing' ),
                        array( 'name' => 'Graphic Design', 'emoji' => '🎨', 'desc' => 'UI/UX, Photoshop, Illustrator & Figma', 'slug' => 'graphic-design' ),
                        array( 'name' => 'Language Academy', 'emoji' => '🗣️', 'desc' => 'English, Chinese, Japanese & Korean', 'slug' => 'language-academy' ),
                    );
                    foreach ( $mock_cats as $cat ) {
                        $courses_archive = get_post_type_archive_link( 'stm-courses' );
                        $catalog_base = $courses_archive ? $courses_archive : home_url( '/all-courses/' );
                        $cat_link = add_query_arg( 'category', $cat['slug'], $catalog_base );
                        ?>
                        <div class="category-card" style="cursor: pointer;" onclick="window.location.href='<?php echo esc_url( $cat_link ); ?>'">
                            <span class="cat-icon"><?php echo esc_html( $cat['emoji'] ); ?></span>
                            <h4><?php echo esc_html( $cat['name'] ); ?></h4>
                            <p><?php echo esc_html( $cat['desc'] ); ?></p>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Dynamic Courses Section -->
    <section id="courses" class="courses-section">
        <div class="container">
            <div class="section-title">
                <h2>វគ្គសិក្សាថ្មីៗបំផុត</h2>
                <p>ចាប់ផ្តើមរៀនសូត្រជាមួយវគ្គសិក្សាដែលពេញនិយម និងមានគុណភាពខ្ពស់</p>
            </div>
            
            <div class="courses-grid">
                <?php
                // Query active MasterStudy LMS courses
                $args = array(
                    'post_type'      => 'stm-courses',
                    'posts_per_page' => 3,
                    'post_status'    => 'publish'
                );
                $courses_query = new WP_Query( $args );

                if ( $courses_query->have_posts() ) :
                    while ( $courses_query->have_posts() ) : $courses_query->the_post();
                        $price = get_post_meta( get_the_ID(), 'price', true );
                        $price = $price ? '$' . $price : 'FREE';
                        ?>
                        <article class="course-card">
                            <div class="course-image">
                                <?php if ( has_post_thumbnail() ) : ?>
                                    <?php the_post_thumbnail( 'medium' ); ?>
                                <?php else : ?>
                                    <div class="placeholder-img">🎓</div>
                                <?php endif; ?>
                            </div>
                            <div class="course-content">
                                <span class="course-badge">LMS COURSE</span>
                                <h3 class="course-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                                <div class="course-meta">
                                    <span>👤 By Instructor</span>
                                    <span class="price"><?php echo esc_html( $price ); ?></span>
                                </div>
                                <a href="<?php the_permalink(); ?>" class="btn-enroll">ចូលរៀនឥឡូវនេះ</a>
                            </div>
                        </article>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                else :
                    // Fallback placeholders if no real courses exist yet
                    $courses_archive = get_post_type_archive_link( 'stm-courses' );
                    $courses_link = $courses_archive ? $courses_archive : home_url( '/courses/' );
                    for ($i = 1; $i <= 3; $i++) :
                        ?>
                        <article class="course-card placeholder">
                            <div class="course-image">
                                <div class="placeholder-img">📚</div>
                            </div>
                            <div class="course-content">
                                <span class="course-badge">FEATURED</span>
                                <h3 class="course-title"><a href="<?php echo esc_url( $courses_link ); ?>">វគ្គសិក្សាគំរូទី <?php echo $i; ?> (ចំណងជើងវគ្គសិក្សា)</a></h3>
                                <div class="course-meta">
                                    <span>👤 ReanDaily Teacher</span>
                                    <span class="price">$19.99</span>
                                </div>
                                <a href="<?php echo esc_url( $courses_link ); ?>" class="btn-enroll">ចូលរៀនឥឡូវនេះ</a>
                            </div>
                        </article>
                        <?php
                    endfor;
                endif;
                ?>
            </div>
        </div>
    </section>

    <!-- Why Choose Us -->
    <section id="features" class="features-section">
        <div class="container">
            <div class="section-title">
                <h2>ហេតុអ្វីជ្រើសរើសរៀនជាមួយ ReanDaily?</h2>
                <p>យើងផ្តល់ជូននូវបទពិសោធន៍សិក្សាដ៏ល្អបំផុតសម្រាប់សិស្សានុសិស្សគ្រប់រូប</p>
            </div>
            <div class="features-grid">
                <div class="feature-item">
                    <div class="feat-icon">⏳</div>
                    <h4>រៀនគ្មានដែនកំណត់</h4>
                    <p>ចុះឈ្មោះម្តង សិក្សាបានពេញមួយជីវិត តាមពេលវេលាងាយស្រួលផ្ទាល់ខ្លួនរបស់អ្នក。</p>
                </div>
                <div class="feature-item">
                    <div class="feat-icon">👨‍🏫</div>
                    <h4>គ្រូជំនាញពិតប្រាកដ</h4>
                    <p>រៀនសូត្រពីគ្រូឧទ្ទេសដែលមានបទពិសោធន៍ការងារ និងជំនាញជាក់ស្តែងក្នុងវិស័យនីមួយៗ。</p>
                </div>
                <div class="feature-item">
                    <div class="feat-icon">🎓</div>
                    <h4>វិញ្ញាបនបត្របញ្ជាក់ការសិក្សា</h4>
                    <p>ទទួលបានវិញ្ញាបនបត្រផ្លូវការដែលទទួលស្គាល់ ដើម្បីបង្ហាញលើប្រវត្តិរូបការងាររបស់អ្នក。</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Free Trial/Free Learning Promotion Section -->
    <section class="free-learning-section">
        <div class="container">
            <div class="free-learning-card">
                <div class="free-learning-content">
                    <span class="free-badge-glow">🎁 ការរៀនសូត្រឥតគិតថ្លៃ (Free Courses)</span>
                    <h2>សាកល្បងរៀនសូត្រដោយសេរី និងគ្មានការចំណាយ</h2>
                    <p>ចាប់ផ្តើមស្វែងយល់ពីមូលដ្ឋានគ្រឹះនៃជំនាញដែលអ្នកចង់បាន។ ចុះឈ្មោះចូលរៀនវគ្គសិក្សាឥតគិតថ្លៃ (Free Course) ភ្លាមៗ គ្មានតម្រូវការកាតធនាគារ ឬការទូទាត់ប្រាក់ឡើយ។ បង្កើតគណនី និងចូលសិក្សាបានភ្លាមៗ!</p>
                    <div class="free-learning-ctas">
                        <?php 
                        $courses_archive = get_post_type_archive_link( 'stm-courses' );
                        $cta_url = $courses_archive ? $courses_archive : home_url( '/all-courses/' );
                        $free_catalog_url = add_query_arg( 'price', 'free', $cta_url );
                        ?>
                        <a href="<?php echo esc_url( $free_catalog_url ); ?>" class="btn-free-start">ចាប់ផ្តើមរៀនឥតគិតថ្លៃឥឡូវនេះ</a>
                    </div>
                </div>
                <div class="free-learning-visual">
                    <div class="gift-box-wrapper">
                        <div class="gift-icon">🎓</div>
                        <div class="gift-glow"></div>
                        <div class="features-mini-list">
                            <div class="mini-feat">✓ គ្មានការទាមទារព័ត៌មានទូទាត់ប្រាក់</div>
                            <div class="mini-feat">✓ ចូលរៀនបានភ្លាមៗបន្ទាប់ពីចុះឈ្មោះ</div>
                            <div class="mini-feat">✓ រៀនសាកល្បងមុនសម្រេចចិត្តជាវវគ្គសិក្សាធំៗ</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-wrapper">
                <h2>ត្រៀមខ្លួនរួចរាល់ក្នុងការចាប់ផ្តើមហើយឬនៅ?</h2>
                <p>ចូលរួមជាមួយសិស្សរាប់ពាន់នាក់ដែលកំពុងអភិវឌ្ឍជំនាញ និងផ្លាស់ប្តូរអនាគតរបស់ពួកគេនៅថ្ងៃនេះ!</p>
                <?php 
                $courses_archive = get_post_type_archive_link( 'stm-courses' );
                $cta_url = $courses_archive ? $courses_archive : home_url( '/all-courses/' );
                ?>
                <a href="<?php echo esc_url( $cta_url ); ?>" class="btn btn-cta">រុករកវគ្គសិក្សាទាំងអស់</a>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>
