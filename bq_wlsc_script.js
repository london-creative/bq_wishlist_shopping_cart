var $ = jQuery.noConflict();

$( document ).ready( function(){

$( 'body' ).delegate( '.wlsc-button', 'click', function( event ){
	
	// GET THE CLICKED LINK ELEMENT
	var clicked_link = $(this);
	
	// GET LINK TYPE - CART OR WISHLIST
	var type;
	if ( clicked_link.hasClass('cart-button') )
		type = 'cart'
	else
		type = 'wishlist';
		
	// GET THE CLICKED WATCH ID
	var watch_id;
	if ( type == 'cart' )
		watch_id = clicked_link.attr('id').replace( 'cart-button-', '' )
	else
		watch_id = clicked_link.attr('id').replace( 'wishlist-button-', '' )
	
	// GET ACTION - ADDING OR REMOVING
	var action;
	if ( clicked_link.hasClass('add') )
		action = 'add'
	else
		action= 'remove';
		
	// PREVENT DEFAULT CLICK ACTION
	event.preventDefault();
	// CHANGE LINK TO LOADER ANIMATION
	clicked_link.html("<img src='" + ajax_vars.loader_src + "' alt='loading...' />'");
	
	$.ajax({
		url: ajax_vars.ajax_url,
		type: 'POST',
		data: 'action=button_click&wid=' + watch_id + '&table=' + type,
		success: function( html ) 
		{
			//console.log(html);
			// CHANGE LINK TEXT AND ATTRIBUTES WHEN SUCCESSFULLY ADDED
			// INCREASE / DECREASE COUNT VALUES
			if ( action == 'add' )
			{
				clicked_link.removeClass( 'add' );
				clicked_link.addClass( 'remove' );
				if ( type == 'cart' )
				{
					clicked_link.html( ajax_vars.cart_texts.remove );
					change_count( 'cart', 'inc' );
				}
				else
				{
					clicked_link.html( ajax_vars.wishlist_texts.remove );
					change_count( 'wishlist', 'inc' );
				}
			}
			else
			{
				clicked_link.removeClass( 'remove' );
				clicked_link.addClass( 'add' );
				if ( type == 'cart' )
				{
					clicked_link.html( ajax_vars.cart_texts.add );
					change_count( 'cart', 'dec' );
				}
				else
				{
					clicked_link.html( ajax_vars.wishlist_texts.add );
					change_count( 'wishlist', 'dec' );
				}
			}
		}
	});
	
} );


function change_count( type, action )
{ 
	// GET THE DESIRED ELEMENTS
	var element = $( '.'+type+'-count' );
	
	// IF NO ELEMENTS EXIST, STOP
	if ( element.length == 0 )
		return false;
	
	// GET CURRENT VALUE
	var current_count = parseInt( element.html() );
	
	// ASSIGN NEW VALUE
	if ( action == 'inc' )
		element.html( ++current_count );
	else
		element.html( --current_count );
}


} );