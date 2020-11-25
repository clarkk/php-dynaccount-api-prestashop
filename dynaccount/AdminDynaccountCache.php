<?php

class AdminDynaccountCache extends AdminTab {
	public function __construct(){
		$this->table = 'dynaccount_cache';
		$this->className = 'dynaccount_cache';
		$this->lang = false;
		$this->edit = false;
		$this->delete = false;
		$this->fieldsDisplay = array(
			'id' => array(
				'title' => $this->l('ID'),
				'align' => 'center'
			),
			'is_booked' => array(
				'title' => $this->l('Booked'),
				'type' => 'bool'
			),
			'time' => array(
				'title' => $this->l('Date'),
				'type' => 'datetime'
			),
			'country' => array(
				'title' => $this->l('Country'),
				'align' => 'center'
			),
			'order_id' => array(
				'title' => $this->l('Order ID'),
				'align' => 'right'
			),
			'cart_id' => array(
				'title' => $this->l('Cart ID'),
				'align' => 'right'
			),
			'total_vat' => array(
				'title' => $this->l('VAT'),
				'align' => 'right'
			),
			'total_paid' => array(
				'title' => $this->l('Total'),
				'align' => 'right'
			)
		);
		
		$this->identifier = 'id';
		
		parent::__construct();
	}
}