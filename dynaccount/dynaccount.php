<?php

if(!defined('_PS_VERSION_')) exit;

class Dynaccount extends Module {
	
	/**
	*	------------------------------------------------------
	*		USER-DEFINED VARIABLES START
	*	------------------------------------------------------
	*/
	
	//	Authentication values
	const DYNACCOUNT_API_ID 		= 0;
	const DYNACCOUNT_API_KEY 		= '';
	const DYNACCOUNT_API_SECRET 	= '';
	
	//	Translate country codes to regions (Country and region codes are case-sensitive)
	private $country = [
		'DE' => 'EU',
		'DK' => 'DK'
	];
	
	//	Define accounts for each region (Region codes are case-sensitive). If the accounts are non-existent in Dynaccount you must create them
	private $accounts = [
		//	Default/failover region (Do not delete!)
		'default' => [
			'sales'		=> 1100,
			'discounts'	=> 1300,
			'vat'		=> 14262,
			'shipping'	=> 1200
		],
		//	User-defined regions
		'DK' => [
			'sales'		=> 1100,
			'discounts'	=> 1300,
			'vat'		=> 14262,
			'shipping'	=> 1200
		],
		'EU' => [
			'sales'		=> 1100,
			'discounts'	=> 1300,
			'vat'		=> 14262,
			'shipping'	=> 1200
		]
	];
	
	//	Specifies the bookkeeping draft where sales will be booked. If the draft is non-existent in Dynaccount you must create it
	const DRAFT 		= 'Prestashop';
	
	//	Specifies the debtor group where customers will be stored. If the debtor group is non-existent in Dynaccount you must create it
	const DEBTOR_GROUP 	= 'Prestashop';
	
	//	Specifies the default currency
	const CURRENCY 		= 'DKK';
	
	/**
	*	------------------------------------------------------
	*		USER-DEFINED VARIABLES END
	*	------------------------------------------------------
	*/
	
	const VERSION 		= '1.1.15';
	
	const TBL_CACHE 	= 'dynaccount_cache';
	const TBL_LOG 		= 'dynaccount_log';
	
	public function __construct(){
		$this->name 			= 'dynaccount';
		$this->version 			= self::VERSION;
		$this->author 			= 'Dynaccount';
		$this->need_instance 	= 0;
		
		parent::__construct();
		
		$this->displayName = $this->l('Dynaccount');
		$this->description = $this->l('Dynaccount API integration');
	}
	
	public function install(){
		//	Prestashop v1.5
		if(Db::getInstance()->getRow("SELECT * FROM `"._DB_PREFIX_."hook` WHERE name='actionOrderStatusUpdate'")){
			if(!parent::install() || !$this->registerHook('actionOrderStatusUpdate')){
				return false;
			}
			
			//	Test
			/*if(!parent::install() || !$this->registerHook('actionProductAdd')){
				return false;
			}*/
		}
		//	Prestashop v1.4
		else{
			if(!parent::install() || !$this->registerHook('updateOrderStatus')){
				return false;
			}
			
			//	Test
			/*if(!parent::install() || !$this->registerHook('addproduct')){
				return false;
			}*/
		}
		
		$this->install_tabs();
		
		Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_.self::TBL_CACHE."` ("
			."`id` int(10) unsigned NOT NULL AUTO_INCREMENT,"
			."`is_booked` tinyint(1) unsigned NOT NULL,"
			."`time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,"
			."`order_id` int(10) unsigned NOT NULL,"
			."`cart_id` int(10) unsigned NOT NULL,"
			."`customer_id` int(10) unsigned NOT NULL,"
			."`address_id` int(10) unsigned NOT NULL,"
			."`country` char(2) NOT NULL,"
			."`invoice_number` int(10) unsigned NOT NULL,"
			."`total_paid` decimal(8,2) NOT NULL,"
			."`total_shipping` decimal(8,2) NOT NULL,"
			."`total_products` decimal(8,2) NOT NULL,"
			."`total_discounts` decimal(8,2) NOT NULL,"
			."`total_vat` decimal(8,2) NOT NULL,"
			."PRIMARY KEY (`id`),"
			."UNIQUE KEY `order_id` (`order_id`),"
			."KEY `is_booked` (`is_booked`))");
		
		Db::getInstance()->Execute("CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_.self::TBL_LOG."` ("
			."`id` int(10) unsigned NOT NULL AUTO_INCREMENT,"
			."`time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,"
			."`msg` varchar(255) NOT NULL,"
			."PRIMARY KEY (`id`))");
		
		return true;
	}
	
	//	Test Prestashop 1.5
	public function hookActionProductAdd($params){
		$this->order_confirmation(1);
		$this->update_cache();
	}
	
	//	Test Prestashop 1.4
	public function hookAddproduct($params){
		$this->order_confirmation(1);
		$this->update_cache();
	}
	
	//	Prestashop 1.5
	public function hookActionOrderStatusUpdate($params){
		switch($params['newOrderStatus']->id){
			case Configuration::get('PS_OS_SHIPPING'):
				$this->order_confirmation($params['id_order']);
				break;
			
			case Configuration::get('PS_OS_REFUND'):
				
				break;
		}
		
		$this->update_cache();
	}
	
	//	Prestashop 1.4
	public function hookUpdateOrderStatus($params){
		switch($params['newOrderStatus']->id){
			case Configuration::get('PS_OS_SHIPPING'):
				$this->order_confirmation($params['id_order']);
				break;
			
			case Configuration::get('PS_OS_REFUND'):
				
				break;
		}
		
		$this->update_cache();
	}
	
	private function order_confirmation($order_id){
		if($order_id){
			$order = new Order($order_id);
			$address = new Address($order->id_address_invoice);
			$country = new Country($address->id_country);
			
			$this->put_cache($order->id, [
				'cart_id'			=> $order->id_cart,
				'customer_id'		=> $order->id_customer,
				'address_id'		=> $order->id_address_invoice,
				'country'			=> $country->iso_code,
				'total_paid'		=> $order->total_paid,
				'total_products'	=> $order->total_products,
				'total_discounts'	=> 0 - (($order->total_products / $order->total_products_wt) * $order->total_discounts),
				'total_shipping'	=> $order->total_shipping,
				'total_vat'			=> $order->total_products_wt - $order->total_products - (1 - ($order->total_products / $order->total_products_wt)) * $order->total_discounts,
				'invoice_number'	=> $order->invoice_number
			]);
		}
		else{
			$this->log('Error: Invalid order id in order confirmation');
		}
	}
	
	private function update_cache_booked($id){
		Db::getInstance()->Execute("UPDATE `"._DB_PREFIX_.self::TBL_CACHE."` SET is_booked=1 WHERE id='$id'");
	}
	
	private function update_cache(){
		$sql = "SELECT * FROM `"._DB_PREFIX_.self::TBL_CACHE."` WHERE is_booked=0 ORDER BY id LIMIT 6";
		if($cache = Db::getInstance()->ExecuteS($sql)){
			try{
				require_once 'dynaccount_api\library\Dynaccount_webshop_API.php';
				$Dyn = new \Dynaccount\Webshop_API(self::DYNACCOUNT_API_ID, self::DYNACCOUNT_API_KEY, self::DYNACCOUNT_API_SECRET);
				
				$Dyn->connect();
				
				/*$debug = false;
				if($Dyn->show_request || $Dyn->show_response || $Dyn->show_url){
					$debug = true;
					echo '<pre>';
				}*/
				
				if($draft_id = $Dyn->get_draft_id(self::DRAFT)){
					if($debtor_group = $Dyn->get_debtor_group(self::DEBTOR_GROUP)){
						foreach($cache as $row){
							$customer = new Customer($row['customer_id']);
							$address = new Address($row['address_id']);
							
							$debtor = [
								'module_id_'		=> $customer->id,
								'module_group_name'	=> self::DEBTOR_GROUP,
								'payment_name'		=> $debtor_group['payment_name'],
								'name'				=> $customer->firstname.' '.$customer->lastname,
								'address'			=> $address->address1,
								'zip'				=> $address->postcode,
								'city'				=> $address->city,
								'ref_country_name'	=> $row['country'],
								'email'				=> $customer->email,
								'ref_currency_name'	=> self::CURRENCY
							];
							
							$Dyn->get_debtor($debtor);
							
							$voucher = [
								'time'	=> strtotime($row['time']),
								'txt'	=> 'Prestashop order: '.$row['order_id'].' (Cart '.$row['cart_id'].')'
							];
							
							$accounting = [
								'total_products'	=> $row['total_products'],
								'total_shipping'	=> $row['total_shipping'],
								'total_discounts'	=> $row['total_discounts'],
								'total_vat'			=> $row['total_vat'],
								'total_paid'		=> $row['total_paid']
							];
							
							if(empty($this->country[$row['country']])){
								$accounts = $this->accounts['default'];
							}
							else{
								$accounts = empty($this->accounts[$this->country[$row['country']]]) ? $this->accounts['default'] : $this->accounts[$this->country[$row['country']]];
							}
							
							$Dyn->put_enclosure($draft_id, $voucher, $accounting, $accounts, $customer->id);
							
							$this->update_cache_booked($row['id']);
							
							$this->log('Action: Order '.$row['order_id'].' booked');
						}
					}
				}
				
				/*if($debug){
					echo '</pre>';
					exit;
				}*/
				
				$Dyn->disconnect();
			}
			catch(\Dynaccount\Error $e){
				$this->log('Error: '.$e->getMessage());
			}
		}
	}
	
	private function log($msg){
		Db::getInstance()->Execute("INSERT `"._DB_PREFIX_.self::TBL_LOG."` SET msg='".addslashes($msg)."'");
	}
	
	private function write_log($var){
		$log_file = dirname(__FILE__).'/test.log';
		if(is_file($log_file)){
			chmod($log_file, 0777);
		}
		file_put_contents($log_file, serialize($var)."\n\n", FILE_APPEND);
	}
	
	private function put_cache($order_id, $order){
		$sql = "SELECT id FROM `"._DB_PREFIX_.self::TBL_CACHE."` WHERE order_id='$order_id'";
		if(Db::getInstance()->ExecuteS($sql)){
			$this->log("Error: Order $order_id is already cached");
		}
		else{
			$sql = "INSERT `"._DB_PREFIX_.self::TBL_CACHE."` SET "
				."order_id='$order_id',"
				."cart_id='".$order['cart_id']."',"
				."customer_id='".$order['customer_id']."',"
				."address_id='".$order['address_id']."',"
				."country='".$order['country']."',"
				."invoice_number='".$order['invoice_number']."',"
				."total_paid='".$order['total_paid']."',"
				."total_shipping='".($order['total_shipping'])."',"
				."total_products='".($order['total_products'])."',"
				."total_discounts='".($order['total_discounts'])."',"
				."total_vat='".($order['total_vat'])."'";
			Db::getInstance()->Execute($sql);
		}
	}
	
	public function uninstall(){
		Db::getInstance()->Execute("DROP TABLE `"._DB_PREFIX_.self::TBL_CACHE."`");
		Db::getInstance()->Execute("DROP TABLE `"._DB_PREFIX_.self::TBL_LOG."`");
		
		$tab = new Tab(Tab::getIdFromClassName('AdminDynaccountCache'));
		$tab->delete();
		
		$tab = new Tab(Tab::getIdFromClassName('AdminDynaccountLog'));
		$tab->delete();
		
		parent::uninstall();
	}
	
	private function install_tabs(){
		$parent_id = 0;
		//	Prestashop v1.5
		if($row = Db::getInstance()->getRow("SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='AdminParentOrders'")){
			$parent_id = $row['id_tab'];
		}
		//	Prestashop v1.4
		elseif($row = Db::getInstance()->getRow("SELECT id_tab FROM `"._DB_PREFIX_."tab` WHERE class_name='AdminOrders'")){
			$parent_id = $row['id_tab'];
		}
		
		if(!Tab::getIdFromClassName('AdminDynaccountCache')){
			$tab = new Tab();
			$tab->class_name = 'AdminDynaccountCache';
			$tab->id_parent = $parent_id;
			$tab->module = $this->name;
			$languages = Language::getLanguages(false);
			foreach($languages as $lang){
				$tab->name[$lang['id_lang']] = 'Dynaccount cache';
			}
			$tab->save();
		}
		
		if(!Tab::getIdFromClassName('AdminDynaccountLog')){
			$tab = new Tab();
			$tab->class_name = 'AdminDynaccountLog';
			$tab->id_parent = $parent_id;
			$tab->module = $this->name;
			$languages = Language::getLanguages(false);
			foreach($languages as $lang){
				$tab->name[$lang['id_lang']] = 'Dynaccount log';
			}
			$tab->save();
		}
	}
}