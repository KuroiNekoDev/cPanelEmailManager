<?php
## cP_server_data
require('../../cP_config.php');

## prevent null/empty actions
if($json->action == '' || !isset($json->action) ) {
	echo 'no se ha recibido el parámetro de acción';
	die;
}


if($json->action == 'get_domains') {
	$response = $cPanel->uapi->DomainInfo->list_domains();

	$domain = array();
	$domain[0] = $response->data->main_domain;

	$total_domain = count($domain);
	$total_parked = count($response->data->parked_domains);
	$total_addons = count($response->data->addon_domains);

	$prk_count = 0; $add_count = 0;

	if( $total_parked > 0 ) {
		while($prk_count < $total_parked) {
			$domain[$total_domain] = $response->data->parked_domains[$prk_count];
			$total_domain++; 
			$prk_count++;
		}
	}

	if( $total_addons > 0 ) {
		while($add_count < $total_addons) {
			$domain[$total_domain] = $response->data->addon_domains[$add_count];
			$total_domain++; 
			$add_count++;
		}
	}

	$total_domains = count($domain);

	$list_count = 0;
	$options = '';

	while ($list_count < $total_domains) {
		$options .= "<option value='".$domain[$list_count]."'>".$domain[$list_count]."</option>\n";
		$list_count++;
	}

	if($options != '') {
		$data['success'] = true;
		$data['html'] = "<option value='' style='display:none;'> Elija el dominio...</option> \n" . $options;		
	} else {
		$data['msg'] = 'No se han encontrado dominios.';
	}

##-> new_account
} elseif($json->action == 'new_account') {

	$cPanel = $cPanel->uapi->Email->add_pop(
		array(
			'domain'	=> $json->email_domain,
			'email'		=> $json->email_name,
			'password'	=> $json->email_pass,
			'quota'		=> $json->email_quota
		));

	if( $cPanel->status == 1 ) {
		$data['success'] = true;
		$data['msg'] = 'La cuenta de correo se ha creado correctamente.';		
	} else {
		$data['msg'] = 'Hubo un problema al crear el correo. (cP error: '. $cPanel->errors[0] .' )';
	}

##-> list_emails
} elseif($json->action == 'get_emails') {

	$cPanel = $cPanel->uapi->Email->list_pops_with_disk(array('domain' => $json->domain ));

	$total_emails = count($cPanel->data);

	if( $total_emails > 0 ) {
		$email_counter = 0;
		$emails = array();
		while ($email_counter < $total_emails) {
			$email_user = $cPanel->data[$email_counter]->user;
			if( $cPanel->data[$email_counter]->suspended_login == 1 & $cPanel->data[$email_counter]->suspended_incoming == 1 ) {
				$suspended_bool = '1';
				$suspended_text = 'Inactivo';
			} else {
				$suspended_bool = '0';
				$suspended_text = 'Activo';
			}

			$emails[$email_counter] = array(
				'user'			=> $cPanel->data[$email_counter]->user,
				'email'			=> $cPanel->data[$email_counter]->email,
				'suspended_b'	=> $suspended_bool,
				'suspended_t'	=> $suspended_text,
				'disk_used'		=> $cPanel->data[$email_counter]->humandiskused,
				'mail_quota'	=> $cPanel->data[$email_counter]->humandiskquota,
				'percent_used'	=> $cPanel->data[$email_counter]->diskusedpercent
			);

			$email_counter++;
		}

		usort($emails, function($a, $b) { return strcmp($a['user'], $b['user']); });

		$email_list = '';
		foreach ($emails as $email_data) {
			$user			= $email_data['user'];
			$email_complete	= $email_data['email'];
			$suspended_b	= $email_data['suspended_b'];
			$suspended_t	= $email_data['suspended_t'];
			$total_quota	= $email_data['disk_used'] .'/'. $email_data['mail_quota'];
			$percent		= $email_data['percent_used'];

			if($suspended_b == 1) {
				$button_text  = 'Rectivar Cuenta';
				$button_icon  = 'play';
			} else {
				$button_text  = 'Desactivar Cuenta';
				$button_icon  = 'pause';
			}

			$email_list .= "
				<tr>\n
					<td>".$email_complete."</td>\n

					<td>\n
						<div class='progress'><div class='progress-bar' role='progressbar' style='width: ".$percent."%'></div></div>\n
						<small>".$total_quota."</small>\n
					</td>\n

					<td>".$suspended_t."</td>\n

					<td>\n
						<div class='btn-group'>\n
							<button class='btn btn-sm btn-info'
									title='Modificar Espacio'
									onclick='quota_modal(\"".$user."\")'>\n
								<i class='fa fa-inbox'></i></button>\n

							<button class='btn btn-sm btn-danger'
									title='Modificar Contraseña'
									onclick='pass_modal(\"".$user."\")'>\n
								<i class='fa fa-lock'></i></button>\n

							<button class='btn btn-sm btn-success'
									title='".$button_text."'
									onclick='status_modal(\"".$user."\", \"".$suspended_b."\")'>\n
								<i class='fa fa-".$button_icon."'></i></button>\n

							<button class='btn btn-sm btn-warning'
									title='Eliminar Cuenta'
									onclick='delete_modal(\"".$user."\")'>\n
								<i class='fa fa-trash'></i></button>\n
						</div>\n
					</td>\n
				</tr>\n
			";

		}

		$data['success'] = true;
		$data['html'] = $email_list;		

	} else {
		$data['msg'] = 'No se han creado cuentas de correo con el dominio indicado.';
	}

##-> change_quota
} elseif($json->action == 'change_quota') {

	$cPanel = $cPanel->uapi->Email->edit_pop_quota(
		array(
			'domain'	=> $json->domain,
			'email'		=> $json->user,
			'quota'		=> $json->quota
		));

	if( $cPanel->status == 1 ) {
		$data['success'] = true;
		$data['msg'] = 'Se ha modificado correctamente la capacidad.';		
	} else {
		$data['msg'] = 'No se puede modificar la capacidad. (cP error: '. $cPanel->errors[0] .' )';
	}


##-> change_pass
} elseif($json->action == 'change_pass') {

	$cPanel = $cPanel->uapi->Email->passwd_pop(
		array(
			'domain'	=> $json->domain,
			'email'		=> $json->user,
			'password'	=> $json->password
		));

	if( $cPanel->status == 1 ) {
		$data['success'] = true;
		$data['msg'] = 'Se ha modificado correctamente la contraseña.';		
	} else {
		$data['msg'] = 'No se puede modificar la contraseña. (cP error: '. $cPanel->errors[0] .' )';
	}


##-> change_status
} elseif($json->action == 'change_status') {

	if( $json->new_status == 1 ) {

		$cPanel_login = $cPanel->uapi->Email->suspend_login( array( 'email' => $json->user ));
		$cPanel_incoming = $cPanel->uapi->Email->suspend_incoming( array( 'email' => $json->user ));

		$stat = 0;
		if( $cPanel_login->status == 1 ) { $stat++; }
		if( $cPanel_incoming->status == 1 ) { $stat++; }

		if( $stat == 2 ) {
			$data['success'] = true;
			$data['msg'] = 'La cuenta se ha desactivado correctamente.';
		} else {
			$data['msg'] = 'No se pudo desactivar la cuenta.';
		}

	} else {

		$cPanel_login = $cPanel->uapi->Email->unsuspend_login( array( 'email' => $json->user ));
		$cPanel_incoming = $cPanel->uapi->Email->unsuspend_incoming( array( 'email' => $json->user ));

		$stat = 0;
		if( $cPanel_login->status == 1 ) { $stat++; }
		if( $cPanel_incoming->status == 1 ) { $stat++; }

		if( $stat == 2 ) {
			$data['success'] = true;
			$data['msg'] = 'La cuenta se ha reactivado correctamente.';
		} else {
			$data['msg'] = 'No se pudo reactivar la cuenta.';
		}

	}

##-> delete_email
} elseif($json->action == 'delete_email') {

	$cPanel = $cPanel->uapi->Email->delete_pop(
		array(
			'email'		=> $json->user,
			'domain'	=> $json->domain
		));

	if( $cPanel->status == 1 ) {
		$data['success'] = true;
		$data['msg'] = 'La cuenta se ha eliminado correctamente.';		
	} else {
		$data['msg'] = 'No se puede modificar la contraseña. (cP error: '. $cPanel->errors[0] .' )';
	}

}

#### return content 2 ajax
header('Content-type: application/json;');
header("HTTP/1.1 200 Success");
echo json_encode($data);

?>
