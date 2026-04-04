<?php
/*
Template Name: Tarot Reader
*/

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        ?>
        <div class="tarot-page-wrapper">
            <div class="container">
                <h1><?php the_title(); ?></h1>
                <div class="content">
                    <?php the_content(); ?>
                </div>

                <!-- Tarot Reader Shortcode -->
                <?php echo do_shortcode('[tarot_reader]'); ?>
            </div>
        </div>
        <?php
    }
}

get_footer();