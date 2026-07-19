<?php
/**
 * Template Name: Courses Archive Page Template
 * 
 * This custom-coded template overrides standard catalog pages to render
 * a lightweight, high-performance courses directory using optimized WP_Query loop.
 */
get_header(); 

// 1. Setup Query Parameters
$paged = 1;
if ( isset( $_GET['paged'] ) ) {
    $paged = max( 1, intval( $_GET['paged'] ) );
} else {
    $request_path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
    if ( preg_match( '#/page/(\d+)/?$#', '/' . $request_path, $page_matches ) ) {
        $paged = max( 1, intval( $page_matches[1] ) );
    } elseif ( get_query_var( 'paged' ) ) {
        $paged = get_query_var( 'paged' );
    } elseif ( get_query_var( 'page' ) ) {
        $paged = get_query_var( 'page' );
    }
}
$category_filter = isset( $_GET['category'] ) ? sanitize_text_field( $_GET['category'] ) : '';

$args = array(
    'post_type'      => 'stm-courses',
    'posts_per_page' => 9,
    'paged'          => $paged,
    'post_status'    => 'publish',
);

// Filter by taxonomy if selected
if ( ! empty( $category_filter ) ) {
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'stm_lms_course_taxonomy',
            'field'    => 'slug',
            'terms'    => $category_filter,
        ),
    );
}

$courses_query = new WP_Query( $args );
?>

<!-- Coded Catalog Banner -->
<section class="archive-banner">
    <div class="container">
        <span class="banner-badge">📚 វគ្គសិក្សារបស់យើង</span>
        <h1>វគ្គសិក្សាជំនាញទាំងអស់</h1>
        <p>ជ្រើសរើសវគ្គសិក្សាដែលស័ក្តិសមសម្រាប់អ្នក ដើម្បីអភិវឌ្ឍសមត្ថភាព និងបង្កើនឱកាសការងារ</p>
    </div>
</section>

<!-- High Performance Catalog Wrapper -->
<div class="courses-archive-page" style="background: #f8fafc; padding: 60px 0 100px 0;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
        <div class="courses-catalog-layout" style="display: grid; grid-template-columns: 280px 1fr; gap: 40px; align-items: start;">
            
            <!-- Left Sidebar Filters -->
            <aside class="catalog-sidebar" style="background: #ffffff; padding: 25px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.02); position: sticky; top: 120px;">
                <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin: 0 0 20px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-family: 'Kantumruy Pro', sans-serif !important;">ប្រភេទវគ្គសិក្សា</h3>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px;">
                    <?php
                    // Get all course category taxonomy terms
                    $categories = get_terms( array(
                        'taxonomy'   => 'stm_lms_course_taxonomy',
                        'hide_empty' => true,
                    ) );
                    
                    $all_active = empty($category_filter) ? 'color: #007bff; font-weight: 700;' : 'color: #475569;';
                    echo '<li><a href="' . esc_url( remove_query_arg('category') ) . '" style="' . $all_active . ' text-decoration: none; font-size: 15px; font-family: \'Kantumruy Pro\', sans-serif !important; display: block; padding: 6px 0; transition: color 0.2s ease;">📚 វគ្គសិក្សាទាំងអស់</a></li>';
                    
                    if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
                        foreach ( $categories as $cat ) {
                            $active = ($category_filter === $cat->slug) ? 'color: #007bff; font-weight: 700;' : 'color: #475569;';
                            $link = add_query_arg( 'category', $cat->slug );
                            echo '<li><a href="' . esc_url( $link ) . '" style="' . $active . ' text-decoration: none; font-size: 15px; font-family: \'Kantumruy Pro\', sans-serif !important; display: block; padding: 6px 0; transition: color 0.2s ease;">🏷️ ' . esc_html( $cat->name ) . ' <span style="font-size: 12px; color: #94a3b8; font-weight: normal;">(' . $cat->count . ')</span></a></li>';
                        }
                    }
                    ?>
                </ul>
            </aside>
            
            <!-- Right Main Catalog Grid -->
            <div class="catalog-main">
                <div class="courses-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px;">
                    <?php
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
                        ?>
                        <div class="no-courses-found" style="grid-column: 1 / -1; text-align: center; padding: 80px 40px; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.02);">
                            <span class="no-courses-icon" style="font-size: 64px; display: block; margin-bottom: 20px;">🔍</span>
                            <h2 style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 10px; font-family: 'Kantumruy Pro', sans-serif !important;">រកមិនឃើញវគ្គសិក្សាឡើយ</h2>
                            <p style="color: #64748b; font-size: 16px; margin-bottom: 20px;">មិនទាន់មានវគ្គសិក្សាក្នុងប្រភេទនេះនៅឡើយទេ។ សូមជ្រើសរើសប្រភេទផ្សេងទៀត។</p>
                            <a href="<?php echo esc_url( remove_query_arg('category') ); ?>" class="btn btn-primary" style="margin-top: 15px; font-family: 'Kantumruy Pro', sans-serif !important;">មើលវគ្គសិក្សាទាំងអស់</a>
                        </div>
                        <?php
                    endif;
                    ?>
                </div>
                
                <!-- Custom Snappy Pagination -->
                <?php
                $total_pages = $courses_query->max_num_pages;
                if ( $total_pages > 1 ) {
                    $current_path = strtok( $_SERVER['REQUEST_URI'], '?' );
                    $base_url = home_url( $current_path );
                    $pagination_base = add_query_arg( 'paged', '%#%', $base_url );

                    echo '<div class="archive-pagination" style="margin-top: 50px; display: flex; justify-content: center; gap: 8px;">';
                    echo paginate_links( array(
                        'base'      => esc_url( $pagination_base ),
                        'format'    => '',
                        'current'   => max( 1, $paged ),
                        'total'     => $total_pages,
                        'prev_text' => '«',
                        'next_text' => '»',
                        'type'      => 'list',
                    ) );
                    echo '</div>';
                }
                ?>
            </div>
            
        </div>
    </div>
</div>

<!-- Mobile responsive layout overrides -->
<style>
@media (max-width: 991px) {
    .courses-catalog-layout {
        grid-template-columns: 1fr !important;
    }
    .catalog-sidebar {
        position: relative !important;
        top: 0 !important;
        margin-bottom: 30px;
    }
}
</style>

<?php get_footer(); ?>
