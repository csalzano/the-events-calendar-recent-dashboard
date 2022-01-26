<?php
defined( 'ABSPATH' ) or exit;

/**
 * Plugin Name: The Events Calendar - Upcoming Events Dashboard
 * Plugin URI: https://breakfastco.xyz
 * Description: Adds an Upcoming Events table to edit.php in the dashboard to make it easy to find upcoming events among thousands.
 * Version: 1.0.0
 * Author: Corey Salzano
 * Author URI: https://breakfastco.xyz
 * Text Domain: tec-recent-dashboard
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

add_filter( 'views_edit-tribe_events', 'output_upcoming_events_table' );
function output_upcoming_events_table( $views )
{
	//Hijack this filter to output a table

	printf( '<h2>%s</h2>', __( 'Upcoming Events', 'tec-recent-dashboard' ) );

	$table = new Upcoming_Events_Feed_List_Table();
	$table->prepare_items();
	$table->display();

	//Return the unchanged value from the filter
	return $views;
}


if( ! class_exists( 'WP_List_Table' ) )
{
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class Upcoming_Events_Feed_List_Table extends WP_List_Table
{
	// bind data with column
	function column_default( $item, $column_name )
	{
		switch( $column_name )
		{
			case 'title':
				//$item contains the post ID for the title column
				$post = get_post( $item[$column_name] );

				//maybe a post state like "Draft" will display
				$post_states        = get_post_states( $post );
				$post_states_string = '';
			
				if ( ! empty( $post_states ) ) {
					$state_count = count( $post_states );
					$i           = 0;
			
					$post_states_string .= ' &mdash; ';
			
					foreach ( $post_states as $state ) {
						$sep = ( ++$i === $state_count ) ? '' : ', ';
			
						$post_states_string .= "<span class='post-state'>$state$sep</span>";
					}
				}

				$html = sprintf( 
					'<strong><a class="row-title" href="%1$s" aria-label="“%2$s” (%3$s)">%2$s</a>%4$s</strong>',
					get_edit_post_link( $post->ID ),
					$post->post_title,
					__( 'Edit', 'tec-recent-dashboard' ),
					$post_states_string
				);

				//Append the row actions to the item
				$actions = array(
					'edit'  => [],
					'trash' => [],
				);
				if( function_exists( 'tribe_is_recurring_event' ) && tribe_is_recurring_event( $post->ID ) )
				{
					if( class_exists( 'Tribe__Events__Pro__Recurrence__Meta' ) )
					{
						$actions = Tribe__Events__Pro__Recurrence__Meta::edit_post_row_actions( $actions, $post );
					}

					if( empty( $actions['trash'] ) )
					{
						$actions['trash'] = sprintf(
							'<span class="trash"><a class="submitdelete" aria-label="%s “%s” %s" href="%s">%s</a></span>',
							__( 'Move', 'tec-recent-dashboard' ),
							$post->post_title,
							__( 'to the Trash', 'tec-recent-dashboard' ),
							esc_url( get_delete_post_link( $post->ID ) ),
							esc_html__( 'Trash', 'tec-recent-dashboard' )
						);
					}

					$actions['view'] = sprintf(
						'<span class="view"><a href="%1$s" rel="bookmark" aria-label="%2$s “%3$s”">%2$s</a></span>',
						get_permalink( $post->ID ),
						__( 'View', 'tec-recent-dashboard' ),
						$post->post_title
					);
				}
				else
				{
					$post_type_object = get_post_type_object( $post->post_type );
					$can_edit_post    = current_user_can( 'edit_post', $post->ID );
					$actions          = array();
					$title            = _draft_or_post_title();
			
					if ( $can_edit_post && 'trash' !== $post->post_status ) {
						$actions['edit'] = sprintf(
							'<a href="%s" aria-label="%s">%s</a>',
							get_edit_post_link( $post->ID ),
							/* translators: %s: Post title. */
							esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ),
							__( 'Edit' )
						);
					}
			
					if ( current_user_can( 'delete_post', $post->ID ) ) {
						if ( EMPTY_TRASH_DAYS ) {
							$actions['trash'] = sprintf(
								'<span class="trash"><a href="%s" class="submitdelete" aria-label="%s">%s</a></span>',
								get_delete_post_link( $post->ID ),
								/* translators: %s: Post title. */
								esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $title ) ),
								_x( 'Trash', 'verb' )
							);
						}
					}
			
					if ( is_post_type_viewable( $post_type_object ) ) {
						if ( in_array( $post->post_status, array( 'pending', 'draft', 'future' ), true ) ) {
							if ( $can_edit_post ) {
								$preview_link    = get_preview_post_link( $post );
								$actions['view'] = sprintf(
									'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
									esc_url( $preview_link ),
									/* translators: %s: Post title. */
									esc_attr( sprintf( __( 'Preview &#8220;%s&#8221;' ), $title ) ),
									__( 'Preview' )
								);
							}
						} elseif ( 'trash' !== $post->post_status ) {
							$actions['view'] = sprintf(
								'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
								get_permalink( $post->ID ),
								/* translators: %s: Post title. */
								esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ),
								__( 'View' )
							);
						}
					}
				}

				return $html . sprintf( '<div class="row-actions">%s</div>', implode( ' | ', $actions ) );

			default:
				return ! empty( $item[$column_name] ) ? $item[$column_name] : '';
		}
	}

	protected function display_tablenav( $which )
	{
		if ( 'bottom' == $which )
		{
			echo '<br class="clear" />';
		}
	}

	// Define table columns
	function get_columns()
	{
		$columns = array(
			'title'     => __( 'Title', 'tec-recent-dashboard' ),
			'recurring' => __( 'Recurring', 'tec-recent-dashboard' ),
			'startdate' => __( 'Start Date', 'tec-recent-dashboard' ),
			'enddate'   => __( 'End Date', 'tec-recent-dashboard' ),
		);
		return $columns;
	}

	// Bind table with columns, data and all
	function prepare_items()
	{
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array(
			$columns, 
			$hidden, 
			$sortable
		);
		
		global $field_group;
		$this->items = [];


		$event_posts = get_posts( array(
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'     => '_EventStartDate',
					'value'   => date( 'Y-m-d' ),
					'compare' => '>',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => '_EventEndDate',
					'value'   => date( 'Y-m-d', strtotime( '+1 month' ) ),
					'compare' => '<',
					'type'    => 'DATETIME',
				),
			),
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'post_status' => get_post_stati(),
			'post_type'   => Tribe__Events__Main::POSTTYPE,
		) );

		$date_format = 'F j, Y';

		if( ! empty( $event_posts ) )
		{
			foreach( $event_posts as $post )
			{
				$this->items[] = array(
					'title'     => $post->ID,
					'recurring' => function_exists( 'tribe_is_recurring_event' ) && tribe_is_recurring_event( $post->ID ) ? __( 'Yes', 'tec-recent-dashboard' ) : __( '—', 'tec-recent-dashboard' ),
					'startdate' => tribe_get_start_date( $post->ID, false, $date_format ),
					'enddate'   => tribe_get_end_date( $post->ID, false, $date_format ),
				);
			}
		}
	}
}