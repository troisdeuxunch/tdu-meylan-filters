<div>
	<select name="lm_brand" onchange="this.form.submit()">
		<option value="" <?php if($current_brand == '') echo 'selected'; ?>>Sélectionner une marque</option>
		<?php foreach($brands as $brand) : ?>
			<option value="<?php echo $brand->slug; ?>" <?php if($brand->slug == $current_brand) echo 'selected'; ?>>
				<?php echo $brand->name; ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>