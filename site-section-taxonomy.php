<?php
/**
 * Plugin Name: Site Section Taxonomy
 * Description: Adds "Site Section" taxonomy to pages for Salibandy and Inssi-Divari sections.
 * Version: 1.0
 * Author: Your Name
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Register Custom Taxonomy "Section" for Pages
 */
function register_section_taxonomy_for_pages() {

    $labels = array(
        'name'              => _x( 'Site Sections', 'taxonomy general name' ),
        'singular_name'     => _x( 'Site Section', 'taxonomy singular name' ),
        'search_items'      => __( 'Search Site Sections' ),
        'all_items'         => __( 'All Site Sections' ),
        'edit_item'         => __( 'Edit Site Section' ),
        'update_item'       => __( 'Update Site Section' ),
        'add_new_item'      => null, 
        'new_item_name'     => __( 'New Site Section Name' ),
        'menu_name'         => __( 'Site Section' ),
    );

    $args = array(
        'hierarchical'      => false,
        'labels'            => $labels,
        'show_ui'           => true,     
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array(
            'slug' => 'section',
        ),
        'show_in_rest'      => true,
        'meta_box_cb'       => false,
        'show_in_quick_edit'=> false,
        'show_in_menu'      => false, 
        'show_ui'      => false, 
   
    );

    register_taxonomy( 'section', 'page', $args );
}
add_action( 'init', 'register_section_taxonomy_for_pages' );



/**
 * Add filter dropdown in Pages admin list
 */
function filter_pages_by_section( $post_type ) {
    
    if ( 'page' !== $post_type ) {
        return;
    }

    $selected = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : '';

    wp_dropdown_categories( array(
        'show_option_all' => 'All Site Sections',
        'taxonomy'        => 'section',
        'name'            => 'section',
        'orderby'         => 'name',
        'selected'        => $selected,
        'hierarchical'    => false,
        'show_count'      => true,
        'hide_empty'      => false,
        'value_field'     => 'slug',
    ) );
}
add_action( 'restrict_manage_posts', 'filter_pages_by_section' );

/**
 * Apply taxonomy filter to Pages query
 */
function apply_section_filter_to_query( $query ) {
    global $pagenow;

    if ( 'edit.php' === $pagenow && $query->is_main_query() && isset($_GET['post_type']) && $_GET['post_type'] === 'page' ) {
        
        if ( ! empty( $_GET['section'] ) ) {
            $query->set( 'tax_query', array(
                array(
                    'taxonomy' => 'section',
                    'field'    => 'slug',
                    'terms'    => sanitize_text_field( $_GET['section'] ),
                )
            ) );
        }
    }
}
add_action( 'pre_get_posts', 'apply_section_filter_to_query' );

/**
 * Show Section taxonomy column in Pages list
 */
function add_section_column_to_pages( $columns ) {
    $columns['section'] = 'Site Section';
    return $columns;
}
add_filter( 'manage_page_posts_columns', 'add_section_column_to_pages' );

/**
 * Display the assigned Site Section(s) in the Pages list table column.
 */
function show_section_column_value( $column, $post_id ) {
    if ( $column == 'section' ) {
        $terms = get_the_terms( $post_id, 'section' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            echo esc_html( $terms[0]->name );
        } else {
            echo '<span aria-hidden="true">—</span><span class="screen-reader-text">None</span>';
        }
    }
}
add_action( 'manage_page_posts_custom_column', 'show_section_column_value', 10, 2 );



/**
 * Remove the default taxonomy meta box
 */
function remove_default_section_meta_box() {
    remove_meta_box( 'sectiondiv', 'page', 'side' );
}
add_action( 'admin_menu', 'remove_default_section_meta_box' );

/**
 * Register a custom meta box for Site Section (radio buttons, single selection).
 */
function add_section_radio_meta_box() {
    add_meta_box(
        'section_radio_div',             
        __( 'Site Section' ),           
        'section_radio_meta_box_callback', 
        'page',                          
        'side',                          
        'high'                         
    );
}
add_action( 'add_meta_boxes', 'add_section_radio_meta_box' );

/**
 * Display the Site Section radio buttons in the meta box.
 *
 * @param WP_Post 
 */
function section_radio_meta_box_callback( $post ) {
    $terms = get_terms( array(
        'taxonomy'   => 'section',
        'hide_empty' => false,
        'orderby'    => 'name',
    ) );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        echo '<p>' . __( 'No Site Sections found.' ) . '</p>';
        return;
    }

    $current_terms = wp_get_post_terms( $post->ID, 'section', array( 'fields' => 'ids' ) );
    $selected = ! empty( $current_terms ) ? (int) $current_terms[0] : 0;

    wp_nonce_field( 'section_radio_save', 'section_radio_nonce' );

    echo '<div class="section-radio-options">';
    foreach ( $terms as $term ) {
        printf(
            '<label style="display:block; margin:6px 0; cursor:pointer;">' .
            '<input type="radio" name="section_radio" value="%d" %s> %s' .
            '</label>',
            esc_attr( $term->term_id ),
            checked( $selected, $term->term_id, false ),
            esc_html( $term->name )
        );
    }
    // "None" option
    printf(
        '<label style="display:block; margin:6px 0; color:#666; cursor:pointer;">' .
        '<input type="radio" name="section_radio" value="0" %s> None' .
        '</label>',
        checked( $selected, 0, false )
    );
    echo '</div>';
}

/**
 * Save the selected Site Section when the page is saved.
 * Enforces single-term assignment. Value "0" clears the section.
 * @param int 
 */
function save_section_radio_meta_box( $post_id ) {
    
    if ( ! isset( $_POST['section_radio_nonce'] ) ) {
        return;
    }

    $nonce = $_POST['section_radio_nonce'];

    if ( ! wp_verify_nonce( $nonce, 'section_radio_save' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_page', $post_id ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    $term_id = isset( $_POST['section_radio'] ) ? (int) $_POST['section_radio'] : 0;

    if ( $term_id > 0 ) {
        wp_set_post_terms( $post_id, array( $term_id ), 'section', false );
    } else {
        wp_set_post_terms( $post_id, array(), 'section', false ); // Remove all
    }
}
add_action( 'save_post_page', 'save_section_radio_meta_box' );

/**
 * Add custom radio buttons to the Quick Edit interface for Site Section.
 */
add_action( 'quick_edit_custom_box', function( $column_name, $post_type ) {
    if ( $column_name !== 'section' || $post_type !== 'page' ) {
        return;
    }

    $terms = get_terms( array(
        'taxonomy'   => 'section',
        'hide_empty' => false,
        'orderby'    => 'name',
    ) );

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return;
    }

    wp_nonce_field( 'section_quick_save', 'section_quick_nonce', false );
    ?>
    <fieldset class="inline-edit-col-left">
        <div class="inline-edit-col">
            <label>
                <span class="title">Site Section</span>
                <span class="input-text-wrap">
                    <?php foreach ( $terms as $term ) : ?>
                        <label style="display:block; margin:3px 0;">
                            <input type="radio" name="section_quick" value="<?php echo esc_attr( $term->term_id ); ?>">
                            <?php echo esc_html( $term->name ); ?>
                        </label>
                    <?php endforeach; ?>
                    <label style="display:block; margin:3px 0; color:#666;">
                        <input type="radio" name="section_quick" value="0">
                        None
                    </label>
                </span>
            </label>
        </div>
    </fieldset>
    <?php
}, 10, 2 );

/**
 * Add term ID as a data attribute to the column for JS access in Quick Edit.
 */
add_action( 'manage_page_posts_custom_column', function( $column, $post_id ) {
    if ( 'section' === $column ) {
        $terms = get_the_terms( $post_id, 'section' );
        $term_id = $terms && ! is_wp_error( $terms ) ? (int) $terms[0]->term_id : 0;
        echo ' data-term-id="' . esc_attr( $term_id ) . '"';
    }
}, 5, 2 );

/**
 * Enqueue JavaScript to populate and save Quick Edit values.
 */
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || 'edit-page' !== $screen->id ) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        $('#new-tag-section').closest('.taxonomy-field-container').hide();
        $('#new-tag-section').closest('.components-panel__body').find('.editor-post-taxonomies__hierarchical-terms-add-new').hide(); // Hide if using Gutenberg and default meta box still showing up
        
        
        $(document).on('click', '.editinline', function() {
            var $row = $(this).closest('tr');
            var termId = $row.find('.column-section').data('term-id') || 0;
            setTimeout(function() {
                $('input[name="section_quick"][value="' + termId + '"]').prop('checked', true);
            }, 50);
        });

        var originalSave = window.inlineEditPost && window.inlineEditPost.save;
        if (originalSave) {
            window.inlineEditPost.save = function(id) {
                var val = $('input[name="section_quick"]:checked').val();
                if (typeof val !== 'undefined') {
                    var nonce = $('#section_quick_nonce').val() || $('#inline-edit #section_quick_nonce').val();
                    $.post(ajaxurl, {
                        action: 'save_section_quick',
                        post_id: id,
                        term_id: val,
                        nonce: nonce
                    });
                }
                return originalSave.apply(this, arguments);
            };
        }
    });
    </script>
    <?php
} );

/**
 * AJAX handler to save Site Section from Quick Edit.
 */
function ajax_save_section_quick() {
    check_ajax_referer( 'section_quick_save', 'nonce' );

    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    $term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;

    if ( ! $post_id || ! current_user_can( 'edit_page', $post_id ) ) {
        wp_die( -1 );
    }

    if ( $term_id > 0 ) {
        wp_set_object_terms( $post_id, array( $term_id ), 'section', false );
    } else {
        wp_set_object_terms( $post_id, array(), 'section', false );
    }

    wp_die();
}
add_action( 'wp_ajax_save_section_quick', 'ajax_save_section_quick' );