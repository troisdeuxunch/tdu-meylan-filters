<div>
	<label for="lm_diameter" class="tdu-mf-label"><?php esc_html_e('Diamètre', 'tdu-meylan-filters'); ?></label>
	<div style="display: flex; gap: 10px;">
		<select name="lm_diameter_min" id="lm_diameter_min" class="no-tom">
			<option value=""><?php esc_html_e('Diamètre minimum', 'tdu-meylan-filters'); ?></option>
			<?php foreach ($diameters as $diameter): ?>
				<option value="<?php echo esc_attr($diameter); ?>" <?php selected($diameter, tdu_get_current_diameter_min_query_var()); ?>>
					<?php echo esc_html($diameter); ?> mm
				</option>
			<?php endforeach; ?>
		</select>
		<select name="lm_diameter_max" id="lm_diameter_max" class="no-tom">
			<option value=""><?php esc_html_e('Diamètre maximum', 'tdu-meylan-filters'); ?></option>
			<?php foreach ($diameters as $diameter): ?>
				<option value="<?php echo esc_attr($diameter); ?>" <?php selected($diameter, tdu_get_current_diameter_max_query_var()); ?>>
					<?php echo esc_html($diameter); ?> mm
				</option>
			<?php endforeach; ?>
		</select>
	</div>
</div>
