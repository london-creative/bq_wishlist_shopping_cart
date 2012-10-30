<?php 

/*
	Plugin name: BQ Wishlist / Shopping cart
	Description: Handles wishlist and shopping cart for both logged in and anonymous users using AJAX.
	Author name: Marian Cerny - London Creative

*/

class bq_wishlist_shopping_cart
{


// *******************************************************************
// ------------------------------------------------------------------
//					VARIABLES AND CONSTRUCTOR
// ------------------------------------------------------------------
// *******************************************************************


private $s_cart_table_name;
private $s_wishlist_table_name;
private $s_cart_cookie_name;
private $s_wishlist_cookie_name;

private $a_default_cart_texts;
private $a_default_wishlist_texts;

public function __construct()
{
	// ACTIONS - ENQUEUE SCRIPTS
	add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
	// ACTIONS - AJAX BUTTON CLICK
	add_action('wp_ajax_button_click', array( $this, 'handle_click' ) );
	add_action('wp_ajax_nopriv_button_click', array( $this, 'handle_click' ) );
	
	// INITIALIZE VARIABLES
	global $wpdb;
	$this->s_cart_table_name = $wpdb->prefix . "bq_shopping_cart";
	$this->s_wishlist_table_name = $wpdb->prefix . "bq_wishlist";
	
	$this->s_cart_cookie_name = "bq_watches_shopping_cart";
	$this->s_wishlist_cookie_name = "bq_watches_wishlist";
	
	$this->a_default_wishlist_texts = array(
		'add' => 'Add to wishlist',
		'remove' => 'Remove from wishlist'
	);
	$this->a_default_cart_texts = array(
		'add' => 'Add to cart',
		'remove' => 'Remove from cart'
	);
	
	// CREATE CART QUERY
	$s_cart_sql = "CREATE TABLE $this->s_cart_table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
		wID int(11) NOT NULL,
		uID int(11) NOT NULL,
		UNIQUE KEY id (id)
	);";

	// CREATE WISHLIST QUERY
	$s_wishlist_sql = "CREATE TABLE $this->s_wishlist_table_name (
		id int(11) NOT NULL AUTO_INCREMENT,
		wID int(11) NOT NULL,
		uID int(11) NOT NULL,
		UNIQUE KEY id (id)
	);";

	// CREATE DATABASE TABLES IF DON'T EXIST 
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($s_cart_sql);
	dbDelta($s_wishlist_sql);
}


// *******************************************************************
// ------------------------------------------------------------------
//					FRONT-END FUNCTIONS
// ------------------------------------------------------------------
// *******************************************************************


public function cart_link( $i_item_id )
{
	echo $this->build_link( 'cart', $i_item_id );
}


public function wishlist_link( $i_item_id )
{
	echo $this->build_link( 'wishlist', $i_item_id );
}


public function handle_click()
{
	// GET POST VARIABLES
	$i_item_id = $_POST['wid'];
	$s_table = $_POST['table'];
	// CALL APPROPRIATE METHOD 
	if ( $this->is_added( $s_table, $i_item_id ) )
	{	
		if ( is_user_logged_in() )		
			$this->remove_from_db( $s_table, $i_item_id );	
		else 
			$this->remove_from_cookie( $s_table, $i_item_id );
	} else
	{ 
		if ( is_user_logged_in() )
			$this->add_to_db( $s_table, $i_item_id );
		else 
			$this->add_to_cookie( $s_table, $i_item_id );
	}
	
	exit;
}


public function get_cart_count()
{
	if ( is_user_logged_in() )		
		return $this->get_count_from_db( 'cart' );	
	else 
		return $this->get_count_from_cookie( 'cart' );
}


public function get_wishlist_count()
{
	if ( is_user_logged_in() )		
		return $this->get_count_from_db( 'wishlist' );	
	else 
		return $this->get_count_from_cookie( 'wishlist' );
}


public function get_cart_items()
{
	if ( is_user_logged_in() )		
		return $this->get_items_from_db( 'cart' );	
	else 
		return $this->get_items_from_cookie( 'cart' );
}


public function get_wishlist_items()
{
	if ( is_user_logged_in() )		
		return $this->get_items_from_db( 'wishlist' );	
	else 
		return $this->get_items_from_cookie( 'wishlist' );
}


// *******************************************************************
// ------------------------------------------------------------------
//					BACK-END FUNCTIONS
// ------------------------------------------------------------------
// *******************************************************************


function enqueue_styles_and_scripts()
{
	// GET PLUGIN BASE DIR
	$s_script_base = plugin_dir_url( __FILE__ );
	// ENQUEUE MAIN SCRIPT
	wp_enqueue_script(
		'bq_wl_sc_script', 
		$s_script_base.'/bq_wlsc_script.js', 
		array( 'jquery' ) 
	);
	// CREATE AJAX VARIABLES USED BY SCRIPT
	$a_ajax_vars = array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'loader_src' => $s_script_base . 'ajax-loader.gif',
		'cart_texts' => $this->a_default_cart_texts,
		'wishlist_texts' => $this->a_default_wishlist_texts,
	);
	// PASS AJAX VARS TO SCRIPT
	wp_localize_script( 
		'bq_wl_sc_script', 
		'ajax_vars', 
		$a_ajax_vars
	);
}


private function build_link( $s_type, $i_item_id )
{	
	// GET DEFAULT LINK TEXTS
	$a_link_texts = ($s_type == 'cart') 
		? $this->a_default_cart_texts
		: $this->a_default_wishlist_texts;
	
	// GET CURRENT ITEM STATUS
	$b_is_added = $this->is_added( $s_type, $i_item_id );
	
	// GET ACTION - 'REMOVE' OR 'ADD' DEPENDING ON CURRENT STATUS
	$s_action = ($b_is_added) ? 'remove' : 'add';

	// START LINK STRING
	$s_link = "<a ";
	
	// ADD HREF
	$s_link .= "href='#' ";
	
	// ADD ID	
	$s_link .= "id='".$s_type."-button-".$i_item_id."' ";
	
	// ADD CLASS 
	$s_link .= "class='wlsc-button ".$s_type."-button ".$s_action."'";
	
	// FINISH LINK STRING
	$s_link .= ">" . $a_link_texts[$s_action] . "</a>";
	
	return $s_link;
}


private function is_added( $s_table, $i_item_id )
{
	if ( is_user_logged_in() )
		return $this->is_added_in_db( $s_table, $i_item_id );
	else
		return $this->is_added_in_cookie( $s_table, $i_item_id );
}


private function get_cookie_name( $s_table )
{
	return ($s_table == 'cart') 
		? $this->s_cart_cookie_name
		: $this->s_wishlist_cookie_name;
}


private function get_table_name( $s_table )
{
	return ($s_table == 'cart')
		? $this->s_cart_table_name
		: $this->s_wishlist_table_name;
}


// **********************************************
// ----------------------------------------------
//				DATABASE FUNCTIONS
// ----------------------------------------------
// **********************************************


private function add_to_db( $s_table, $i_item_id )
{
	global $wpdb;
	// GET TABLE NAME 
	$s_table_name = $this->get_table_name( $s_table );
	// CREATE AN ARRAY OF VALUES TO BE INSERTED
	$a_values = array(
		'wID' => $i_item_id,
		'uID' => get_current_user_id(),
	);
	// INSERT VALUES	
	$wpdb->insert( $s_table_name, $a_values );
}


private function remove_from_db( $s_table, $i_item_id )
{
	global $wpdb;
	// GET TABLE NAME 
	$s_table_name = $this->get_table_name( $s_table );
	// GET USER ID
	$i_uid = get_current_user_id();
	// CREATE AND EXECUTE QUERY	
	$s_query = "DELETE FROM {$s_table_name} WHERE uID={$i_uid} AND wID={$i_item_id};";
	$wpdb->query( $wpdb->prepare( $s_query ) );
}


private function get_count_from_db( $s_table )
{
	global $wpdb;
	// GET TABLE NAME
	$s_table_name = $this->get_table_name( $s_table );
	// GET USER ID
	$i_uid = get_current_user_id();
	// CREATE SELECTION QUERY
	$s_query = "SELECT COUNT(*) FROM {$s_table_name} WHERE uID={$i_uid}";
	
	// GET THE VALUES	
	$i_count = $wpdb->get_var( $wpdb->prepare( $s_query ) );
	$s_result_string = "<span class='{$s_table}-count'>{$i_count}</span>";
	return $s_result_string;
}


private function get_items_from_db( $s_table )
{
	global $wpdb;
	// GET TABLE NAME
	$s_table_name = $this->get_table_name( $s_table );
	// GET USER ID
	$i_uid = get_current_user_id();
	// CREATE SELECTION QUERY
	$s_query = "SELECT * FROM {$s_table_name} WHERE uID={$i_uid}";
	
	// GET THE VALUES	
	$a_items = $wpdb->get_col( $wpdb->prepare( $s_query ), 1 );
	return $a_items;
}


private function is_added_in_db( $s_table, $i_item_id )
{ 
	// GET TABLE NAME
	$s_table_name = $this->get_table_name( $s_table );
	// BUILD SQL QUERY
	$s_sql = "SELECT wID FROM {$s_table_name} WHERE uID=" . get_current_user_id() . " AND wID={$i_item_id}";
	// CHECK IF ANY RECORDS ARE RETURNED
	global $wpdb;
	$result = $wpdb->get_var( $wpdb->prepare( $s_sql ) );
	return !empty ( $result );
}


// **********************************************
// ----------------------------------------------
//				COOKIE FUNCTIONS
// ----------------------------------------------
// **********************************************


private function add_to_cookie( $s_table, $i_item_id )
{
	// GET COOKIE NAME
	$s_cookie_name = $this->get_cookie_name( $s_table );
		
	// GET THE UNIX TIMESTAMP OF ONE WEEK FROM NOW
	$i_next_week = time()+60*60*24*7; 
	
	// GET CURRENT COOKIE SIZE
	$i_cookie_size = count( $_COOKIE[$s_cookie_name] );
	
	// SET A NEW COOKIE
	setcookie( $s_cookie_name.'['.$i_cookie_size.']', $i_item_id, $i_next_week, '/' );
}


private function remove_from_cookie( $s_table, $i_item_id )
{
	// GET COOKIE NAME
	$s_cookie_name = $this->get_cookie_name( $s_table );	
	// GET COOKIE ARRAY INDEX
	$i_item_index = array_search( $i_item_id, $_COOKIE[$s_cookie_name] );
	// DELETE COOKIE
	setcookie( $s_cookie_name.'['.$i_item_index.']', '', 0, '/' );	
}


private function get_count_from_cookie( $s_table )
{
	// GET COOKIE NAME
	$s_cookie_name = $this->get_cookie_name( $s_table );	
	// GET COOKIE ARRAY SIZE
	$i_count = count($_COOKIE[$s_cookie_name]);
	// BUILD RESULT STRING
	$s_result_string = "<span class='{$s_table}-count'>{$i_count}</span>";
	return $s_result_string;
}


private function get_items_from_cookie( $s_table )
{
	// GET COOKIE NAME
	$s_cookie_name = $this->get_cookie_name( $s_table );	
	// RETURN COOKIE ARRAY
	return $_COOKIE[$s_cookie_name];
}


private function is_added_in_cookie( $s_table, $i_item_id )
{
	// GET COOKIE NAME
	$s_cookie_name = $this->get_cookie_name( $s_table );	
	// GET COOKIE ARRAY
	$a_cookie = $_COOKIE[$s_cookie_name];
	// CHECK IF COOKIE IS EMPTY AND IF CONTAINS ITEM
	return ( !empty( $a_cookie ) && in_array( $i_item_id, $a_cookie ) );
}

}
	
	
// *******************************************************************
// ------------------------------------------------------------------
// 						FUNCTION SHORTCUTS
// ------------------------------------------------------------------
// *******************************************************************

// GLOBALIZE AND INITIALIZE VARIABLE
global $bqwlsc; 
$bqwlsc = new bq_wishlist_shopping_cart();

// CART LINK
function bq_cart_link( $i_item_id, $a_link_texts = '' )
{
	global $bqwlsc;
	return $bqwlsc->cart_link( $i_item_id, $a_link_texts );
}
// WISHLIST LINK
function bq_wishlist_link( $i_item_id, $a_link_texts = '' )
{
	global $bqwlsc;
	return $bqwlsc->wishlist_link( $i_item_id, $a_link_texts );
}
// CART COUNT
function bq_cart_count()
{
	global $bqwlsc;
	return $bqwlsc->get_cart_count();
}
// WISHLIST COUNT
function bq_wishlist_count()
{
	global $bqwlsc;
	return $bqwlsc->get_wishlist_count();
}
// CART ITEMS
function bq_cart_items()
{
	global $bqwlsc;
	return $bqwlsc->get_cart_items();
}
// WISHLIST ITEMS
function bq_wishlist_items()
{
	global $bqwlsc;
	return $bqwlsc->get_wishlist_items();
}
// BUTTON CLICK
function bq_handle_click()
{
	global $bqwlsc;
	return $bqwlsc->handle_click();
}


?>