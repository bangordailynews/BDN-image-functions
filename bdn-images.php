<?php
/*
Plugin Name: BDN Images
Contributors: wpdavis
Tags: images, image, media
Requires at least: 3.0
Tested up to: 3.2
Version: 0.1
*/


if( !function_exists( 'bdn_has_images' ) ) {
	
	/*
	* What we use to get the images.
	*/
	function bdn_get_images( $id, $args = array() ) {

		$defaults = array(
			'post_parent' => intval( $id ),
			'numberposts' => -1,
			'offset' => 0,
			'order' => 'ASC',
			'orderby' => 'menu_order ID',
			'post_status' => 'inherit',
			'post_type' => 'attachment',
			//'post_mime_type' => 'image'
			//Disabling post mime type unless it becomes an issue. Just adds another unnecessary where clause
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		//asort so we're always using the exact same array
		asort( $args );
		
		$key = md5( serialize( $args ) );
		
		$images = wp_cache_get( $key );
		if ( false === $images ) {
			$images = get_children( $args );
			
			if( $images && is_array( $images ) )
				$images = array_keys( $images );
			
			wp_cache_set( $key, $images );
		}
		
		return $images;

	} // End bdn_get_images
	
	
	/*
	* Checks if the post has at least one image attached
	*/
	function bdn_has_images( $id, $offset = 0 ){
		
		$images = bdn_get_images( $id, array( 'offset' => $offset, 'numberposts' => 1 ) );
		
		if( $images )
			return true;
		
	} // End bdn_has_images


	/**
	* Checks to see if image is horizontal and has appropriate width
	*/
	function bdn_image_meets_params( $id, $minwidth = 600 ) {
	
		$images = bdn_get_images( $id, array( 'numberposts' => 1 ) );
		
		$return = false;
		if( $images ) {
		
			$image = reset( $images );
			
			$meta = wp_get_attachment_metadata( $image );
			
			$width = $meta[ 'width' ];
			$height = $meta[ 'height' ];
			
			//We want images that are at least 4:3.
			if ( ( 3 * $width >= 4 * $height ) && ( $width >= $minwidth ) )
				$return = true;
				
		}
		
		return $return;
		
	} // End bdn_image_meets_params

			
			
	/**
	* Gets the images attached to the article
	* All the variables except $id and $args are deprecated.
	*/
	function bdn_image( $id, $args = array(), $width = 600, $showcaption = false, $link = false, $number = 1, $class = 'image', $offset = 0 ) {

		$id = intval( $id );
		
		$defaults = array(
			'numberposts' => 1,
			'offset' => 0,
			'width' => 600,
			'showcaption' => false,
			'link' => false,
			'class' => 'image',
			'size' => 'medium',
			'order' => 'ASC',
			'orderby' => 'menu_order ID',
			'post_status' => 'inherit',
			'post_type' => 'attachment',
			'stretch' => false,
			'buylink' => true
		);
		

		$args = wp_parse_args( $args, $defaults );
		extract( $args );
		
		$images = bdn_get_images( $id, array( 'numberposts' => $numberposts, 'offset' => $offset, 'order' => $order, 'orderby' => $orderby, 'post_status' => $post_status, 'post_type' => $post_type ) );
		
		if( $images ) {
			foreach( $images as $image ) {
			
				$meta = wp_get_attachment_metadata( $image );
				
				//Check to make sure image width and height are set correctly. Else set them both to the desired width
				$image_width = ( empty( $meta[ 'width' ] ) ) ? $width : $meta[ 'width' ];
				$image_height = ( empty( $meta[ 'height' ] ) ) ? $width : $meta[ 'height' ];
				
				//If the desired width exceeds the actual size of the image, don't stretch
				if( !$stretch && $width > $image_width ) {
					$width = $image_width;
					$height = $image_height;
				}
				
				$thumb = wp_get_attachment_image_src( $image, 'thumbnail' );
				$medium = wp_get_attachment_image_src( $image, 'medium' );
				$large = wp_get_attachment_image_src( $image, 'large' );
				$full = wp_get_attachment_image_src( $image, 'full' );
				
				//Replace straight quotes in the cutline so they don't foul up the tag
				$cutline = str_replace( '"', '&quot', get_post_field( 'post_excerpt', $image ) );
				
				//Integreates with Scott Bressler's media credit
				if( function_exists( 'get_media_credit_html' ) )
					$credit = get_media_credit_html( $image );
				
				//Standardize between thumb and thumbnail
				if( 'thumbnail' == $size )
					$size = 'thumb';
				
				//Check to make sure the size requested actually exists
				if( !in_array( $size, array( 'thumb', 'medium', 'large', 'full' ) ) )
					$size = 'large';
				
				//Get the image url
				$image_url = $$size;
				$image_url = $image_url[ 0 ];

				//If we're linking to something, do it up.
				if( 'image' == $link ) {
					echo '<a href="' . $large[ 0 ] . '" class="thickbox image_large" rel="gallery-' . $id . '" title="' . strip_tags( $cutline ) . '">';
				} elseif( 'article' == $link ) {
					echo '<a href="' . get_permalink( $id ) . '" title="' . get_the_title( $id ) . '">';
				}

				//Echo the img tag
				echo '<img src="' . $image_url . '" width="' . $width . '" class="' . $class . '" alt="' . strip_tags( $cutline ) . '"  title="' . strip_tags( $cutline ) . '" />';

				//If we have a  link, close it out.
				if ( $link )
					echo '</a>';
								
				if( $showcaption ) {
					echo '<div class="cutlineCredit">' . $credit . '</div>';
					echo '<div class="cutline">' . $cutline . '</div>';
				}

			} // Foreach loop

		} // If Images
	
	} // End bdn_image

			
	/**
	* Grabs the thumbnail version of the first attached photo
	* @TODO Turn args into array?
	*/
	function bdn_thumb( $id, $height = 150, $width = 150, $echo = true, $link = true, $ref = false ) {

			$id = intval( $id );
			
			$width = intval( $width );
			$height = intval( $height );
			
			//Check to see if we should be using a smaller version of the thumb
			if( $width <= 25 ) {
				$size = '25thumb';
			} elseif( $width <= 75 ) {
				$size = '75thumb';
			} else {
				$size = 'thumbnail';
			}
			
			$images = bdn_get_images( $id, array( 'numberposts' => 1 ) );
			
			if( $images ) {
				foreach( $images as $image ) {
				
					$meta = wp_get_attachment_metadata( $image );
					
					//Check to make sure image widths are set
					$image_width = ( empty( $meta[ 'width' ] ) ) ? $width : $meta[ 'width' ];
					$image_height = ( empty( $meta[ 'height' ] ) ) ? $height : $meta[ 'height' ];
					
					//Don't stretch
					if( $width > $image_width ) {
						$width = $image_width;
						$height = $image_height;
					}
	
					$thumb = wp_get_attachment_image_src( $image, $size );
					
					//Replace quotes so we don't break the string
					$cutline = str_replace( '"', '&quot;', get_post_field( 'post_excerpt', $image ) );
					
					if( $echo ) {
						if( $link )
							echo '<a href="' . get_permalink( $id ) . $ref . '">';
						echo '<img src="' . $thumb[0] . '"  alt="' . esc_html( $cutline ). '" class="th" height="' . $height . '" width="' . $width . '" />';
						if( $link )
							echo '</a>';
					} else {
						return $thumb[0];
					} // End echo
				} // End foreach
			} // End if images
	} // End bdn_thumb


} // end checking for dropin