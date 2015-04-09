<?php

// Multibanco IfThen - Email payment instructions filter
add_filter('multibanco_ifthen_email_instructions_table_html', 'my_multibanco_ifthen_email_instructions_table_html', 1, 4);
function my_multibanco_ifthen_email_instructions_table_html($html, $ref, $ent, $order_total) {
	ob_start();
	?>
	<h2>Multibanco payment instructions</h2>
	<p>
		<b>Reference:</b> <?php echo $ref; ?>
		<br/>
		<b>Entity:</b> <?php echo $ent; ?>
		<br/>
		<b>Value:</b> <?php echo $order_total; ?>
	</p>
	<?php
	return ob_get_clean();
}

// Multibanco IfThen Thank you page payment instructions filter
add_filter('multibanco_ifthen_thankyou_instructions_table_html', 'my_multibanco_ifthen_thankyou_instructions_table_html', 1, 4);
function my_multibanco_ifthen_thankyou_instructions_table_html($html, $ref, $ent, $order_total) {
	ob_start();
	?>
	<h2>Multibanco payment instructions</h2>
	<p>
		<b>Reference:</b> <?php echo $ref; ?>
		<br/>
		<b>Entity:</b> <?php echo $ent; ?>
		<br/>
		<b>Value:</b> <?php echo $order_total; ?>
	</p>
	<?php
	return ob_get_clean();
}

?>