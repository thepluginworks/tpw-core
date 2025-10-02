<?php
/**
 * Single template for TPW Notice
 */
if (!defined('ABSPATH')) { exit; }
get_header();
?>
<main id="primary" class="site-main tpw-notice-single">
  <?php while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <?php if ( has_post_thumbnail() ) : ?>
        <div class="tpw-notice-hero">
          <?php the_post_thumbnail('large'); ?>
        </div>
      <?php endif; ?>

      <header class="entry-header">
        <h1 class="entry-title"><?php echo esc_html( get_the_title() ); ?></h1>
        <?php
          $terms = get_the_terms(get_the_ID(), 'tpw_notice_category');
          if ($terms && !is_wp_error($terms)) {
            echo '<div class="tpw-notice-meta">' . esc_html($terms[0]->name) . '</div>';
          }
        ?>
      </header>

      <div class="entry-content">
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; ?>
</main>
<?php get_footer(); ?>
