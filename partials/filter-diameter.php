<div>
	<label for="lm_diameter">Diametre</label>
	<div style="display: flex; gap: 10px;">
		<select name="lm_diameter_min" id="lm_diameter_min">
			<option value="">Diamètre minimum</option>
			<?php foreach ($diameters as $diameter): ?>
				<option value="<?php echo esc_attr($diameter); ?>" <?php selected($diameter, tdu_get_current_diameter_min_query_var()); ?>>
					<?php echo esc_html($diameter); ?> mm
				</option>
			<?php endforeach; ?>
		</select>
		<select name="lm_diameter_max" id="lm_diameter_max">
			<option value="">Diamètre maximum</option>
			<?php foreach ($diameters as $diameter): ?>
				<option value="<?php echo esc_attr($diameter); ?>" <?php selected($diameter, tdu_get_current_diameter_max_query_var()); ?>>
					<?php echo esc_html($diameter); ?> mm
				</option>
			<?php endforeach; ?>
		</select>
	</div>
</div>