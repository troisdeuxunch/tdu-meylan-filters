<?php if(count($watch_types) > 0): ?>
<div>
	<label for="lm_type" class="tdu-mf-label"><?php esc_html_e('Type de montre', 'tdu-meylan-filters'); ?></label>
	<select name="lm_type" id="lm_type" class="no-tom">
		<option value=""><?php esc_html_e('Tous les types', 'tdu-meylan-filters'); ?></option>
		<?php foreach ($watch_types as $watch_type): ?>
			<option value="<?php echo esc_attr($watch_type->slug); ?>" <?php selected($watch_type->slug, tdu_get_current_type_query_var()); ?>>
				<?php echo esc_html($watch_type->name); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
<?php endif; ?>
