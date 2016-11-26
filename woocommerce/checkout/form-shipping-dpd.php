<tr class="wc_shipping_dpd">
	<th><label for="<?php echo $field_id ?>"><?php _e( 'Choose terminal', 'wc-estonian-shipping-methods' ) ?></label></th>
	<td>
		<select name="<?php echo $field_name ?>" id="<?php echo $field_id ?>">
			<option value="" <?php selected( $selected, '' ); ?>><?php _ex( '- Choose terminal -', 'empty value label for terminals', 'wc-estonian-shipping-methods' ) ?></option>
		<?php foreach( $terminals as $group_name => $locations ) : ?>
			<optgroup label="<?php echo $group_name ?>">
				<?php foreach( $locations as $location ) : ?>
				<option value="<?php echo $location->place_id ?>" <?php selected( $selected, $location->place_id ); ?>><?php echo $location->name ?></option>
				<?php endforeach; ?>
			</optgroup>
		<?php endforeach; ?>
		</select>
	</td>
</tr>