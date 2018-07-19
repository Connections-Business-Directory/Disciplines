<?php

/**
 * The disciplines admin page.
 *
 * @package     Connections
 * @subpackage  The categories admin page.
 * @copyright   Copyright (c) 2013, Steven A. Zahm
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       unknown
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function connectionsShowDisciplinesPage() {

	global $current_screen;

	/*
	 * Check whether user can edit terms.
	 */
	if ( ! current_user_can( 'connections_edit_categories' ) ) {

		wp_die(
			'<p id="error-page" style="-moz-background-clip:border;
				-moz-border-radius:11px;
				background:#FFFFFF none repeat scroll 0 0;
				border:1px solid #DFDFDF;
				color:#333333;
				display:block;
				font-size:12px;
				line-height:18px;
				margin:25px auto 20px;
				padding:1em 2em;
				text-align:center;
				width:700px">' . __(
				'You do not have sufficient permissions to access this page.',
				'connections_disciplines'
			) . '</p>'
		);

	} else {

		// Grab an instance of the Connections object.
		//$instance = Connections_Directory();

		$form     = new cnFormObjects();
		$taxonomy = 'discipline';
		$action   = '';

		if ( isset( $_GET['cn-action'] ) ) {

			$action = $_GET['cn-action'];
		}

		if ( $action === "edit_{$taxonomy}" ) {

			/**
			 * Use with caution, see https://codex.wordpress.org/Function_Reference/wp_reset_vars
			 */
			wp_reset_vars( array( 'wp_http_referer' ) );

			$id = absint( $_GET['id'] );
			check_admin_referer( "{$taxonomy}_edit_" . $id );

			$term     = cnTerm::get( $id, $taxonomy );
			$category = new cnCategory( $term );

			/**
			 * Fires before the Edit Term form for all taxonomies.
			 *
			 * The dynamic portion of the hook name, `$taxonomy`, refers to
			 * the taxonomy slug.
			 *
			 * @since 3.0.0
			 *
			 * @param object $tag      Current taxonomy term object.
			 * @param string $taxonomy Current $taxonomy slug.
			 */
			do_action( "cn_{$taxonomy}_pre_edit_form", $term, $taxonomy );

			?>

			<div class="wrap">
				<div class="form-wrap" style="width:600px; margin: 0 auto;">
					<h1><a name="new"></a><?php _e( 'Edit Discipline', 'connections_disciplines' ); ?></h1>

					<?php
					$attr = array(
						'action' => '',
						'method' => 'post',
						'id'     => 'edit-term',
						'name'   => "update-{$taxonomy}"
					);

					$form->open( $attr );
					//$form->tokenField( "update_{$taxonomy}" );

					/**
					 * Fires inside the Edit Term form tag.
					 *
					 * The dynamic portion of the hook name, `$taxonomy`, refers to
					 * the taxonomy slug.
					 *
					 * @since 3.7.0
					 */
					do_action( "cn_{$taxonomy}_term_edit_form_tag" );
					?>

					<input type="hidden" name="cn-action" value="update-term" />
					<input type="hidden" name="screen" value="<?php echo esc_attr($current_screen->id); ?>" />
					<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>" />

					<?php $form->tokenField( 'update-term' ); ?>

					<div class="form-field form-required term-name-wrap">
						<label for="term_name"><?php _e( 'Name', 'connections_disciplines' ) ?></label>
						<input type="text" aria-required="true" size="40" value="<?php echo esc_attr( $category->getName() ); ?>" id="term_name" name="term_name"/>
						<input type="hidden" value="<?php echo esc_attr( $category->getID() ); ?>" id="term_id" name="term_id"/>

						<p><?php _e( 'The name is how it appears on your site.', 'connections_disciplines' ); ?></p>
					</div>

					<div class="form-field term-slug-wrap">
						<label for="term_slug"><?php _e( 'Slug', 'connections_disciplines' ); ?> </label>
						<input type="text" size="40" value="<?php echo esc_attr( $category->getSlug() ); ?>" id="term_slug" name="term_slug"/>

						<p><?php _e(
								'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.',
								'connections_disciplines'
							); ?></p>
					</div>

					<div class="form-field term-parent-wrap">
						<label for="term_parent"><?php _e( 'Parent', 'connections_disciplines' ); ?></label>

						<?php
						cnTemplatePart::walker(
							'term-select',
							array(
								'hide_empty'       => 0,
								'hide_if_empty'    => FALSE,
								'name'             => 'term_parent',
								'orderby'          => 'name',
								'taxonomy'         => 'discipline',
								'selected'         => $category->getParent(),
								'exclude_tree'     => $category->getID(),
								'hierarchical'     => TRUE,
								'show_option_none' => __( 'None', 'connections_disciplines' ),
								//'return'           => TRUE,
							)
						);
						?>
						<p><?php _e(
								'Categories can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.',
								'connections_disciplines'
							); ?></p>
					</div>


					<div class="form-field term-description-wrap">
						<?php
						ob_start();

						/*
						 * Now we're going to have to keep track of which TinyMCE plugins
						 * WP core supports based on version, sigh.
						 */
						if ( version_compare( $GLOBALS['wp_version'], '3.8.999', '<' ) ) {

							$tinymcePlugins = array(
								'inlinepopups',
								'tabfocus',
								'paste',
								'wordpress',
								'wplink',
								'wpdialogs'
							);

						} else {

							$tinymcePlugins = array( 'tabfocus', 'paste', 'wordpress', 'wplink', 'wpdialogs' );
						}

						wp_editor(
							wp_kses_post( $category->getDescription() ),
							'term_description',
							array(
								'media_buttons' => FALSE,
								'tinymce'       => array(
									'editor_selector'   => 'tinymce',
									'toolbar1'          => 'bold, italic, underline, |, bullist, numlist, |, justifyleft, justifycenter, justifyright, alignleft, aligncenter, alignright, |, link, unlink, |, pastetext, pasteword, removeformat, |, undo, redo',
									'toolbar2'          => '',
									'inline_styles'     => TRUE,
									'relative_urls'     => FALSE,
									'remove_linebreaks' => FALSE,
									'plugins'           => implode( ',', $tinymcePlugins )
								)
							)
						);

						echo ob_get_clean();
						?>
					</div>

					<?php
					/**
					 * Fires after the Edit Term form fields are displayed.
					 *
					 * The dynamic portion of the hook name, `$taxonomy`, refers to
					 * the taxonomy slug.
					 *
					 * @since 3.0.0
					 *
					 * @param object $tag      Current taxonomy term object.
					 * @param string $taxonomy Current taxonomy slug.
					 */
					do_action( "cn_{$taxonomy}_edit_form_fields", $term, $taxonomy );

					/**
					 * Fires at the end of the Edit Term form for all taxonomies.
					 *
					 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
					 *
					 * @since 3.0.0
					 *
					 * @param object $tag      Current taxonomy term object.
					 * @param string $taxonomy Current taxonomy slug.
					 */
					do_action( "{$taxonomy}_edit_form", $term, $taxonomy );

					$cancelURL = add_query_arg( 'page', 'connections_disciplines', self_admin_url( 'admin.php' ) );
					?>

					<!--<input type="hidden" name="cn-action" value="update_category"/>-->

					<p class="submit">
						<a class="button button-warning" href="<?php echo esc_url( $cancelURL ); ?>"><?php _e( 'Cancel', 'connections_disciplines' ); ?></a>
						<input type="submit" name="update" id="update" class="button button-primary" value="<?php _e( 'Update Discipline', 'connections_disciplines' ); ?>"/>
					</p>

					<?php $form->close(); ?>

				</div>
			</div>
		<?php
		} else {

			/**
			 * @var CN_Term_Admin_List_Table $table
			 */
			$table = cnTemplatePart::table(
				'term-admin',
				array(
					'screen'   => get_current_screen()->id,
					'taxonomy' => $taxonomy
				)
			);

			$table->prepare_items();
			?>
			<div class="wrap nosubsub">

				<h1>Connections : <?php _e( 'Disciplines', 'connections_disciplines' ); ?></h1>

				<form class="search-form" action="" method="get">

					<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>"/>
					<?php $table->search_box( __( 'Search Disciplines', 'connections_disciplines' ), $taxonomy ); ?>

				</form>
				<br class="clear"/>

				<div id="col-container">

					<div id="col-right">
						<div class="col-wrap">
							<?php

							$attr = array(
								'action' => '',
								'method' => 'post'
							);

							$form->open( $attr );
							//$form->tokenField( 'bulk_delete_category' );
							?>

							<input type="hidden" name="cn-action" value="bulk-term-action" />
							<input type="hidden" name="screen" value="<?php echo esc_attr($current_screen->id); ?>" />
							<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>" />

							<?php
							$table->display();

							$form->close();

							?>

							<br class="clear" />

							<script type="text/javascript">
								/* <![CDATA[ */
								(function ($) {
									$(document).ready(function () {
										$('#doaction, #doaction2').click(function () {
											if ($('select[name^="action"]').val() == 'delete') {
												var m = 'You are about to delete the selected discipline(s).\n  \'Cancel\' to stop, \'OK\' to delete.';
												return showNotice.warn(m);
											}
										});
									});
								})(jQuery);
								/* ]]> */
							</script>

							<div class="form-wrap">
								<p><?php _e(
										'<strong>Note:</strong><br/>Deleting a discipline which has been assigned to an entry will reassign that entry to the default discipline.',
										'connections_disciplines'
									); ?></p>
							</div>

							<?php
							/**
							 * Fires after the taxonomy list table.
							 *
							 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
							 *
							 * @since 3.0.0
							 *
							 * @param string $taxonomy The taxonomy name.
							 */
							do_action( "cn_after-{$taxonomy}-table", $taxonomy );
							?>

						</div>
					</div>
					<!-- right column -->

					<div id="col-left">
						<div class="col-wrap">

							<?php
							/**
							 * Fires before the Add Term form for all taxonomies.
							 *
							 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
							 *
							 * @since 3.0.0
							 *
							 * @param string $taxonomy The taxonomy slug.
							 */
							do_action( "cn_{$taxonomy}_pre_add_form", $taxonomy );
							?>

							<div class="form-wrap">
								<h3><?php _e( 'Add New Discipline', 'connections_disciplines' ); ?></h3>

								<?php
								$attr = array(
									'id'     => 'add-term',
									'action' => '',
									'method' => 'post'
								);

								$form->open( $attr );

								/**
								 * Fires at the beginning of the Add Tag form.
								 *
								 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
								 *
								 * @since 3.7.0
								 */
								do_action( "cn_{$taxonomy}_term_new_form_tag" );
								?>
								<input type="hidden" name="cn-action" value="add-term" />
								<input type="hidden" name="screen" value="<?php echo esc_attr($current_screen->id); ?>" />
								<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>" />

								<?php $form->tokenField( "add-term" ); ?>

								<div class="form-field form-required term-name-wrap">
									<label for="term_name"><?php _e( 'Name', 'connections_disciplines' ); ?></label>
									<input type="text" aria-required="true" size="40" value="" id="term_name" name="term_name"/>
									<input type="hidden" value="" id="term_id" name="term_id"/>

									<p><?php _e( 'The name is how it appears on your site.', 'connections_disciplines' ); ?></p>
								</div>

								<div class="form-field term-slug-wrap">
									<label for="term_slug"><?php _e( 'Slug', 'connections_disciplines' ); ?></label>
									<input type="text" size="40" value="" id="term_slug" name="term_slug"/>

									<p><?php _e(
											'The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.',
											'connections_disciplines'
										); ?></p>
								</div>

								<div class="form-field term-parent-wrap">
									<label for="term_parent"><?php _e( 'Parent', 'connections_disciplines' ); ?></label>

									<?php
									$dropdown_args = array(
										'hide_empty'       => 0,
										'hide_if_empty'    => FALSE,
										'taxonomy'         => 'discipline',
										'name'             => 'term_parent',
										'orderby'          => 'name',
										'hierarchical'     => TRUE,
										'show_option_none' => __( 'None', 'connections_disciplines' ),
									);

									/**
									 * Filter the taxonomy parent drop-down on the Edit Term page.
									 *
									 * @since 3.7.0
									 *
									 * @param array   $dropdown_args    {
									 *                                  An array of taxonomy parent drop-down arguments.
									 *
									 * @type int|bool $hide_empty       Whether to hide terms not attached to any posts. Default 0|false.
									 * @type bool     $hide_if_empty    Whether to hide the drop-down if no terms exist. Default false.
									 * @type string   $taxonomy         The taxonomy slug.
									 * @type string   $name             Value of the name attribute to use for the drop-down select element.
									 *                                      Default 'parent'.
									 * @type string   $orderby          The field to order by. Default 'name'.
									 * @type bool     $hierarchical     Whether the taxonomy is hierarchical. Default true.
									 * @type string   $show_option_none Label to display if there are no terms. Default 'None'.
									 * }
									 *
									 * @param string  $taxonomy         The taxonomy slug.
									 */
									$dropdown_args = apply_filters(
										'cn_taxonomy_parent_dropdown_args',
										$dropdown_args,
										$taxonomy
									);

									cnTemplatePart::walker( 'term-select', $dropdown_args );
									?>

									<p><?php _e(
											'Categories can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.',
											'connections_disciplines'
										); ?></p>
								</div>

								<div class="form-field term-description-wrap">
									<?php
									ob_start();

									/*
									 * Now we're going to have to keep track of which TinyMCE plugins
									 * WP core supports based on version, sigh.
									 */
									if ( version_compare( $GLOBALS['wp_version'], '3.8.999', '<' ) ) {

										$tinymcePlugins = array(
											'inlinepopups',
											'tabfocus',
											'paste',
											'wordpress',
											'wplink',
											'wpdialogs'
										);

									} else {

										$tinymcePlugins = array(
											'tabfocus',
											'paste',
											'wordpress',
											'wplink',
											'wpdialogs'
										);
									}

									wp_editor(
										'',
										'term_description',
										array(
											'media_buttons' => FALSE,
											'tinymce'       => array(
												'editor_selector'   => 'tinymce',
												'toolbar1'          => 'bold, italic, underline, |, bullist, numlist, |, justifyleft, justifycenter, justifyright, alignleft, aligncenter, alignright, |, link, unlink, |, pastetext, pasteword, removeformat, |, undo, redo',
												'toolbar2'          => '',
												'inline_styles'     => TRUE,
												'relative_urls'     => FALSE,
												'remove_linebreaks' => FALSE,
												'plugins'           => implode( ',', $tinymcePlugins )
											)
										)
									);

									echo ob_get_clean();
									?>

								</div>

								<!--<input type="hidden" name="cn-action" value="add_--><?php //echo $taxonomy; ?><!--"/>-->

								<?php
								/**
								 * Fires after the Add Term form fields for hierarchical taxonomies.
								 *
								 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
								 *
								 * @since 3.0.0
								 *
								 * @param string $taxonomy The taxonomy slug.
								 */
								do_action( "cn_{$taxonomy}_add_form_fields", $taxonomy );

								submit_button( __( 'Add New Discipline', 'connections_disciplines' ), 'primary', 'add' );

								/**
								 * Fires at the end of the Add Term form for all taxonomies.
								 *
								 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
								 *
								 * @since 3.0.0
								 *
								 * @param string $taxonomy The taxonomy slug.
								 */
								do_action( "cn_{$taxonomy}_add_form", $taxonomy );
								?>

								<?php $form->close(); ?>
							</div>
						</div>
					</div>
					<!-- left column -->

				</div>
				<!-- Column container -->
			</div>
		<?php
		}
	}
}
