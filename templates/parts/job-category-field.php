<?php
/**
 * Job form category field, shared by the job-form and job-form-simple blocks.
 *
 * One category per job (the Free default) renders the familiar select. When
 * `wcb_job_category_limit` allows more, it renders a checkbox list with the
 * chosen categories as removable chips, a counter, and a search box once the
 * list is long. The chosen slugs live in the host form's own state
 * (`categorySlugs`), so its preview and submit read them directly.
 *
 * Expects:
 *   $wcb_jobcat_form   (string)   Host form store namespace, e.g. 'wcb-job-form'.
 *   $wcb_jobcat_id     (string)   Id of the select, prefix for the other ids.
 *   $wcb_jobcat_terms  (object[]) \WCB\Core\Taxonomy::options( 'wcb_category' ).
 *
 * @package WP_Career_Board
 * @since   1.8.1
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** This filter is documented in api/endpoints/class-jobs-endpoint.php */
$wcb_jobcat_limit = max( 1, (int) apply_filters( 'wcb_job_category_limit', 1 ) );

$wcb_jobcat_names = array();
foreach ( $wcb_jobcat_terms as $wcb_jobcat_term ) {
	$wcb_jobcat_names[ $wcb_jobcat_term->slug ] = $wcb_jobcat_term->name;
}

wp_interactivity_state(
	'wcb-job-category-field',
	array(
		'limit'         => $wcb_jobcat_limit,
		'names'         => (object) $wcb_jobcat_names,
		/* translators: 1: categories chosen so far, 2: most allowed. */
		'counterFormat' => __( '%1$s of %2$s chosen', 'wp-career-board' ),
		'limitMessage'  => sprintf(
			/* translators: %s: most categories one job may have. */
			_n( 'You can choose up to %s category.', 'You can choose up to %s categories.', $wcb_jobcat_limit, 'wp-career-board' ),
			number_format_i18n( $wcb_jobcat_limit )
		),
		/* translators: %s: category name. */
		'removeFormat'  => __( 'Remove %s', 'wp-career-board' ),
		/* translators: %s: the job's current categories, comma separated. */
		'manyFormat'    => __( 'This job is listed under %s. Choosing a category here replaces them.', 'wp-career-board' ),
		'separator'     => _x( ', ', 'separator between category names', 'wp-career-board' ),
	)
);
wp_enqueue_script_module( '@wcb/job-category-field' );

$wcb_jobcat_context = (string) wp_json_encode(
	array(
		'form' => $wcb_jobcat_form,
	)
);
?>
<?php if ( 1 === $wcb_jobcat_limit ) : ?>
	<div class="wcb-form-field" data-wp-interactive="wcb-job-category-field" data-wp-context="<?php echo esc_attr( $wcb_jobcat_context ); ?>">
		<label class="wcb-form-label" for="<?php echo esc_attr( $wcb_jobcat_id ); ?>">
			<?php esc_html_e( 'Category', 'wp-career-board' ); ?>
		</label>
		<select
			id="<?php echo esc_attr( $wcb_jobcat_id ); ?>"
			class="wcb-field"
			aria-describedby="<?php echo esc_attr( $wcb_jobcat_id . '-many' ); ?>"
			data-wp-bind--value="state.first"
			data-wp-on--change="actions.pick"
		>
			<option value=""><?php esc_html_e( 'Select a category', 'wp-career-board' ); ?></option>
			<?php foreach ( $wcb_jobcat_terms as $wcb_jobcat_term ) : ?>
				<option value="<?php echo esc_attr( $wcb_jobcat_term->slug ); ?>"><?php echo esc_html( $wcb_jobcat_term->label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="wcb-form-help" id="<?php echo esc_attr( $wcb_jobcat_id . '-many' ); ?>" hidden data-wp-bind--hidden="!state.manyNote" data-wp-text="state.manyNote"></p>
	</div>
<?php else : ?>
	<fieldset class="wcb-form-field wcb-job-cats" data-wp-interactive="wcb-job-category-field" data-wp-context="<?php echo esc_attr( $wcb_jobcat_context ); ?>">
		<legend class="wcb-form-label wcb-job-cats__legend"><?php esc_html_e( 'Categories', 'wp-career-board' ); ?></legend>
		<p class="wcb-form-help" data-wp-text="state.counterText"></p>
		<ul class="wcb-job-cats__chips" aria-label="<?php esc_attr_e( 'Chosen categories', 'wp-career-board' ); ?>">
			<template data-wp-each--cat="state.chosen" data-wp-each-key="context.cat.slug">
				<li class="wcb-job-cats__chip">
					<span data-wp-text="context.cat.name"></span>
					<button type="button" class="wcb-job-cats__remove" data-wp-on--click="actions.remove" data-wp-bind--aria-label="state.removeLabel">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
					</button>
				</li>
			</template>
		</ul>
		<?php if ( count( $wcb_jobcat_terms ) > 12 ) : ?>
			<label class="screen-reader-text" for="<?php echo esc_attr( $wcb_jobcat_id . '-search' ); ?>"><?php esc_html_e( 'Search categories', 'wp-career-board' ); ?></label>
			<input
				type="search"
				id="<?php echo esc_attr( $wcb_jobcat_id . '-search' ); ?>"
				class="wcb-field wcb-job-cats__search"
				placeholder="<?php esc_attr_e( 'Search categories', 'wp-career-board' ); ?>"
				autocomplete="off"
				data-wp-on--input="actions.search"
			/>
		<?php endif; ?>
		<ul class="wcb-job-cats__list" id="<?php echo esc_attr( $wcb_jobcat_id ); ?>">
			<?php foreach ( $wcb_jobcat_terms as $wcb_jobcat_term ) : ?>
				<li
					class="<?php echo esc_attr( 'wcb-job-cats__option wcb-job-cats__option--depth-' . min( 3, (int) $wcb_jobcat_term->depth ) ); ?>"
					data-wp-context="<?php echo esc_attr( (string) wp_json_encode( array( 'slug' => $wcb_jobcat_term->slug ) ) ); ?>"
				>
					<label class="wcb-checkbox-label">
						<input
							type="checkbox"
							value="<?php echo esc_attr( $wcb_jobcat_term->slug ); ?>"
							data-wp-bind--checked="state.isChosen"
							data-wp-bind--disabled="state.isLocked"
							data-wp-on--change="actions.toggle"
						/>
						<span><?php echo esc_html( $wcb_jobcat_term->name ); ?></span>
						<?php echo \WCB\Core\Taxonomy::parent_note( $wcb_jobcat_term->parent_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
					</label>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( count( $wcb_jobcat_terms ) > 12 ) : ?>
			<?php echo \WCB\Core\Taxonomy::no_match_note(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
		<?php endif; ?>
		<p class="wcb-form-help" role="status" data-wp-text="state.limitText"></p>
	</fieldset>
<?php endif; ?>
