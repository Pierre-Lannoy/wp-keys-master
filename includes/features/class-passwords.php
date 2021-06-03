<?php
/**
 * Keys Master list
 *
 * Lists all active passwords.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */

namespace KeysMaster\Plugin\Feature;

use KeysMaster\System\Conversion;

use KeysMaster\System\Date;
use KeysMaster\System\Timezone;
use KeysMaster\System\Option;
use KeysMaster\System\Password;
use KeysMaster\System\User;
use KeysMaster\System\GeoIP;
use KeysMaster\System\UserAgent;
use KeysMaster\System\Role;
use Feather\Icons;
use KeysMaster\System\Hash;


if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Define the passwords list functionality.
 *
 * Lists all active passwords.
 *
 * @package Features
 * @author  Pierre Lannoy <https://pierre.lannoy.fr/>.
 * @since   1.0.0
 */
class Passwords extends \WP_List_Table {

	/**
	 * Active passwords
	 *
	 * @since    1.0.0
	 * @var      array    $passwords    The passwords list.
	 */
	private $passwords = [];

	/**
	 * GeoIP helper.
	 *
	 * @since    1.0.0
	 * @var      \KeysMaster\System\GeoIP    $geoip    GeoIP helper.
	 */
	private $geoip = null;

	/**
	 * The number of lines to display.
	 *
	 * @since    1.0.0
	 * @var      integer    $limit    The number of lines to display.
	 */
	private $limit = 0;

	/**
	 * The page to display.
	 *
	 * @since    1.0.0
	 * @var      integer    $limit    The page to display.
	 */
	private $paged = 1;

	/**
	 * The main filter.
	 *
	 * @since    1.0.0
	 * @var      array    $filters    The main filter.
	 */
	private $filters = [];

	/**
	 * The order by of the list.
	 *
	 * @since    1.0.0
	 * @var      string    $orderby    The order by of the list.
	 */
	private $orderby = 'id';

	/**
	 * The order of the list.
	 *
	 * @since    1.0.0
	 * @var      string    $order    The order of the list.
	 */
	private $order = 'desc';

	/**
	 * The user id.
	 *
	 * @since    1.0.0
	 * @var      integer    $user_id    The user id.
	 */
	private $user_id = null;

	/**
	 * The current url.
	 *
	 * @since    1.0.0
	 * @var      string    $url    The current url.
	 */
	private $url = '';

	/**
	 * The form nonce.
	 *
	 * @since    1.0.0
	 * @var      string    $nonce    The form nonce.
	 */
	private $nonce = '';

	/**
	 * The action to perform.
	 *
	 * @since    1.0.0
	 * @var      string    $action    The action to perform.
	 */
	private $action = '';

	/**
	 * The bulk args.
	 *
	 * @since    1.0.0
	 * @var      array    $bulk    The bulk args.
	 */
	private $bulk = [];

	/**
	 * The Role settings.
	 *
	 * @since    1.0.0
	 * @var      array    $settings    The Role settings.
	 */
	private $settings = [];

	/**
	 * The Roles.
	 *
	 * @since    1.0.0
	 * @var      array    $roles    The Roles.
	 */
	private $roles = [];

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => 'password',
				'plural'   => 'passwords',
				'ajax'     => true,
			]
		);
		$this->geoip = new GeoIP();
		global $wp_version;
		if ( version_compare( $wp_version, '4.2-z', '>=' ) && $this->compat_fields && is_array( $this->compat_fields ) ) {
			array_push( $this->compat_fields, 'all_items' );
		}
		$this->settings = Option::roles_get();
		$this->roles    = Role::get_all();
		$this->process_args();
		$this->process_action();
		$this->passwords = [];
		foreach ( Password::get_all_passwords() as $user ) {
			if ( array_key_exists( 'meta_value', $user ) && is_array( $user['meta_value'] ) ) {
				foreach ( $user['meta_value'] as $password ) {
					$item              = [];
					$item['id']        = $user['user_id'];
					$item['umeta_id']  = $user['umeta_id'];
					$item['uuid']      = $password['uuid'];
					$item['name']      = $password['name'];
					$item['created']   = $password['created'];
					$item['last_used'] = $password['last_used'];
					$item['ip']        = $password['last_ip'];
					if ( 0 < count( $this->filters ) ) {
						foreach ( $this->filters as $filter => $value ) {
							if ( array_key_exists( $filter, $item ) && (string) $value !== (string) $item[ $filter ] ) {
								continue 2;
							}
						}
					}
					$this->passwords[] = $item;
				}
			}
		}
	}

	/**
	 * Default column formatter.
	 *
	 * @param   array  $item   The current item.
	 * @param   string $column_name The current column name.
	 * @return  string  The cell formatted, ready to print.
	 * @since    1.0.0
	 */
	protected function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Check box column formatter.
	 *
	 * @param   array $item   The current item.
	 * @return  string  The cell formatted, ready to print.
	 * @since    1.0.0
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input ' . ( $item['uuid'] === $this->selftoken ? 'disabled ' : '' ) . 'type="checkbox" name="bulk[]" value="%s" />',
			$item['id'] . ':' . $item['uuid']
		);
	}

	/**
	 * "user" column formatter.
	 *
	 * @param   array $item   The current item.
	 * @return  string  The cell formatted, ready to print.
	 * @since    1.0.0
	 */
	protected function column_id( $item ) {
		$user_info = get_userdata( $item['id'] );
		$name      = $user_info->display_name;
		$icon      = '<img style="width:32px;margin-right:10px;margin-top:1px;float:left;" src="' . esc_url( get_avatar_url( $item['id'], [ 'size' => '64' ] ) ) . '" />';
		$role      = Role::get_user_main( $item['id'] );
		if ( array_key_exists( $role, $this->roles ) ) {
			$role = $this->roles[ $role ]['l10n_name'];
		} else {
			$role = '';
		}
		$role = '<br /><span style="color:silver">' . $role . '</span>';
		$user = '<strong><a href="' . get_edit_user_link( $item['id'] ) . '">' . $name . '</a></strong>';
		return $icon . $user . $this->get_filter( 'id', $item['id'] ) . $role;
	}

	/**
	 * "name" column formatter.
	 *
	 * @param   array $item   The current item.
	 * @return  string  The cell formatted, ready to print.
	 * @since    1.0.0
	 */
	protected function column_name( $item ) {
		return $item['name'] . $this->get_filter( 'id', $item['id'] ) . '<br /><span style="color:silver">' . $item['uuid'] . '</span>';
	}

	/**
	 * "ip" column formatter.
	 *
	 * @param   array $item   The current item.
	 * @return  string  The cell formatted, ready to print.
	 * @since    1.0.0
	 */
	protected function column_ip( $item ) {
		if ( ! isset( $item['ip'] ) ) {
			return '-';
		}
		$icon   = $this->geoip->get_flag( $item['ip'], '', 'width:14px;padding-left:4px;padding-right:4px;vertical-align:baseline;' );
		$result = $icon . $item['ip'] . $this->get_filter( 'ip', $item['ip'] );
		return $result;
	}

	/**
	 * "created" column formatter.
	 *
	 * @param   array $item   The current item.
	 * @return  string  The cell formatted, ready to print.
	 * @since    1.0.0
	 */
	protected function column_created( $item ) {
		$datetime = new \DateTime( date( 'Y-m-d H:i:s', $item['created'] ) );
		$datetime->setTimezone( Timezone::network_get() );
		return $datetime->format( 'Y-m-d H:i:s' );
	}

	/**
	 * "login" column formatter.
	 *
	 * @param   array $item   The current item.
	 * @return  string  The cell formatted, ready to print.
	 * @since    1.0.0
	 */
	protected function column_last_used( $item ) {
		if ( isset( $item['last_used'] ) ) {
			$datetime = new \DateTime( date( 'Y-m-d H:i:s', $item['last_used'] ) );
			$datetime->setTimezone( Timezone::network_get() );
			return $datetime->format( 'Y-m-d H:i:s' );
		}
		return '-';
	}

	/**
	 * Get an internal link markup.
	 *
	 * @param   string $url The url.
	 * @param   string $anchor The anchor.
	 * @return  string  The link markup, ready to print.
	 * @since 1.0.0
	 */
	private function get_internal_link( $url, $anchor ) {
		if ( '' === $url ) {
			return $anchor;
		}
		return '<a href="' . $url . '" style="text-decoration:none;color:inherit;">' . $anchor . '</a>';
	}

	/**
	 * Enumerates columns.
	 *
	 * @return      array   The columns.
	 * @since    1.0.0
	 */
	public function get_columns() {
		$columns = [
			'cb'        => '<input type="checkbox" />',
			'id'        => esc_html__( 'User', 'keys-master' ),
			'name'      => esc_html__( 'Name', 'keys-master' ),
			'created'   => esc_html__( 'Created', 'keys-master' ),
			'ip'        => esc_html__( 'Last remote IP', 'keys-master' ),
			'last_used' => esc_html__( 'Last used', 'keys-master' ),
		];
		return $columns;
	}

	/**
	 * Enumerates hidden columns.
	 *
	 * @return      array   The hidden columns.
	 * @since    1.0.0
	 */
	protected function get_hidden_columns() {
		return [];
	}

	/**
	 * Enumerates sortable columns.
	 *
	 * @return      array   The sortable columns.
	 * @since    1.0.0
	 */
	protected function get_sortable_columns() {
		$sortable_columns = [
			'id'        => [ 'id', true ],
			'name'      => [ 'name', true ],
			'created'   => [ 'created', true ],
			'idle'      => [ 'idle', true ],
			'last_used' => [ 'last_used', true ],
		];
		return $sortable_columns;
	}

	/**
	 * Enumerates bulk actions.
	 *
	 * @return      array   The bulk actions.
	 * @since    1.0.0
	 */
	public function get_bulk_actions() {
		return [
			'invalidate' => esc_html__( 'Revoke password(s)', 'keys-master' ),
		];
	}

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @param string $which Position of extra control.
	 * @since 1.0.0
	 */
	protected function display_tablenav( $which ) {
		if ( 'top' === $which ) {
			wp_nonce_field( 'bulk-keys-master-tools', '_wpnonce', false );
		}
		echo '<div class="tablenav ' . esc_attr( $which ) . '">';
		if ( $this->has_items() ) {
			echo '<div class="alignleft actions bulkactions">';
			$this->bulk_actions( $which );
			echo '</div>';
		}
		$this->extra_tablenav( $which );
		$this->pagination( $which );
		echo '<br class="clear" />';
		echo '</div>';
	}

	/**
	 * Extra controls to be displayed between bulk actions and pagination.
	 *
	 * @param string $which Position of extra control.
	 * @since 1.0.0
	 */
	public function extra_tablenav( $which ) {
		$list = $this;
		$args = compact( 'list', 'which' );
		foreach ( $args as $key => $val ) {
			$$key = $val;
		}
		if ( 'top' === $which || 'bottom' === $which ) {
			include POKM_ADMIN_DIR . 'partials/keys-master-admin-tools-lines.php';
		}
	}

	/**
	 * Prepares the list to be displayed.
	 *
	 * @since    1.0.0
	 */
	public function prepare_items() {
		$this->set_pagination_args(
			[
				'total_items' => count( $this->passwords ),
				'per_page'    => $this->limit,
				'total_pages' => ceil( count( $this->passwords ) / $this->limit ),
			]
		);
		$current_page          = $this->get_pagenum();
		$columns               = $this->get_columns();
		$hidden                = $this->get_hidden_columns();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = [ $columns, $hidden, $sortable ];
		$data                  = $this->passwords;
		usort(
			$data,
			function ( $a, $b ) {
				$result = intval( $a[ $this->orderby ] ) < intval( $b[ $this->orderby ] ) ? 1 : -1;
				return ( 'asc' === $this->order ) ? -$result : $result;
			}
		);
		$this->items = array_slice( $data, ( ( $current_page - 1 ) * $this->limit ), $this->limit );
	}

	/**
	 * Get the filter image.
	 *
	 * @param   string  $filter     The filter name.
	 * @param   string  $value      The filter value.
	 * @param   boolean $soft       Optional. The image must be softened.
	 * @return  string  The filter image, ready to print.
	 * @since   1.0.0
	 */
	protected function get_filter( $filter, $value, $soft = false ) {
		$filters = $this->filters;
		if ( array_key_exists( $filter, $this->filters ) ) {
			unset( $this->filters[ $filter ] );
			$url    = $this->get_page_url();
			$alt    = esc_html__( 'Remove this filter', 'keys-master' );
			$fill   = '#9999FF';
			$stroke = '#0000AA';
		} else {
			$this->filters[ $filter ] = $value;
			$url                      = $this->get_page_url();
			$alt                      = esc_html__( 'Add as filter', 'keys-master' );
			$fill                     = 'none';
			if ( $soft ) {
				$stroke = '#C0C0FF';
			} else {
				$stroke = '#3333AA';
			}
		}
		$this->filters = $filters;
		return '&nbsp;<a href="' . $url . '"><img title="' . $alt . '" style="width:11px;vertical-align:baseline;" src="' . Icons::get_base64( 'filter', $fill, $stroke ) . '" /></a>';
	}

	/**
	 * Get the page url with args.
	 *
	 * @return  string  The url.
	 * @since 1.0.0
	 */
	public function get_page_url() {
		$args         = [];
		$args['page'] = 'pokm-manager';
		if ( count( $this->filters ) > 0 ) {
			foreach ( $this->filters as $key => $filter ) {
				if ( '' !== $filter ) {
					$args[ $key ] = $filter;
				}
			}
		}
		if ( 40 !== $this->limit ) {
			$args['limit'] = $this->limit;
		}
		if ( 'id' !== $this->orderby || 'desc' !== $this->order ) {
			$args['orderby'] = $this->orderby;
			$args['order']   = $this->order;
		}
		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		return $url;
	}

	/**
	 * Get available lines breakdowns.
	 *
	 * @since 1.0.0
	 */
	public function get_line_number_select() {
		$_disp  = [ 20, 40, 60, 80 ];
		$result = [];
		foreach ( $_disp as $d ) {
			$l          = [];
			$l['value'] = $d;
			// phpcs:ignore
			$l['text']     = sprintf( esc_html__( 'Display %d passwords per page', 'keys-master' ), $d );
			$l['selected'] = ( intval( $d ) === intval( $this->limit ) ? 'selected="selected" ' : '' );
			$result[]      = $l;
		}
		return $result;
	}

	/**
	 * Pagination links.
	 *
	 * @param string $which Position of extra control.
	 * @since 1.0.0
	 */
	protected function pagination( $which ) {
		if ( empty( $this->_pagination_args ) ) {
			return;
		}
		$total_items     = (int) $this->_pagination_args['total_items'];
		$total_pages     = (int) $this->_pagination_args['total_pages'];
		$infinite_scroll = false;
		if ( isset( $this->_pagination_args['infinite_scroll'] ) ) {
			$infinite_scroll = $this->_pagination_args['infinite_scroll'];
		}
		if ( 'top' === $which && $total_pages > 1 ) {
			$this->screen->render_screen_reader_content( 'heading_pagination' );
		}
		// phpcs:ignore
		$output               = '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items ), number_format_i18n( $total_items ) ) . '</span>';
		$current              = (int) $this->get_pagenum();
		$removable_query_args = wp_removable_query_args();
		$current_url          = $this->url;
		$current_url          = remove_query_arg( $removable_query_args, $current_url );
		$page_links           = [];
		$total_pages_before   = '<span class="paging-input">';
		$total_pages_after    = '</span></span>';
		$disable_first        = false;
		$disable_last         = false;
		$disable_prev         = false;
		$disable_next         = false;
		if ( 1 === $current ) {
			$disable_first = true;
			$disable_prev  = true;
		}
		if ( 2 === $current ) {
			$disable_first = true;
		}
		if ( $current === $total_pages ) {
			$disable_last = true;
			$disable_next = true;
		}
		if ( $current === $total_pages - 1 ) {
			$disable_last = true;
		}
		if ( $disable_first ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='first-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				$this->get_url( remove_query_arg( 'paged', $current_url ), true ),
				__( 'First page' ),
				'&laquo;'
			);
		}
		if ( $disable_prev ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='prev-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				$this->get_url( add_query_arg( 'paged', max( 1, $current - 1 ), $current_url ), true ),
				__( 'Previous page' ),
				'&lsaquo;'
			);
		}
		if ( 'bottom' === $which ) {
			$html_current_page  = $current;
			$total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page' ) . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
		} else {
			$html_current_page = sprintf(
				"%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
				'<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page' ) . '</label>',
				$current,
				strlen( $total_pages )
			);
		}
		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		// phpcs:ignore
		$page_links[]     = $total_pages_before . sprintf( _x( '%1$s of %2$s', 'paging' ), $html_current_page, $html_total_pages ) . $total_pages_after;
		if ( $disable_next ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='next-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				$this->get_url( add_query_arg( 'paged', min( $total_pages, $current + 1 ), $current_url ), true ),
				__( 'Next page' ),
				'&rsaquo;'
			);
		}
		if ( $disable_last ) {
			$page_links[] = '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
		} else {
			$page_links[] = sprintf(
				"<a class='last-page button' href='%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				$this->get_url( add_query_arg( 'paged', $total_pages, $current_url ), true ),
				__( 'Last page' ),
				'&raquo;'
			);
		}
		$pagination_links_class = 'pagination-links';
		if ( ! empty( $infinite_scroll ) ) {
			$pagination_links_class .= ' hide-if-js';
		}
		$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';
		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		} else {
			$page_class = ' no-pages';
		}
		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";
		// phpcs:ignore
		echo $this->_pagination;
	}

	/**
	 * Print column headers, accounting for hidden and sortable columns.
	 *
	 * @staticvar int $cb_counter.
	 * @param bool $with_id Whether to set the id attribute or not.
	 * @since 1.0.0
	 */
	public function print_column_headers( $with_id = true ) {
		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();
		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb']     = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label><input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}
		foreach ( $columns as $column_key => $column_display_name ) {
			$class = [ 'manage-column', "column-$column_key" ];
			if ( in_array( $column_key, $hidden, true ) ) {
				$class[] = 'hidden';
			}
			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			} elseif ( in_array( $column_key, [ 'posts', 'comments', 'links' ], true ) ) {
				$class[] = 'num';
			}
			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}
			if ( isset( $sortable[ $column_key ] ) ) {
				list( $orderby, $desc_first ) = $sortable[ $column_key ];
				if ( $this->orderby === $orderby ) {
					$order   = 'asc' === $this->order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $this->order;
				} else {
					$order   = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}
				$column_display_name = '<a href="' . $this->get_url( add_query_arg( compact( 'orderby', 'order' ), $this->url ), true ) . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';
			}
			$tag   = ( 'cb' === $column_key ) ? 'td' : 'th';
			$scope = ( 'th' === $tag ) ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';
			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}
			// phpcs:ignore
			echo "<$tag $scope $id $class>$column_display_name</$tag>";
		}
	}

	/**
	 * Generates content for a single row of the table
	 *
	 * @param object $item The current item
	 */
	public function single_row( $item ) {
		echo '<tr>';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Get the cleaned url.
	 *
	 * @param boolean $url Optional. The url, false for current url.
	 * @param boolean $limit Optional. Has the limit to be in the url.
	 * @return string The url cleaned, ready to use.
	 * @since 1.0.0
	 */
	public function get_url( $url = false, $limit = false ) {
		global $wp;
		$url = remove_query_arg( 'limit', $url );
		if ( $limit ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . 'limit=' . $this->limit;
		}
		return esc_url( $url );
	}

	/**
	 * Initializes all the list properties.
	 *
	 * @since 1.0.0
	 */
	public function process_args() {
		$this->nonce   = filter_input( INPUT_POST, '_wpnonce' );
		$this->url     = set_url_scheme( 'http://' . filter_input( INPUT_SERVER, 'HTTP_HOST' ) . filter_input( INPUT_SERVER, 'REQUEST_URI' ) );
		$this->limit   = filter_input( INPUT_GET, 'limit', FILTER_SANITIZE_NUMBER_INT );
		$this->user_id = filter_input( INPUT_GET, 'user_id', FILTER_SANITIZE_NUMBER_INT );
		foreach ( [ 'top', 'bottom' ] as $which ) {
			if ( wp_verify_nonce( $this->nonce, 'bulk-keys-master-tools' ) && array_key_exists( 'dolimit-' . $which, $_POST ) ) {
				$this->limit = filter_input( INPUT_POST, 'limit-' . $which, FILTER_SANITIZE_NUMBER_INT );
			}
		}
		if ( 0 === intval( $this->limit ) ) {
			$this->limit = filter_input( INPUT_POST, 'limit-top', FILTER_SANITIZE_NUMBER_INT );
		}
		if ( 0 === intval( $this->limit ) ) {
			$this->limit = 40;
		}
		$this->paged = filter_input( INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT );
		if ( ! $this->paged ) {
			$this->paged = filter_input( INPUT_POST, 'paged', FILTER_SANITIZE_NUMBER_INT );
			if ( ! $this->paged ) {
				$this->paged = 1;
			}
		}
		$this->order = filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING );
		if ( ! $this->order ) {
			$this->order = 'desc';
		}
		$this->orderby = filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING );
		if ( ! $this->orderby ) {
			$this->orderby = 'id';
		}
		foreach ( [ 'id', 'ip' ] as $f ) {
			$v = filter_input( INPUT_GET, $f, FILTER_SANITIZE_STRING );
			if ( $v ) {
				$this->filters[ $f ] = $v;
			}
		}
		foreach ( [ 'top', 'bottom' ] as $which ) {
			if ( wp_verify_nonce( $this->nonce, 'bulk-keys-master-tools' ) && array_key_exists( 'dowarmup-' . $which, $_POST ) ) {
				$this->action = 'warmup';
			}
			if ( wp_verify_nonce( $this->nonce, 'bulk-keys-master-tools' ) && array_key_exists( 'doinvalidate-' . $which, $_POST ) ) {
				$this->action = 'reset';
			}
		}
		if ( '' === $this->action ) {
			$action = '-1';
			if ( '-1' === $action && wp_verify_nonce( $this->nonce, 'bulk-keys-master-tools' ) && array_key_exists( 'action', $_POST ) ) {
				$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );
			}
			if ( '-1' === $action && wp_verify_nonce( $this->nonce, 'bulk-keys-master-tools' ) && array_key_exists( 'action2', $_POST ) ) {
				$action = filter_input( INPUT_POST, 'action2', FILTER_SANITIZE_STRING );
			}
			if ( '-1' !== $action && wp_verify_nonce( $this->nonce, 'bulk-keys-master-tools' ) && array_key_exists( 'bulk', $_POST ) ) {
				$this->bulk = filter_input( INPUT_POST, 'bulk', FILTER_SANITIZE_STRING, FILTER_FORCE_ARRAY );
				if ( 0 < count( $this->bulk ) ) {
					$this->action = $action;
				}
			}
		}
	}

	/**
	 * Processes the selected action.
	 *
	 * @since 1.0.0
	 */
	public function process_action() {
		switch ( $this->action ) {
			case 'invalidate':
				$count = Password::delete_selected_passwords( $this->bulk );
				if ( is_integer( $count ) ) {
					if ( 0 < $count ) {
						$message = esc_html__( 'All selected passwords have been revoked.', 'keys-master' );
						$code    = 0;
					} else {
						$message = esc_html__( 'No passwords to revoke.', 'keys-master' );
						$code    = 0;
					}
				} else {
					$message = esc_html__( 'Unable to revoke all selected passwords. Please see events log.', 'keys-master' );
					$code    = 500;
				}
				break;
			default:
				return;
		}
		if ( 0 === $code ) {
			add_settings_error( 'pokm_manager_no_error', $code, $message, 'updated' );
		} else {
			add_settings_error( 'pokm_manager_error', $code, $message, 'error' );
		}
	}
}
