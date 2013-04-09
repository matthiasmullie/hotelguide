<div id="infowindowData">
	<div id="infowindowContent">
		<h2>Contactformulier</h2>
		<p>Vragen? Opmerkingen? Laat het ons weten!</p>
		<?php
			$submitted = isset( $_POST['name'] ) && isset( $_POST['email'] ) && isset( $_POST['message'] );

			$name = isset( $_POST['name'] ) ? $_POST['name'] : '';
			$email = filter_var( isset( $_POST['email'] ) ? $_POST['email'] : '', FILTER_VALIDATE_EMAIL );
			$message = isset( $_POST['message'] ) ? $_POST['message'] : '';

			$valid = $name && $email && $message;

			if ( !$submitted || !$valid ) {
				// form not yet submitted or submitted invalid; display form
				echo '
					<form name="contact" action="/serverside/contact.php" method="POST"'. ( $submitted ? 'class="submitted"' : '' ) .'>
						<input type="text" name="name" id="name" placeholder="Naam"'. ( $name ? "value='$name'" : 'class="error"' ) .' />
						<input type="email" name="email" id="email" placeholder="E-mail adres"'. ( $email ? "value='$email'" : 'class="error"' ) .' />
						<textarea name="message" id="message" placeholder="Boodschap"'. ( $message ? '' : 'class="error"' ) .'>'. ( $message ?: '' ) .'</textarea>
						<a href="#" class="button" id="submit"><span>Verstuur</span></a>
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
