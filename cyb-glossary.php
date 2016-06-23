<?php

/**
 * Plugin Name: CYB Glossary
 * Plugin URI:  http://cybmeta.com/como-crear-un-glosario-o-listado-alfabetico-de-posts/
 * Description: WP plugin to build a alphabet Glossary
 * Author:      @CybMeta
 * Author URI:  http://cybmeta.com
 * Version:     0.1
 * Text Domain: fx
 */
 
if ( !defined( 'ABSPATH' ) ) exit;

add_action('plugins_loaded', 'cybglossary_loaded');
function cybglossary_loaded() {
	load_plugin_textdomain( 'cybglossary', false, basename( dirname( __FILE__ ) ) . '/languages/' ); 
}

add_action( 'init', 'cybglossary_init' );
function cybglossary_init() {
     
    $labels = array(
        'name'               => 'Diccionario Forex',
        'singular_name'      => 'Término',
        'add_new_item'       => 'Añadir nuevo término',
        'new_item'           => 'Nuevo término',
        'edit_item'          => 'Editar término',
        'view_item'          => 'Ver término',
        'all_items'          => 'Todos los términos',
        'search_items'       => 'Buscar en el diccionario',
        'not_found'          => 'No se han encontrado nigún término con esos criterios.',
        'not_found_in_trash' => 'No hay términos en la papelera'
    );
 
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'rewrite'            => array( 'slug' => 'diccionario' ),
        'capability_type'    => 'page',
        'has_archive'        => true,
        'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' )
    );
 
    register_post_type( 'term', $args );
    
    if( ! taxonomy_exists( 'glossary' ) ) {
	$args = array(
		'show_ui'    => false,
		'rewrite'    => array( 'slug' => 'glosario' )
     	);
     	register_taxonomy( 'glossary', array('term'), $args );
     
    }
}

add_action('save_post','cybglossary_set_first_letter');
function cybglossary_set_first_letter( $post_id ){
 
    // skip autosave
    if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
         return;
    }
 
    // limit to term post type
    if( isset($_POST['post_type']) && 'term' != $_POST['post_type'] ) {
         return;
    }
    
     // Check permissions
    if( !current_user_can('edit_post', $post_id ) ) {
        return;
    }
 
    //Assign first letter of the title as glossary term
    if( isset($_POST['post_title']) ) {
        //TODO: skip no-sense words, characters, etc
    	$glossary_term = mb_strtolower( mb_substr( $_POST['post_title'], 0, 1 ) );
    	wp_set_post_terms(
		$post_id,
		$glossary_term,
		'glossary'
        );
    }
 
    //delete the transient that is storing the alphabet letters
    delete_transient('cybglossary_archive_alphabet');
 
}

// Set ascendent order by name in "terms" post type and glossary taxomony archives
add_action( 'pre_get_posts', function( $query ) {

  if( ! $query->is_admin && $query->is_main_query() && ( $query->is_tax( 'glossary' ) || $query->is_post_type_archive( 'term' ) ) ) {
  	$query->set( 'order', 'ASC' );
  	$query->set( 'orderby', 'title' );
  }
  
} );

function cybglossary_the_alphabet_select_menu() {
     $taxonomy ='glossary';
     // save the terms that have posts in an array as a transient
     if( false === ( $alphabet = get_transient('cyb_archive_alphabet') ) ) {
          // It wasn't there, so regenerate the data and save the transient
          $terms = get_terms($taxonomy);
          $alphabet = array();
          if($terms){
              foreach($terms as $term){
                  $alphabet[]= $term->slug;
              }
          }
          set_transient('cybglossary_archive_alphabet', $alphabet );
     }?>
     <select id="alphabet-menu" onchange="window.location.href=this.value">
         <?php
         foreach(range('a','z') as $i) {
 
             $current = ($i == get_query_var($taxonomy)) ? "current-menu-item" : "menu-item";
             
             $link = get_term_link( $i, $taxonomy );
             
             if( ! is_wp_error( $link ) ) {
             
             	echo '<option value="'.esc_url( $link ).'" '.selected( $i, get_query_var($taxonomy) ).'>'.$i.'</option>';
             
             }
 
          }
          ?>
     </select>
<?php
}

function cybglossary_the_alphabet_menu() {
     $taxonomy ='glossary';
     // save the terms that have posts in an array as a transient
     if( false === ( $alphabet = get_transient('cyb_archive_alphabet') ) ) {
          // It wasn't there, so regenerate the data and save the transient
          $terms = get_terms($taxonomy);
          $alphabet = array();
          if($terms){
              foreach($terms as $term){
                  $alphabet[]= $term->slug;
              }
          }
          set_transient('cybglossary_archive_alphabet', $alphabet );
     }?>
     <ul id="alphabet-menu">
         <?php
         foreach(range('a','z') as $i) {
 
             $current = ($i == get_query_var($taxonomy)) ? "current-menu-item" : "menu-item";
             if(in_array( $i, $alphabet )) {
                 printf('<li class="az-char %s"><a href="%s">%s</a></li>', $current, get_term_link( $i, $taxonomy ), $i );
             } else {
                 printf('<li class="az-char %s">%s</li>', $current, $i );
             }
 
          }
          ?>
     </ul>
<?php
}


// Get next/prev post sorted by title
// See http://wordpress.stackexchange.com/questions/166932/how-to-get-next-and-previous-post-links-alphabetically-by-title-across-post-ty
add_filter('get_next_post_sort', 'filter_next_post_sort');
function filter_next_post_sort($sort) {

    if ( !is_main_query() || !is_singular('term') ) {
    
        return $sort;
        
    }

    $sort = "ORDER BY p.post_title ASC LIMIT 1";

    return $sort;
    
}
add_filter('get_next_post_where',  'filter_next_post_where');
function filter_next_post_where($where) {

    global $wpdb;

    if ( !is_main_query() || !is_singular('term') ) {
    
      return $where;
      
    }
    
    $the_post = get_post( get_the_ID() );

    $where = $wpdb->prepare("WHERE p.post_title > '%s' AND p.post_type = '". $the_post->post_type ."' AND p.post_status = 'publish'",$the_post->post_title);

    return $where;
}

add_filter('get_previous_post_sort',  'filter_previous_post_sort');
function filter_previous_post_sort($sort) {

    if ( !is_main_query() || !is_singular('term') ) {
    
        return $sort;
        
    }

    $sort = "ORDER BY p.post_title DESC LIMIT 1";

    return $sort;
    
}
add_filter('get_previous_post_where', 'filter_previous_post_where');
function filter_previous_post_where($where) {

    global $wpdb;

    if ( !is_main_query() || !is_singular('term') ) {
    
      return $where;
      
    }
    
    $the_post = get_post( get_the_ID() );
    
    $where = $wpdb->prepare("WHERE p.post_title < '%s' AND p.post_type = '". $the_post->post_type ."' AND p.post_status = 'publish'",$the_post->post_title);

    return $where;
    
}

register_activation_hook( __FILE__, 'cybglossary_activation_hook' );
function cybglossary_activation_hook() {
    // First, we "add" the custom post type via the above written function.
    cybglossary_init();
    
    // You should *NEVER EVER* do this on every page load!!
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'cybglossary_deactivation_hook' );
function cybglossary_deactivation_hook() {
    delete_transient('cybglossary_archive_alphabet');
    flush_rewrite_rules();
}

?>