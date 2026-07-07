<div>
	<label for="lm_brand" class="tdu-mf-label"><?php esc_html_e('Marque', 'tdu-meylan-filters'); ?></label>
	<select name="lm_brand" onchange="this.form.submit()" class="no-tom">
		<option value="" <?php if($current_brand == '') echo 'selected'; ?>><?php esc_html_e('Sélectionner une marque', 'tdu-meylan-filters'); ?></option>
		<?php foreach($brands as $brand) : ?>
			<option value="<?php echo $brand->slug; ?>" <?php if($brand->slug == $current_brand) echo 'selected'; ?>>
				<?php echo $brand->name; ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>
