<?php
	$submitted = isset( $_POST['name'] ) && isset( $_POST['email'] ) && isset( $_POST['message'] );

	$name = isset( $_POST['name'] ) ? $_POST['name'] : '';
	$email = filter_var( isset( $_POST['email'] ) ? $_POST['email'] : '', FILTER_VALIDATE_EMAIL );
	$message = isset( $_POST['message'] ) ? $_POST['message'] : '';

	$valid = $name && $email && $message;

	if ( !$submitted || !$valid ) {
		$formClass = 'infowindow';
		$formClass .= ( $submitted ? ' submitted' : '' );
		$formClass .= ( $valid ? ' valid' : '' );

		$args = array(
			'formClass' => $formClass,
			'formErrorMessage' => ( $submitted && !$valid ? '<p class="error" data-l10n-id="formError"></p>' : '' ),
			'nameValue' => ( $name ? $name : '' ),
			'nameClass' => ( $name ? '' : 'error' ),
			'emailValue' => ( $email ? $email : '' ),
			'emailClass' => ( $email ? '' : 'error' ),
			'messageValue' => ( $message ? $message : '' ),
			'messageClass' => ( $message ? '' : 'error' ),
		);
	} else {
		// form submitted; email to recipient & display thanks message
		$headers = "From: $name <$email>\r\n";
		mail( 'contact@last-minute-vakanties.be', 'Contactformulier LMV', $message, $headers );
	}
?>
<div id="infowindowData">
	<div id="infowindowContent"
		 data-l10n-id="<?php echo ( !$submitted || !$valid ? 'infowindowContact' : 'infowindowContactSubmitted' ); ?>"
		 <?php echo ( isset( $args ) ? "data-l10n-args='". json_encode( $args ) ."'" : '' ); ?>
	></div>
</div>
