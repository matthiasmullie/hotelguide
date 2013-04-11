<div id="infowindowData">
	<div id="infowindowContent">
		<h2>Contactformulier</h2>
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

				// form not yet submitted or submitted invalid; display form
				echo '
					<p>Vragen? Opmerkingen? Laat het ons weten!</p>
					<form name="contact" action="/contact.php" method="POST" class="'. $formClass .'">
						'. ( $submitted && !$valid ? '<p class="error">Gelieve het formulier volledig in te vullen.</p>' : '' ) .'
						<input type="text" name="name" id="name" placeholder="Naam"'. ( $name ? "value='$name'" : 'class="error"' ) .' />
						<input type="email" name="email" id="email" placeholder="E-mail adres"'. ( $email ? "value='$email'" : 'class="error"' ) .' />
						<textarea name="message" id="message" placeholder="Boodschap"'. ( $message ? '' : 'class="error"' ) .'>'. ( $message ?: '' ) .'</textarea>
						<input type="submit" class="button" id="submit" value="Verstuur" />
					</form>';
			} else {
				// form submitted; email to recipient & display thanks message
				$headers = "From: $name <$email>\r\n";
				mail( 'contact@last-minute-vakanties.be', 'Contactformulier LMV', $message, $headers );

				echo '<p>Bedankt voor je reactie!</p>';
			}
		?>
	</div>
</div>
