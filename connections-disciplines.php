<?php
/**
 * An extension for the Connections Business Directory plugin which adds the ability to add and assign disciplines to your business directory entries.
 *
 * @package   Connections Business Directory Disciplines
 * @category  Extension
 * @author    Steven A. Zahm
 * @license   GPL-2.0+
 * @link      http://connections-pro.com
 * @copyright 2018 Steven A. Zahm
 *
 * @wordpress-plugin
 * Plugin Name:       Connections Business Directory Disciplines
 * Plugin URI:        https://connections-pro.com/documentation/disciplines/
 * Description:       An extension for the Connections Business Directory plugin which adds the ability to add and assign disciplines to your business directory entries.
 * Version:           1.0
 * Author:            Steven A. Zahm
 * Author URI:        http://connections-pro.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       connections_disciplines
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Connections_Disciplines' ) ) {

	final class Connections_Disciplines {

		const VERSION = '1.0';

		/**
		 * @var string The absolute path this this file.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $file = '';

		/**
		 * @var string The URL to the plugin's folder.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $url = '';

		/**
		 * @var string The absolute path to this plugin's folder.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $path = '';

		/**
		 * @var string The basename of the plugin.
		 *
		 * @access private
		 * @since 1.0
		 */
		private static $basename = '';

		public function __construct() {

			self::$file       = __FILE__;
			self::$url        = plugin_dir_url( self::$file );
			self::$path       = plugin_dir_path( self::$file );
			self::$basename   = plugin_basename( self::$file );

			self::loadDependencies();

			// This should run on the `plugins_loaded` action hook. Since the extension loads on the
			// `plugins_loaded action hook, call immediately.
			self::loadTextdomain();

			// Add to Connections menu.
			add_filter( 'cn_submenu', array( __CLASS__, 'addMenu' ) );

			// Remove the "View" link from the "Disciplines" taxonomy admin page.
			add_filter( 'cn_discipline_row_actions', array( __CLASS__, 'removeViewAction' ) );

			// Register the metabox.
			add_action( 'cn_metabox', array( __CLASS__, 'registerMetabox') );

			// Attach disciplines to entry when saving an entry.
			add_action( 'cn_process_taxonomy-category', array( __CLASS__, 'attachDisciplines' ), 9, 2 );

			// Add the "Disciplines" option to the admin settings page.
			// This is also required so it'll be rendered by $entry->getContentBlock( 'disciplines' ).
			add_filter( 'cn_content_blocks', array( __CLASS__, 'settingsOption') );

			// Add the action that'll be run when calling $entry->getContentBlock( 'disciplines' ) from within a template.
			add_action( 'cn_entry_output_content-disciplines', array( __CLASS__, 'block' ), 10, 3 );

			// Register the widget.
			add_action( 'widgets_init', array( 'CN_Disciplines_Widget', 'register' ) );
		}

		/**
		 * The widget.
		 *
		 * @access private
		 * @since  1.0
		 * @static
		 * @return void
		 */
		private static function loadDependencies() {

			require_once( self::$path . 'includes/class.widgets.php' );
		}

		/**
		 * Load the plugin translation.
		 *
		 * Credit: Adapted from Ninja Forms / Easy Digital Downloads.
		 *
		 * @access private
		 * @since  1.0
		 * @static
		 *
		 * @return void
		 */
		public static function loadTextdomain() {

			// Plugin textdomain. This should match the one set in the plugin header.
			$domain = 'connections_disciplines';

			// Set filter for plugin's languages directory
			$languagesDirectory = apply_filters( "cn_{$domain}_languages_directory", dirname( self::$file ) . '/languages/' );

			// Traditional WordPress plugin locale filter
			$locale   = apply_filters( 'plugin_locale', get_locale(), $domain );
			$fileName = sprintf( '%1$s-%2$s.mo', $domain, $locale );

			// Setup paths to current locale file
			$local  = $languagesDirectory . $fileName;
			$global = WP_LANG_DIR . "/{$domain}/" . $fileName;

			if ( file_exists( $global ) ) {

				// Look in global `../wp-content/languages/{$domain}/` folder.
				load_textdomain( $domain, $global );

			} elseif ( file_exists( $local ) ) {

				// Look in local `../wp-content/plugins/{plugin-directory}/languages/` folder.
				load_textdomain( $domain, $local );

			} else {

				// Load the default language files
				load_plugin_textdomain( $domain, FALSE, $languagesDirectory );
			}
		}

		public static function addMenu( $menu ) {

			$menu[67]  = array(
				'hook'       => 'disciplines',
				'page_title' => 'Connections : ' . __( 'Disciplines', 'connections_disciplines' ),
				'menu_title' => __( 'Disciplines', 'connections_disciplines' ),
				'capability' => 'connections_edit_categories',
				'menu_slug'  => 'connections_disciplines',
				'function'   => array( __CLASS__, 'showPage' ),
			);

			return $menu;
		}

		public static function showPage() {

			// Grab an instance of the Connections object.
			$instance = Connections_Directory();

			if ( $instance->dbUpgrade ) {

				include_once CN_PATH . 'includes/inc.upgrade.php';
				connectionsShowUpgradePage();
				return;
			}

			switch ( $_GET['page'] ) {

				case 'connections_disciplines':
					include_once self::$path . 'includes/admin/pages/disciplines.php';
					connectionsShowDisciplinesPage();
					break;
			}
		}

		public static function removeViewAction( $actions ) {

			unset( $actions['view'] );

			return $actions;
		}

		/**
		 * Registered the custom metabox.
		 *
		 * @access private
		 * @since  1.0
		 * @static
		 */
		public static function registerMetabox() {

			$atts = array(
				'id'       => 'disciplines',
				'title'    => __( 'Disciplines', 'connections_disciplines' ),
				//'pages'    => $pages,
				'context'  => 'side',
				'priority' => 'core',
				'callback' => array( __CLASS__, 'metabox' ),
			);

			cnMetaboxAPI::add( $atts );
		}

		/**
		 * The disciplines metabox.
		 *
		 * @access public
		 * @since  1.0
		 *
		 * @param  cnEntry $entry   An instance of the cnEntry object.
		 * @param  array   $metabox The metabox options array from self::register().
		 */
		public static function metabox( $entry, $metabox ) {

			echo '<div class="disciplinediv" id="taxonomy-discipline">';

			$style = <<<HEREDOC
<style type="text/css" scoped>
	.disciplinediv div.tabs-panel {
		min-height: 42px;
		max-height: 200px;
		overflow: auto;
		padding: 0 0.9em;
		border: solid 1px #ddd;
		background-color: #fdfdfd;
	}
	.disciplinediv ul.disciplinechecklist ul {
		margin-left: 18px;
	}
</style>
HEREDOC;
			echo $style;

				echo '<div id="discipline-all" class="tabs-panel">';

				cnTemplatePart::walker(
					'term-checklist',
					array(
						'name'     => 'entry_discipline',
						'taxonomy' => 'discipline',
						'selected' => cnTerm::getRelationships( $entry->getID(), 'discipline', array( 'fields' => 'ids' ) ),
					)
				);

				echo '</div>';
			echo '</div>';
		}

		/**
		 * Add, update or delete the entry disciplines.
		 *
		 * @access public
		 * @since  1.0
		 * @static
		 *
		 * @param  string $action The action to being performed to an entry.
		 * @param  int    $id     The entry ID.
		 */
		public static function attachDisciplines( $action, $id ) {

			// Grab an instance of the Connections object.
			$instance = Connections_Directory();

			if ( isset( $_POST['entry_discipline'] ) && ! empty( $_POST['entry_discipline'] ) ) {

				$instance->term->setTermRelationships( $id, $_POST['entry_discipline'], 'discipline' );

			} else {

				$instance->term->setTermRelationships( $id, array(), 'discipline' );
			}
		}

		/**
		 * Add the custom meta as an option in the content block settings in the admin.
		 * This is required for the output to be rendered by $entry->getContentBlock().
		 *
		 * @access private
		 * @since  1.0
		 * @param  array  $blocks An associative array containing the registered content block settings options.
		 * @return array
		 */
		public static function settingsOption( $blocks ) {

			$blocks['disciplines'] = __( 'Disciplines', 'connections_disciplines' );

			return $blocks;
		}

		/**
		 * Callback for the `cn_entry_output_content-{id}` filter in @see cnOutput::getCategoryBlock()
		 *
		 * Renders the Disciplines content block.
		 * Modelled after the @see cnOutput::getCategoryBlock()
		 *
		 * @access  private
		 * @since   1.0
		 * @static
		 *
		 * @param  cnEntry    $object
		 * @param  array      $atts     The shortcode atts array passed from the calling action.
		 * @param  cnTemplate $template
		 */
		public static function block( $object, $atts, $template ) {

			global $wp_rewrite;

			$defaults = array(
				'container_tag'    => 'div',
				//'label_tag'        => 'span',
				'item_tag'         => 'span',
				'type'             => 'list',
				'list'             => 'unordered',
				//'label'            => __( 'Disciplines:', 'connections_disciplines' ) . ' ',
				'separator'        => ', ',
				'parent_separator' => ' &raquo; ',
				'before'           => '',
				'after'            => '',
				'link'             => FALSE,
				'parents'          => FALSE,
				//'child_of'         => 0,
				//'return'           => FALSE,
			);

			/**
			 * Allow extensions to filter the method default and supplied args.
			 *
			 * @since 1.0
			 */
			$atts = cnSanitize::args(
				apply_filters( 'cn_output_atts_discipline', $atts ),
				apply_filters( 'cn_output_default_atts_discipline', $defaults )
			);

			$terms = cnRetrieve::entryTerms( $object->getId(), 'discipline' );

			if ( empty( $terms ) ) {

				return;
			}

			$count = count( $terms );
			$html  = '';
			$label = '';
			$items = array();

			if ( 'list' == $atts['type'] ) {

				$atts['item_tag'] = 'li';
			}

			$i = 1;

			foreach ( $terms as $term ) {

				$text = '';
				//$text .= esc_html( $term->name );

				if ( $atts['parents'] ) {

					// If the term is a root parent, skip.
					if ( 0 !== $term->parent ) {

						$text .= self::getTermParents(
							$term->parent,
							'discipline',
							array(
								'link'       => $atts['link'],
								'separator'  => $atts['parent_separator'],
								'force_home' => $object->directoryHome['force_home'],
								'home_id'    => $object->directoryHome['page_id'],
							)
						);
					}
				}

				$atts['link'] = FALSE;
				if ( $atts['link'] ) {

					$rel = is_object( $wp_rewrite ) && $wp_rewrite->using_permalinks() ? 'rel="category tag"' : 'rel="category"';

					$url = cnTerm::permalink(
						$term,
						'category',
						array(
							'force_home' => $object->directoryHome['force_home'],
							'home_id'    => $object->directoryHome['page_id'],
						)
					);

					$text .= '<a href="' . $url . '" ' . $rel . '>' . esc_html( $term->name ) . '</a>';

				} else {

					$text .= esc_html( $term->name );
				}

				/**
				 * @since 1.0
				 */
				$items[] = apply_filters(
					'cn_entry_output_discipline_item',
					sprintf(
						'<%1$s class="cn-discipline-name cn-discipline-%2$d">%3$s%4$s</%1$s>',
						$atts['item_tag'],
						$term->term_id,
						$text,
						$count > $i && 'list' !== $atts['type'] ? esc_html( $atts['separator'] ) : ''
					),
					$term,
					$count,
					$i,
					$atts
				);

				$i++; // Increment here so the correct value is passed to the filter.
			}

			/*
			 * Remove NULL, FALSE and empty strings (""), but leave values of 0 (zero).
			 * Filter our these in case someone hooks into the `cn_entry_output_category_item` filter and removes a category
			 * by returning an empty value.
			 */
			$items = array_filter( $items, 'strlen' );

			/**
			 * @since 1.0
			 */
			$items = apply_filters( 'cn_entry_output_discipline_items', $items );

			if ( 'list' == $atts['type'] ) {

				$html .= sprintf(
					'<%1$s class="cn-discipline-list">%2$s</%1$s>',
					'unordered' === $atts['list'] ? 'ul' : 'ol',
					implode( '', $items )
				);

			} else {

				$html .= implode( '', $items );
			}

			/**
			 * @since 1.0
			 */
			$html = apply_filters(
				'cn_entry_output_discipline_container',
				sprintf(
					'<%1$s class="cn-disciplines">%2$s</%1$s>' . PHP_EOL,
					$atts['container_tag'],
					$atts['before'] . $label . $html . $atts['after']
				),
				$atts
			);

			echo $html;
		}

		/**
		 * Retrieve category parents with separator.
		 *
		 * NOTE: This is the Connections equivalent of @see get_category_parents() in WordPress core ../wp-includes/category-template.php
		 *
		 * @access public
		 * @since  8.5.18
		 * @static
		 *
		 * @param int    $id        Term ID.
		 * @param string $taxonomy  Term taxonomy.
		 * @param array  $atts      The attributes array. {
		 *
		 *     @type bool   $link       Whether to format as link or as a string.
		 *                              Default: FALSE
		 *     @type string $separator  How to separate categories.
		 *                              Default: '/'
		 *     @type bool   $nicename   Whether to use nice name for display.
		 *                              Default: FALSE
		 *     @type array  $visited    Already linked to categories to prevent duplicates.
		 *                              Default: array()
		 *     @type bool   $force_home Default: FALSE
		 *     @type int    $home_id    Default: The page set as the directory home page.
		 * }
		 *
		 * @return string|WP_Error A list of category parents on success, WP_Error on failure.
		 */
		public static function getTermParents( $id, $taxonomy = 'discipline', $atts = array() ) {

			$defaults = array(
				'link'       => FALSE,
				'separator'  => '/',
				'nicename'   => FALSE,
				'visited'    => array(),
				'force_home' => FALSE,
				'home_id'    => cnSettingsAPI::get( 'connections', 'connections_home_page', 'page_id' ),
			);

			$atts = cnSanitize::args( $atts, $defaults );

			$chain  = '';
			$parent = cnTerm::get( $id, $taxonomy );

			if ( is_wp_error( $parent ) ) {

				return $parent;
			}

			if ( $atts['nicename'] ) {

				$name = $parent->slug;

			} else {

				$name = $parent->name;
			}

			if ( $parent->parent && ( $parent->parent != $parent->term_id ) && ! in_array( $parent->parent,  $atts['visited'] ) ) {

				$atts['visited'][] = $parent->parent;

				$chain .= self::getTermParents( $parent->parent, 'discipline', $atts );
			}

			if ( $atts['link'] ) {

				$chain .= '<span class="cn-category-breadcrumb-item" id="cn-category-breadcrumb-item-' . esc_attr( $parent->term_id ) . '">' . '<a href="' . esc_url( cnTerm::permalink( $parent->term_id, 'category', $atts ) ) . '">' . $name . '</a>' . $atts['separator'] . '</span>';

			} else {

				$chain .= $name . esc_html( $atts['separator'] );
			}

			return $chain;
		}
	}

	/**
	 * Start up the extension.
	 *
	 * @access public
	 * @since 1.0
	 *
	 * @return mixed object | bool
	 */
	function Connections_Disciplines() {

			if ( class_exists('connectionsLoad') ) {

					return new Connections_Disciplines();

			} else {

				add_action(
					'admin_notices',
					 create_function(
						 '',
						'echo \'<div id="message" class="error"><p><strong>ERROR:</strong> Connections must be installed and active in order use Connections Disciplines.</p></div>\';'
						)
				);

				return FALSE;
			}
	}

	/**
	 * Since Connections loads at default priority 10, and this extension is dependent on Connections,
	 * we'll load with priority 11 so we know Connections will be loaded and ready first.
	 */
	add_action( 'plugins_loaded', 'Connections_Disciplines', 11 );

}
