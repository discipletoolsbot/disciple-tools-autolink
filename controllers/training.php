<?php

class Disciple_Tools_Autolink_Training_Controller extends Disciple_Tools_Autolink_Controller {

	public function show() {
		if ( ! $this->settings->training_enabled() ) {
			$this->functions->redirect_to_app();

			return;
		}

		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );
		$videos = $this->settings->training_videos_list();
		$action = "training";
		include __DIR__ . '/../templates/training.php';
	}
}
