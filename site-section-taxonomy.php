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
        'add_new_item'      => __( 'Add New Site Section' ),
        'new_item_name'     => __( 'New Site Section Name' ),
        'menu_name'         => __( 'Site Section' ),
    );

    $args = array(
        'hierarchical'      => true,     // behaves like categories
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite' => array(
            'slug' => 'section',
        ),
        'meta_box_cb'       => false, // for custom meta box usage
    );

    register_taxonomy( 'section', 'page', $args );
}
add_action( 'init', 'register_section_taxonomy_for_pages' );

function meta_box_section(){
    add_meta_box(
        'section_dropdown_mb', 
        'Site Section', 
        'render_section_dropdown_box', 
        'page', 
        'side', 
        'core'
    );
}
add_action('add_meta_boxes', 'meta_box_section');

function render_section_dropdown_box($post){
    $terms = get_the_terms( $post->ID, 'section' );
    $current = ( !empty($terms) && !is_wp_error($terms) ) ? $terms[0]->term_id : 0;
    
    
    wp_dropdown_categories(array(
        'taxonomy' => 'section',
        'name' => 'section_plugin_field',
        'selected' => $current,
        'show_option_none' => 'Select Section',
        'hide_empty' => 0,
        'hierarchical' => 1,
        'class' => 'widefat',
    ));
}

/**
 * Add filter dropdown in Pages admin list
 */
function filter_pages_by_section( $post_type, $which ) {
    
    if ( 'page' !== $post_type ) {
        return;
    }

    $taxonomy = 'section';
    $taxonomy_obj = get_taxonomy( $taxonomy );
    wp_dropdown_categories( array(
        'show_option_all' => sprintf( __( 'All %s' ), $taxonomy_obj->label ),
        'taxonomy'        => $taxonomy,
        'name'            => $taxonomy,
        'orderby'         => 'name',
        'selected'        => isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '',
        'hierarchical'    => true,
        'depth'           => 4,
        'show_count'      => true,
        'hide_empty'      => false,
    ) );
}
add_action( 'restrict_manage_posts', 'filter_pages_by_section', 10, 2 );

function quick_edit_section_dropdown( $column_name, $post_type ) {
    if ( 'section' !== $column_name || 'page' !== $post_type ) {
        return;
    }
    ?>
    <fieldset class="inline-edit-col-right">
        <div class="inline-edit-col">
            <label>
                <span class="title">Site Section</span>
                <?php
                wp_dropdown_categories( array(
                    'taxonomy'         => 'section',
                    'name'             => 'section_plugin_field',
                    'show_option_none' => __( '— No Section —' ),
                    'hide_empty'       => 0,
                    'hierarchical'     => 1,
                    'class'            => 'widefat',
                ) );
                ?>
            </label>
        </div>
    </fieldset>
    <?php
}
add_action( 'quick_edit_custom_box', 'quick_edit_section_dropdown', 10, 2 );

/**
 * Apply taxonomy filter to Pages query
 */
function apply_section_filter_to_query( $query ) {
    global $pagenow;

    if ( 'edit.php' === $pagenow && isset($_GET['section']) && $_GET['section'] != '' ) {
        $query->query_vars['tax_query'] = array(
            array(
                'taxonomy' => 'section',
                'field'    => 'slug',
                'terms'    => $_GET['section'],
            )
        );
    }
}
add_filter( 'parse_query', 'apply_section_filter_to_query' );

/**
 * Show Section taxonomy column in Pages list
 */
function add_section_column_to_pages( $columns ) {
    $columns['section'] = 'Site Section';
    return $columns;
}
add_filter( 'manage_page_posts_columns', 'add_section_column_to_pages' );

function show_section_column_value( $column, $post_id ) {
    if ( $column == 'section' ) {
        $terms = get_the_terms( $post_id, 'section' );
        $term_id = 0;
        
        if ( !empty($terms) && !is_wp_error($terms) ) {
            foreach ( $terms as $term ) {
                echo esc_html( $term->name ) . '<br>';
                $term_id = $term->term_id; 
            }
        } else {
            echo '—';
        }
        echo '<div class="section_hidden_id" data-val="' . esc_attr($term_id) . '" style="display:none;"></div>';
    }
}
add_action( 'manage_page_posts_custom_column', 'show_section_column_value', 10, 2 );
//Didn't know how to do the following parts correctly, so I asked AI to help me so the code actually functions properly
function save_section_plugin_data( $post_id ) {
    if ( ! isset( $_POST['section_nonce'] ) && ! isset( $_POST['_inline_edit'] ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_page', $post_id ) ) return;

    if ( isset( $_POST['section_plugin_field'] ) ) {
        $term_id = intval( $_POST['section_plugin_field'] );
        if ( $term_id > 0 ) {
            wp_set_object_terms( $post_id, $term_id, 'section' );
        } else {
            wp_set_object_terms( $post_id, array(), 'section' );
        }
    }
}
add_action( 'save_post', 'save_section_plugin_data' );

function section_quick_edit_javascript() {
    global $pagenow;
    if ( $pagenow !== 'edit.php' || ( isset($_GET['post_type']) && $_GET['post_type'] !== 'page' ) ) return;
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('.editinline').on('click', function() {
            var post_id = $(this).closest('tr').attr('id').replace('post-', '');
            var term_id = $('#post-' + post_id + ' .section_hidden_id').data('val');
            
            // Set the value in the Quick Edit box
            setTimeout(function() {
                var $edit_row = $('#edit-' + post_id);
                $edit_row.find('select[name="section_plugin_field"]').val(term_id);
            }, 50);
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'section_quick_edit_javascript' );