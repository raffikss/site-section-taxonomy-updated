<?php
/**
 * Plugin Name: Site Section Taxonomy
 * Description: Adds "Site Section" taxonomy to pages for Salibandy and Inssi-Divari sections.
 * Version: 1.0
 * Author: Rainers Reds Biezais
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'SITE_SECTION_TAXONOMY', 'section' );
define( 'SITE_SECTION_POST_TYPE', 'page' );

/**
 * Register Site Section taxonomy for Pages
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
        'hierarchical'          => true,
        'labels'                => $labels,
        'show_ui'               => true,
        'show_admin_column'     => true,
        'query_var'             => true,
        'update_count_callback' => 'update_section_term_count',
        'rewrite'               => array( 'slug' => 'section' ),
    );

    register_taxonomy( SITE_SECTION_TAXONOMY, SITE_SECTION_POST_TYPE, $args );
}
add_action( 'init', 'register_section_taxonomy_for_pages' );

/**
 * Get post statuses that appear on the Pages list
 *
 * @return array
 */
function section_get_admin_statuses() {
    $statuses = get_post_stati( array( 'show_in_admin_all_list' => true ) );
    return ! empty( $statuses ) ? $statuses : array( 'publish' );
}

/**
 * Get page counts for Site Section terms
 *
 * @param array $term_ids Optional. Array of term IDs to get counts for.
 * @param bool  $return_term_taxonomy Optional. Return term_taxonomy_id as key instead of term_id.
 * @return array
 */
function section_get_page_counts( $term_ids = array(), $return_term_taxonomy = false ) {
    global $wpdb;

    $statuses     = section_get_admin_statuses();
    $placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
    $select_id   = $return_term_taxonomy ? 'tt.term_taxonomy_id' : 'tt.term_id';
    $where_terms = '';
    $params      = array();

    if ( ! empty( $term_ids ) ) {
        $where_terms = 'AND tt.term_taxonomy_id IN ( ' . implode( ', ', array_fill( 0, count( $term_ids ), '%d' ) ) . ' )';
        $params      = $term_ids;
    }

    $sql = "
        SELECT {$select_id} AS term_key, COUNT( DISTINCT p.ID ) AS count
        FROM {$wpdb->term_relationships} tr
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
        WHERE tt.taxonomy = %s
          {$where_terms}
          AND p.post_type = %s
          AND p.post_status IN ( {$placeholders} )
        GROUP BY {$select_id}
    ";

    $prepared = $wpdb->prepare(
        $sql,
        array_merge( $params, array( SITE_SECTION_TAXONOMY, SITE_SECTION_POST_TYPE ), $statuses )
    );

    $results = $wpdb->get_results( $prepared, ARRAY_A );
    $counts  = array();

    if ( $results ) {
        foreach ( $results as $row ) {
            $counts[ (int) $row['term_key'] ] = (int) $row['count'];
        }
    }

    return $counts;
}

/**
 * Update Site Section term counts to include all admin-visible page statuses
 *
 * @param array  $terms
 * @param string $taxonomy
 */
function update_section_term_count( $terms, $taxonomy ) {
    if ( SITE_SECTION_TAXONOMY !== $taxonomy || empty( $terms ) ) {
        return;
    }

    $term_ids = wp_list_pluck( $terms, 'term_id' );
    $counts   = section_get_page_counts( $term_ids, true );

    if ( empty( $counts ) ) {
        return;
    }

    global $wpdb;
    foreach ( $counts as $term_taxonomy_id => $count ) {
        $wpdb->update(
            $wpdb->term_taxonomy,
            array( 'count' => $count ),
            array( 'term_taxonomy_id' => $term_taxonomy_id )
        );
    }
}

/**
 * Get cached page counts for Site Section taxonomy
 *
 * @return array
 */
function get_section_taxonomy_page_counts() {
    static $cached = null;
    if ( null === $cached ) {
        $cached = section_get_page_counts();
    }
    return $cached;
}

/**
 * Walker class for dropdown with accurate counts
 */
class Section_Taxonomy_Dropdown_Walker extends Walker_CategoryDropdown {
    protected $counts = array();

    public function __construct( $counts = array() ) {
        $this->counts = $counts;
    }

    public function start_el( &$output, $category, $depth = 0, $args = array(), $id = 0 ) {
        $category->count = isset( $this->counts[ $category->term_id ] ) ? (int) $this->counts[ $category->term_id ] : 0;
        parent::start_el( $output, $category, $depth, $args, $id );
    }
}

/**
 * Add Site Section meta box to Page editor
 */
function add_section_meta_box() {
    add_meta_box(
        'section-meta-box',
        __( 'Site Section', 'site-section-taxonomy' ),
        'render_section_meta_box',
        SITE_SECTION_POST_TYPE,
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'add_section_meta_box' );

/**
 * Render Site Section dropdown in meta box
 *
 * @param WP_Post $post
 */
function render_section_meta_box( $post ) {
    wp_nonce_field( 'save_section_meta_box', 'section_meta_box_nonce' );

    $terms   = wp_get_post_terms( $post->ID, SITE_SECTION_TAXONOMY, array( 'fields' => 'ids' ) );
    $selected = ! empty( $terms ) ? (int) $terms[0] : '';

    wp_dropdown_categories( array(
        'show_option_none' => __( '— No Site Section —', 'site-section-taxonomy' ),
        'taxonomy'         => SITE_SECTION_TAXONOMY,
        'name'             => 'section_taxonomy_term',
        'orderby'          => 'name',
        'selected'         => $selected,
        'hierarchical'     => true,
        'depth'            => 4,
        'hide_empty'       => false,
    ) );
}

/**
 * Save Site Section from meta box or quick edit
 *
 * @param int $post_id
 */
function save_section_meta_box_data( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! isset( $_POST['section_taxonomy_term'] ) ) {
        return;
    }

    $nonce = isset( $_POST['section_meta_box_nonce'] ) ? $_POST['section_meta_box_nonce'] : '';
    if ( ! $nonce && isset( $_POST['section_quick_edit_nonce'] ) ) {
        $nonce = $_POST['section_quick_edit_nonce'];
    }

    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'save_section_meta_box' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $term_id = intval( $_POST['section_taxonomy_term'] );
    if ( $term_id > 0 ) {
        wp_set_post_terms( $post_id, array( $term_id ), SITE_SECTION_TAXONOMY, false );
    } else {
        wp_set_post_terms( $post_id, array(), SITE_SECTION_TAXONOMY, false );
    }
}
add_action( 'save_post_page', 'save_section_meta_box_data' );

/**
 * Add filter dropdown on Pages list screen
 *
 * @param string $post_type
 * @param string $which
 */
function filter_pages_by_section( $post_type, $which ) {
    if ( SITE_SECTION_POST_TYPE !== $post_type ) {
        return;
    }

    $taxonomy     = SITE_SECTION_TAXONOMY;
    $selected     = isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
    $taxonomy_obj = get_taxonomy( $taxonomy );
    $counts       = get_section_taxonomy_page_counts();
    $walker       = new Section_Taxonomy_Dropdown_Walker( $counts );

    wp_dropdown_categories( array(
        'show_option_all' => sprintf( __( 'All %s' ), $taxonomy_obj->label ),
        'taxonomy'        => $taxonomy,
        'name'            => $taxonomy,
        'orderby'         => 'name',
        'selected'        => $selected,
        'hierarchical'   => true,
        'depth'           => 4,
        'show_count'      => true,
        'hide_empty'      => false,
        'walker'          => $walker,
    ) );
}
add_action( 'restrict_manage_posts', 'filter_pages_by_section', 10, 2 );

/**
 * Apply section filter to admin query
 *
 * @param WP_Query $query
 */
function apply_section_filter_to_query( $query ) {
    global $pagenow;

    if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
        return;
    }

    $post_type = $query->get( 'post_type' );
    if ( empty( $post_type ) ) {
        $post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : 'post';
    }

    if ( is_array( $post_type ) ) {
        $post_type = reset( $post_type );
    }

    $raw_section = isset( $_GET['section'] ) ? wp_unslash( $_GET['section'] ) : '';
    if ( SITE_SECTION_POST_TYPE !== $post_type || '' === $raw_section ) {
        return;
    }

    $is_numeric = is_numeric( $raw_section );
    $term_value = $is_numeric ? absint( $raw_section ) : sanitize_title( $raw_section );

    if ( empty( $term_value ) ) {
        return;
    }

    $query->set( 'tax_query', array(
        array(
            'taxonomy' => SITE_SECTION_TAXONOMY,
            'field'    => $is_numeric ? 'term_id' : 'slug',
            'terms'    => array( $term_value ),
        ),
    ) );
    $query->set( 'section', null );
}
add_action( 'pre_get_posts', 'apply_section_filter_to_query' );

/**
 * Refresh Site Section counts when taxonomy admin is loaded
 */
function refresh_section_counts_on_admin_load() {
    global $pagenow;

    if ( 'edit-tags.php' !== $pagenow ) {
        return;
    }

    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
    if ( SITE_SECTION_TAXONOMY !== $taxonomy ) {
        return;
    }

    $counts = section_get_page_counts( array(), true );
    if ( empty( $counts ) ) {
        return;
    }

    global $wpdb;
    foreach ( $counts as $term_taxonomy_id => $count ) {
        $wpdb->update(
            $wpdb->term_taxonomy,
            array( 'count' => $count ),
            array( 'term_taxonomy_id' => $term_taxonomy_id )
        );
    }
}
add_action( 'load-edit-tags.php', 'refresh_section_counts_on_admin_load' );

/**
 * Add Site Section column to Pages list
 *
 * @param array $columns
 * @return array
 */
function add_section_column_to_pages( $columns ) {
    $columns['section'] = __( 'Site Section', 'site-section-taxonomy' );
    return $columns;
}
add_filter( 'manage_page_posts_columns', 'add_section_column_to_pages' );

/**
 * Render Site Section column content and inline data for quick edit
 *
 * @param string $column
 * @param int    $post_id
 */
function show_section_column_value( $column, $post_id ) {
    if ( 'section' !== $column ) {
        return;
    }

    $terms = get_the_terms( $post_id, SITE_SECTION_TAXONOMY );
    $term_id = '';

    if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
        $term_id = $terms[0]->term_id;
        foreach ( $terms as $term ) {
            echo esc_html( $term->name ) . '<br>';
        }
    }

    //Added standard inline edit data wrapper so WP copies it to quick edit form
    printf(
        '<div class="hidden" id="inline_%d"><div class="section_term_id">%s</div></div>',
        (int) $post_id,
        esc_html( $term_id )
    );
}
add_action( 'manage_page_posts_custom_column', 'show_section_column_value', 10, 2 );

/**
 * Hide default taxonomy checkboxes in Quick Edit
 *
 * @param bool   $show_in_quick_edit
 * @param string $taxonomy_name
 * @param string $post_type
 * @return bool
 */
function hide_section_taxonomy_in_quick_edit( $show_in_quick_edit, $taxonomy_name, $post_type ) {
    if ( SITE_SECTION_TAXONOMY === $taxonomy_name && SITE_SECTION_POST_TYPE === $post_type ) {
        return false;
    }
    return $show_in_quick_edit;
}
add_filter( 'quick_edit_show_taxonomy', 'hide_section_taxonomy_in_quick_edit', 10, 3 );

/**
 * Add Site Section dropdown to Quick Edit form
 *
 * @param string $column_name
 * @param string $post_type
 */
function add_section_quick_edit_field( $column_name, $post_type ) {
    if ( 'section' !== $column_name || SITE_SECTION_POST_TYPE !== $post_type ) {
        return;
    }

    wp_nonce_field( 'save_section_meta_box', 'section_quick_edit_nonce' );
    ?>
    <label>
        <span class="title"><?php esc_html_e( 'Site Section', 'site-section-taxonomy' ); ?></span>
        <?php
        wp_dropdown_categories( array(
            'show_option_none' => __( '— No Site Section —', 'site-section-taxonomy' ),
            'taxonomy'         => SITE_SECTION_TAXONOMY,
            'name'             => 'section_taxonomy_term',
            'id'               => 'section_taxonomy_term_quick_edit',
            'orderby'          => 'name',
            'hierarchical'     => true,
            'depth'            => 4,
            'hide_empty'       => false,
        ) );
        ?>
    </label>
    <?php
}
add_action( 'quick_edit_custom_box', 'add_section_quick_edit_field', 10, 2 );

/**
 * Add JavaScript to prefill Quick Edit dropdown with current Site Section
 */
function section_quick_edit_inline_js() {
    $screen = get_current_screen();
    if ( ! $screen || SITE_SECTION_POST_TYPE !== $screen->post_type || 'edit-page' !== $screen->id ) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        var $wp_inline_edit = inlineEditPost.edit;
        inlineEditPost.edit = function(id) {
            $wp_inline_edit.apply(this, arguments);

            var postId = typeof(id) === 'object' ? parseInt(this.getId(id), 10) : parseInt(id, 10);
            if (postId <= 0) return;

            //added wait for the quick edit fields to be inserted
            setTimeout(function() {
                var $sectionSelect = $('#section_taxonomy_term_quick_edit');
                if (!$sectionSelect.length) return;

                // Get data from the hidden inline block in the original row
                var $origRow = $('#post-' + postId);
                if (!$origRow.length) return;

                var termId = $origRow.find('#inline_' + postId + ' .section_term_id').text().trim() || '';
                termId = (termId && parseInt(termId) > 0) ? termId : '';
                $sectionSelect.val(termId).trigger('change'); 
            }, 50);
        };
    });
    </script>
    <?php
}
add_action( 'admin_print_footer_scripts-edit.php', 'section_quick_edit_inline_js' );