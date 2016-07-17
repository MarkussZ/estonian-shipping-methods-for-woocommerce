<tr class="wc_shipping_smartpost">
	<th><label for="<?php echo $field_id ?>"><?php _e( 'Choose timewindow', 'wc-estonian-shipping-methods' ) ?></label></th>
	<td>
		<select name="<?php echo $field_name ?>" id="<?php echo $field_id ?>">
			<option value="" <?php selected( $selected, '' ); ?>><?php _ex( '- Choose timewindow -', 'empty value label for courier', 'wc-estonian-shipping-methods' ) ?></option>
		<?php foreach( $windows as $value => $window ) : ?>
			<option value="<?php echo $value ?>" <?php selected( $selected, $value ); ?>><?php echo $window ?></option>
		<?php endforeach; ?>
		</select>
	</td>
</tr>