<?php

class AdminDynaccountLog extends AdminTab {
	public function __construct(){
		$this->table = 'dynaccount_log';
		$this->className = 'dynaccount_log';
		$this->lang = false;
		$this->edit = false;
		$this->delete = false;
		$this->fieldsDisplay = array(
			'id' => array(
				'title' => $this->l('ID'),
				'align' => 'center'
			),
			'time' => array(
				'title' => $this->l('Date'),
				'type' => 'datetime'
			),
			'msg' => array(
				'title' => $this->l('Message'),
			)
		);
		
		$this->identifier = 'id';
		
		parent::__construct();
	}
}