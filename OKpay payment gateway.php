<?php
/*
Plugin Name: Okaypay for Woocommerce
Plugin URI: #
Description: Okaypay checkout.
Version: 0.1
Author: saadat khodarahmi 
Author URI: http://www.takrang.ir
*/
add_action('plugins_loaded', 'woocommerce_Okaypay_init', 0);

function woocommerce_Okaypay_init() {

class WC_Okaypay extends WC_Payment_Gateway {

    public function __construct() { 
		$this->id				= 'Okaypay';
		$this->icon 			= apply_filters('woocommerce_bacs_icon',  plugins_url('logo.png', __FILE__));
		$this->has_fields 		= false;
		$this->method_title     = __( 'Okay Pay', 'woocommerce' );
		$this->init_form_fields();
		$this->init_settings();
		$this->title 			= $this->settings['title'];
		$this->description      = $this->settings['description'];
		$this->okpayid   		= $this->settings['okpayid'];

		$this->notify_url 		= trailingslashit(home_url()); 
		
		add_action('init', array(&$this, 'check_Okaypay_response'));
		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
    	add_action('woocommerce_thankyou_Okaypay', array(&$this, 'thankyou_page'));
    	add_action('woocommerce_receipt_Okaypay', array(&$this, 'receipt_page'));

    } 


    function init_form_fields() {
    
    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ), 
							'type' => 'checkbox', 
							'label' => __( 'Enable Okaypay', 'woocommerce' ), 
							'default' => 'yes'
						), 
			'title' => array(
              'title' => __( 'Title', 'woocommerce' ), 
              'type' => 'text', 
              'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ), 
              'default' => __( 'OkayPay', 'woocommerce' )
            ),
			'okpayid' => array(
							'title' => __( '* OKPay E-Mail Address or Wallet ID', 'woocommerce' ), 
							'type' => 'text', 
							'description' => __( 'The seller e-mail address or wallet id to use for accepting OKPay payments.', 'woocommerce' ), 
							'default' => ''
						),
			'description' => array(
					'title' => __( 'Description', 'woocommerce' ),
					'type' => 'textarea',
					'default' => __('Pay via OkPay', 'woocommerce')
				),

			);
    
    } 
    
    function check_Okaypay_response(){
            global $woocommerce;
            if(isset($_REQUEST['ok_invoice']) && isset($_REQUEST['okpaynotify'])){
               
                $order_id = $_REQUEST['ok_invoice']/256;
                if($order_id != ''){
                    try{
						$order = new woocommerce_order($order_id);
						$amount = $_POST["ok_item_1_price"];
						$status = $_POST["ok_txn_status"];
						$transid = $_POST["ok_txn_id"];
						$fee = $_POST["ok_txn_fee"];
						if($_REQUEST['okpaynotify'] == '1' AND $_POST['ok_txn_net'] == $_POST['ok_item_1_price'] AND $_POST['ok_reciever'] == $this->okpayid){
							
								$request = 'ok_verify=true';
								foreach ($_POST as $key => $value) {
								  $request .= '&' . $key . '=' . urlencode(stripslashes($value));
								}
								if (extension_loaded('curl')) {
								  $ch = curl_init('https://www.okpay.com/ipn-verify.html');
								  curl_setopt($ch, CURLOPT_POST, true);
								  curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
								  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
								  curl_setopt($ch, CURLOPT_HEADER, false);
								  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
								  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
								  $response = curl_exec($ch);
								  if (strcmp($response, 'VERIFIED') == 0 || $_POST['ok_txn_status'] == 'completed') {
								  
										if($order->status !=='completed'){
											if($order->status == 'processing'){

											}else{
												$order->payment_complete();
												$order->add_order_note('Okaypay payment successful<br/>Bank Ref Number: '.$transid);
												$order->add_order_note("Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.");
												$woocommerce->cart->empty_cart();

											}
										}
								  }
								  else {
									logTransaction($GATEWAY["name"], $_POST, "Unsuccessful");
								  }
								  curl_close($ch);
								}
								else {
								  $header = 'POST /ipn-verify.html HTTP/1.0' . "\r\n";
								  $header = 'Host: www.okpay.com' . "\r\n";
								  $header .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
								  $header .= 'Content-Length: ' . strlen($request) . "\r\n";
								  $header .= 'Connection: close' . "\r\n\r\n";
								  $fp = fsockopen('www.okpay.com', 80, $errno, $errstr, 30);
								  if ($fp) {
									fputs($fp, $header . $request);
									while (!feof($fp)) {
									  $response = fgets($fp, 1024);
									  if (strcmp($response, 'VERIFIED') == 0 || $_POST['ok_txn_status'] == 'completed') {
									  
										//addInvoicePayment($invoiceid, $transid, $amount, $fee, $gatewaymodule);
										//logTransaction($GATEWAY["name"], $_POST, "Successful");
										if($order->status !=='completed'){
											if($order->status == 'processing'){

											}else{
												$order->payment_complete();
												$order->add_order_note('Okaypay payment successful<br/>Bank Ref Number: '.$transid);
												$order->add_order_note("Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.");
												$woocommerce->cart->empty_cart();

											}
										}
									  }
									  else {
 											if($order->status !=='completed'){
												if($order->status == 'processing'){

												}else{
													$order -> update_status('failed');
													$order -> add_order_note('Failed');
													$order -> add_order_note("Payment failed in bank");
												}
											}
									  }
									}
									fclose($fp);
								  }
								
								}
								/*
								if($_REQUEST['V2_HASH']==$hash AND $order->order_total==$_REQUEST['PAYMENT_AMOUNT']){
									if($order->status !=='completed'){
										if($order->status == 'processing'){

										}else{
											$order->payment_complete();
											$order->add_order_note('Perfect Money payment successful<br/>Bank Ref Number: '.$_REQUEST['PAYMENT_BATCH_NUM']);
											$order->add_order_note("Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.");
											$woocommerce->cart->empty_cart();

										}
									}
								}		
								*/
						}elseif($_REQUEST['okpaynotify'] == '2'){
								if($order->status !=='completed'){
									if($order->status == 'processing'){

                                    }else{
										$order -> update_status('failed');
										$order -> add_order_note('Failed');
										$order -> add_order_note("Payment failed in bank");
                                    }
								}
						
						}
                        
      
					}catch(Exception $e){
                            // $errorOccurred = true;
                            $msg = "Error";
                        }

                }



            }



    }	
	
	public function admin_options() {
    	?>
    	<h3><?php _e('Okay pay', 'woocommerce'); ?></h3>
    	<p><?php _e('Okaypay Payment ', 'woocommerce'); ?></p>
		<?php if(!function_exists("hash")){
		echo 'Hash must exist on your server';
		}
		?>
    	<table class="form-table">
    	<?php
    		$this->generate_settings_html();
    	?>
		</table><!--/.form-table-->
    	<?php
    } 


    function payment_fields() {
      if ($this->description) echo wpautop(wptexturize($this->description));
	  
    }

    function thankyou_page() {
		if ($this->description) echo wpautop(wptexturize($this->description));
		
		?><h2><?php _e('Our Details', 'woocommerce') ?></h2><ul class="order_details ppay_details"><?php
		
		$fields = array(
			'ppay_number'=> __('Okaypay', 'woocommerce')
		);
		
		foreach ($fields as $key=>$value) :
		    if(!empty($this->$key)) :
		    	echo '<li class="'.$key.'">'.$value.': <strong>'.wptexturize($this->$key).'</strong></li>';
		    endif;
		endforeach;
		
		?></ul><?php
    }
	
	
	
    function receipt_page( $order ) {

			echo '<p>'.__('Thank you for your order, please click the button below to pay with Okaypay.', 'woocommerce').'</p>';
	
			echo $this->generate_Okaypay_form( $order );
	
	}
	
	
	function generate_Okaypay_form( $order_id ) {
			//$this->get_return_url( $order )
            global $woocommerce;
            $order = &new woocommerce_order($order_id);
			$params = array('ok_receiver' => $this->okpayid  ,
                      'ok_currency' => 'USD',
                      'ok_invoice' => $order_id*256,
					  'ok_fees' => '1',
                      'ok_language' => 'EN',
					  'ok_item_1_name'=> $order_id.' Order number',
					  'ok_item_1_price' =>$order->order_total,
                      'ok_ipn' =>  $this->notify_url.'?okpaynotify=1',
                      'ok_return_success' => $this->get_return_url( $order ),
                      'ok_return_fail' => $this->notify_url.'?okpaynotify=2');
            $lr_args_array = array();
            foreach($params as $key => $value){
                $lr_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
            }
			
			$woocommerce->add_inline_js('
				jQuery("body").block({
						message: "<img src=\"' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif' ) ) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Okpay to make payment.', 'woocommerce').'",
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						centerY: false,
						css: {
							top:			"20%",
							padding:        20,
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"32px"
						}
					});
				jQuery("#submit_okpay_payment_form").click();				
			');
			
			return '<form id="okpaysubmit"  action="https://www.okpay.com/process.html" method="POST" name="process" target="_top">' . implode('', $lr_args_array) . '
					<input type="submit" class="button-alt" id="submit_okpay_payment_form" value="'.__('Pay OkayPay', 'woocommerce').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woocommerce').'</a>
				</form>';
	
	}


    function process_payment( $order_id ) {
    	global $woocommerce;
    	
		$order = new WC_Order( $order_id );
		
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
		);
    }

}


	function woocommerce_add_Okaypay_gateway($methods) {
		$methods[] = 'WC_Okaypay';
		return $methods;
	}

	add_filter('woocommerce_payment_gateways', 'woocommerce_add_Okaypay_gateway' );
}
