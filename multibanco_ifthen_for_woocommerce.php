<?php
/**
 * Plugin Name: Multibanco (IfthenPay gateway) for WooCommerce
 * Plugin URI: http://www.webdados.pt/produtos-e-servicos/internet/desenvolvimento-wordpress/multibanco-ifthen-software-gateway-woocommerce-wordpress/
 * Description: This plugin allows Portuguese costumers to pay WooCommerce orders with Multibanco (Pag. Serviços), using the IfthenPay gateway.
 * Version: 1.5.1
 * Author: Webdados
 * Author URI: http://www.webdados.pt
 * Text Domain: multibanco_ifthen_for_woocommerce
 * Domain Path: /lang
**/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Check if WooCommerce is active
 **/
// Get active network plugins - "Stolen" from Novalnet Payment Gateway
function mbifthen_active_nw_plugins() {
	if (!is_multisite())
		return false;
	$mbifthen_activePlugins = (get_site_option('active_sitewide_plugins')) ? array_keys(get_site_option('active_sitewide_plugins')) : array();
	return $mbifthen_activePlugins;
}
if (in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins')) || in_array('woocommerce/woocommerce.php', (array) mbifthen_active_nw_plugins())) {

	//Languages
	add_action('plugins_loaded', 'mbifthen_lang');
	function mbifthen_lang() {
		load_plugin_textdomain('multibanco_ifthen_for_woocommerce', false, dirname(plugin_basename(__FILE__)) . '/lang/');
	}
	
	function mbifthen_init() {
		
		if ( ! class_exists( 'WC_Multibanco_IfThen_Webdados' ) ) {
			class WC_Multibanco_IfThen_Webdados extends WC_Payment_Gateway {
				
				/**
				 * Constructor for your payment class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct() {
					global $woocommerce;

					$this->id = 'multibanco_ifthen_for_woocommerce';

					//Check version and upgrade

					// Logs
					$this->debug = ($this->get_option('debug')=='yes' ? true : false);
					if ($this->debug) $this->log = new WC_Logger();
					$this->debug_email = $this->get_option('debug_email');
					
					$this->version = '1.5.1';
					$this->upgrade();

	            	load_plugin_textdomain('multibanco_ifthen_for_woocommerce', false, dirname(plugin_basename(__FILE__)) . '/lang/');
	            	//$this->icon = WP_PLUGIN_URL."/".plugin_basename( dirname(__FILE__)) . '/images/icon.png';
	            	$this->icon = plugins_url('images/icon.png', __FILE__);
	            	$this->has_fields = false;
	            	$this->method_title = __('Pagamento de Serviços no Multibanco (Ifthen)', 'multibanco_ifthen_for_woocommerce');
					$this->secret_key = $this->get_option('secret_key');
					if (trim($this->secret_key)=='') {
						$this->secret_key=md5(home_url().time().rand(0,999));
					}
	            	$this->notify_url = str_replace( 'https:', 'http:', home_url( '/' ) ).'wc-api/WC_Multibanco_IfThen_Webdados/?chave=[CHAVE_ANTI_PHISHING]&entidade=[ENTIDADE]&referencia=[REFERENCIA]&valor=[VALOR]';

					//Plugin options and settings
					$this->init_form_fields();
					$this->init_settings();

					//User settings
					$this->title = $this->get_option('title');
					$this->description = $this->get_option('description');
					$this->ent = $this->get_option('ent');
					$this->subent = $this->get_option('subent');
					$this->only_portugal = $this->get_option('only_portugal');
					$this->only_above = $this->get_option('only_above');
					$this->only_bellow = $this->get_option('only_bellow');
			 
					// Actions and filters
					add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
					add_action('woocommerce_thankyou_'.$this->id, array($this, 'thankyou'));
					add_filter('woocommerce_available_payment_gateways', array($this, 'disable_unless_portugal'));
					add_filter('woocommerce_available_payment_gateways', array($this, 'disable_only_above_or_bellow'));
				 
					// Customer Emails
					add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2);

					// Payment listener/API hook
					add_action('woocommerce_api_wc_multibanco_ifthen_webdados', array($this, 'callback'));
					
				}

				/**
				 * Upgrades (if needed)
				 */
				function upgrade() {
					if ($this->get_option('version')<$this->version) {
						//Upgrade
						if ($this->debug) $this->log->add($this->id, 'Upgrade to '.$this->version.' started');
						if ($this->version=='1.0.1') {
							//Only change is to set the version on the database. It's done below
						}
						//Upgrade on the database - Risky?
						$temp=get_option('woocommerce_multibanco_ifthen_for_woocommerce_settings','');
						$temp['version']=$this->version;
						update_option('woocommerce_multibanco_ifthen_for_woocommerce_settings', $temp);
						if ($this->debug) $this->log->add($this->id, 'Upgrade to '.$this->version.' finished');
					}
				}

				/**
				 * Initialise Gateway Settings Form Fields
				 * 'setting-name' => array(
				 *		'title' => __( 'Title for setting', 'woothemes' ),
				 *		'type' => 'checkbox|text|textarea',
				 *		'label' => __( 'Label for checkbox setting', 'woothemes' ),
				 *		'description' => __( 'Description for setting' ),
				 *		'default' => 'default value'
				 *	),
				 */
				function init_form_fields() {
				
					$this->form_fields = array(
						'enabled' => array(
										'title' => __('Enable/Disable', 'woocommerce'), 
										'type' => 'checkbox', 
										'label' => __( 'Enable "Pagamento de Serviços no Multibanco" (using IfthenPay)', 'multibanco_ifthen_for_woocommerce'), 
										'default' => 'no'
									),
						'only_portugal' => array(
										'title' => __('Only for Portuguese customers?', 'multibanco_ifthen_for_woocommerce'), 
										'type' => 'checkbox', 
										'label' => __( 'Enable only for customers whose address is in Portugal', 'multibanco_ifthen_for_woocommerce'), 
										'default' => 'no'
									),
						'only_above' => array(
										'title' => __('Only for orders above', 'multibanco_ifthen_for_woocommerce'), 
										'type' => 'number', 
										'description' => __( 'Enable only for orders above x &euro; (exclusive). Leave blank (or zero) to allow for any order value.', 'multibanco_ifthen_for_woocommerce').' <br/> '.__( 'By design, Mulitibanco only allows payments from 1 to 999999 &euro; (inclusive). You can use this option to further limit this range.', 'multibanco_ifthen_for_woocommerce'), 
										'default' => ''
									),
						'only_bellow' => array(
										'title' => __('Only for orders bellow', 'multibanco_ifthen_for_woocommerce'), 
										'type' => 'number', 
										'description' => __( 'Enable only for orders bellow x &euro; (exclusive). Leave blank (or zero) to allow for any order value.', 'multibanco_ifthen_for_woocommerce').' <br/> '.__( 'By design, Mulitibanco only allows payments from 1 to 999999 &euro; (inclusive). You can use this option to further limit this range.', 'multibanco_ifthen_for_woocommerce'), 
										'default' => ''
									),
						'title' => array(
										'title' => __('Title', 'woocommerce' ), 
										'type' => 'text', 
										'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'), 
										'default' => __('Pagamento de Serviços no Multibanco', 'multibanco_ifthen_for_woocommerce')
									),
						'description' => array(
										'title' => __('Description', 'woocommerce' ), 
										'type' => 'textarea',
										'description' => __('This controls the description which the user sees during checkout.', 'woocommerce' ), 
										'default' => __('Easy and simple payment using "Pagamento de Serviços" at any "Multibanco" ATM terminal or your Home Banking service. (Only available to customers of Portuguese banks)', 'multibanco_ifthen_for_woocommerce')    
									),
						'ent' => array(
										'title' => __('Entity', 'multibanco_ifthen_for_woocommerce'), 
										'type' => 'number',
										'description' => __( 'Entity provided by IfthenPay when signing the contract. (E.g.: 10559, 11202, 11473, 11604)', 'multibanco_ifthen_for_woocommerce'), 
										'default' => ''    
									),
						'subent' => array(
										'title' => __('Subentity', 'multibanco_ifthen_for_woocommerce'), 
										'type' => 'number', 
										'description' => __('Subentity provided by IfthenPay when signing the contract. (E.g.: 999)', 'multibanco_ifthen_for_woocommerce'), 
										'default' => ''   
									),
						'secret_key' => array(
										'title' => __('Anti-phishing key', 'multibanco_ifthen_for_woocommerce'), 
										'type' => 'hidden', 
										'description' => '<b id="woocommerce_multibanco_ifthen_for_woocommerce_secret_key_label">'.$this->get_option('secret_key').'</b><br/>'.__('To ensure callback security, generated by the system and that must be provided to IfthenPay when asking for the callback activation.', 'multibanco_ifthen_for_woocommerce'), 
										'default' => $this->secret_key 
									),
						'debug' => array(
										'title' => __( 'Debug Log', 'woocommerce' ),
										'type' => 'checkbox',
										'label' => __( 'Enable logging', 'woocommerce' ),
										'default' => 'no',
										'description' => sprintf( __( 'Log plugin events, such as callback requests, inside <code>woocommerce/logs/multibanco_ifthen_for_woocommerce-%s.txt</code>', 'multibanco_ifthen_for_woocommerce' ), sanitize_file_name( wp_hash( $this->id ) ) ),
									),
						'debug_email' => array(
										'title' => __( 'Debug to email', 'multibanco_ifthen_for_woocommerce' ),
										'type' => 'email',
										'label' => __( 'Enable email logging', 'multibanco_ifthen_for_woocommerce' ),
										'default' => '',
										'description' => __( 'Send plugin events to this email address, such as callback requests.', 'multibanco_ifthen_for_woocommerce' ),
									)
						);
				
				}
				public function admin_options() {
					global $woocommerce;
					?>
					<h3><?php echo $this->method_title; ?> <span style="font-size: 75%;">v.<?php echo $this->version; ?></span></h3>
					<p><b><?php _e('In order to use this plugin you <u>must</u>:', 'multibanco_ifthen_for_woocommerce'); ?></b></p>
					<ul class="wc_ifthen_list">
						<li><?php printf( __('Set WooCommerce currency to <b>Euros (&euro;)</b> %1$s', 'multibanco_ifthen_for_woocommerce'), '<a href="admin.php?page=woocommerce_settings&tab=general">&gt;&gt;</a>.'); ?></li>
						<li><?php printf( __('Sign a contract with %1$s. To get more informations on this service go to %2$s.', 'multibanco_ifthen_for_woocommerce'), '<b><a href="http://www.ifthenpay.com" target="_blank">IfthenPay</a></b>', '<a href="https://www.ifthensoftware.com/ProdutoX.aspx?ProdID=5" target="_blank">https://www.ifthensoftware.com/ProdutoX.aspx?ProdID=5</a>'); ?></li>
						<li><?php printf( __('Ask IfthenPay to activate "Callback" on your account using this exact URL: %1$s and this Anti-phishing key: %2$s', 'multibanco_ifthen_for_woocommerce'), '<br/><code><b>'.$this->notify_url.'</b></code><br/>', '<br/><code><b>'.$this->secret_key.'</b></code>'); ?></li>
						<li><?php _e('Fill in all details (entity and subentity) provided by <b>IfthenPay</b> on the fields bellow.', 'multibanco_ifthen_for_woocommerce'); ?>
					</ul>
					<p><?php printf( __('Please be aware that this is not an official plugin by <b>IfthenPay</b>. Do not ask them for support on using it. Please refer to %1$s for technical support.', 'multibanco_ifthen_for_woocommerce'), '<b><a href="http://www.webdados.pt/produtos-e-servicos/internet/desenvolvimento-wordpress/multibanco-ifthen-software-gateway-woocommerce-wordpress/" target="_blank">Webdados</a></b>'); ?></p>
					<hr/>
					<script type="text/javascript">
					jQuery(document).ready(function(){
						if (jQuery('#woocommerce_multibanco_ifthen_for_woocommerce_secret_key').val()=='') {
							jQuery('#woocommerce_multibanco_ifthen_for_woocommerce_secret_key').val('<?php echo $this->secret_key; ?>');
							jQuery('#woocommerce_multibanco_ifthen_for_woocommerce_secret_key_label').html('<?php echo $this->secret_key; ?>');
							jQuery('#mainform').submit();
						}
					});
					</script>
					<table class="form-table">
					<?php
					if (trim(get_woocommerce_currency())=='EUR') {
						$this->generate_settings_html();
					} else {
						?>
						<p><b><?php _e('ERROR!', 'multibanco_ifthen_for_woocommerce'); ?> <?php printf( __('Set WooCommerce currency to <b>Euros (&euro;)</b> %1$s', 'multibanco_ifthen_for_woocommerce'), '<a href="admin.php?page=woocommerce_settings&tab=general">'.__('here', 'multibanco_ifthen_for_woocommerce').'</a>.'); ?></b></p>
						<?php
					}
					?>
					</table>
					<style type="text/css">
						.wc_ifthen_list {
							list-style-type: disc;
							list-style-position: inside;
						}
						.wc_ifthen_list li {
							margin-left: 1.5em;
						}
					</style>
					<?php
				}

				/**
				 * Thank you page
				 */
				function thankyou($order_id) {
					$order = new WC_Order($order_id);
					?>
					<style type="text/css">
						table.multibanco_ifthen_for_woocommerce_table {
							width: auto !important;
							margin: auto;
						}
						table.multibanco_ifthen_for_woocommerce_table td,
						table.multibanco_ifthen_for_woocommerce_table th {
							background-color: #FFFFFF;
							color: #000000;
							padding: 10px;
							vertical-align: middle;
							white-space: nowrap;
						}
						table.multibanco_ifthen_for_woocommerce_table th {
							text-align: center;
							font-weight: bold;
						}
						table.multibanco_ifthen_for_woocommerce_table th img {
							margin: auto;
							margin-top: 10px;
						}
					</style>
					<table class="multibanco_ifthen_for_woocommerce_table" cellpadding="0" cellspacing="0">
						<tr>
							<th colspan="2">
								<?php _e('Payment instructions', 'multibanco_ifthen_for_woocommerce'); ?>
								<br/>
								<img src="<?php echo plugins_url('images/banner.png', __FILE__); ?>" alt="<?php echo esc_attr($this->title); ?>" title="<?php echo esc_attr($this->title); ?>"/>
							</th>
						</tr>
						<?php
							$ref = $this->get_ref($order->id);
							if (is_array($ref)) { ?>
							<tr>
								<td><?php _e('Entity', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
								<td><?php echo $ref['ent']; ?></td>
							</tr>
							<tr>
								<td><?php _e('Reference', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
								<td><?php echo chunk_split($ref['ref'], 3, ' '); ?></td>
							</tr>
							<tr>
								<td><?php _e('Value', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
								<td><?php echo $order->order_total; ?> &euro;</td>
							</tr>
							<tr>
								<td colspan="2" style="font-size: small;"><?php _e('The receipt issued by the ATM machine is a proof of payment. Keep it.', 'multibanco_ifthen_for_woocommerce'); ?></td>
							</tr>
						<?php } else { ?>
							<tr>
								<td><b><?php _e('Error', 'multibanco_ifthen_for_woocommerce'); ?>:</b></td>
								<td><?php echo $ref; ?></td>
							</tr>
						<?php } ?>
					</table>
					<?php
				}


				/**
				 * Email instructions
				 */
				function email_instructions($order, $sent_to_admin) {
					if ( $order->payment_method !== $this->id) return;
					switch ($order->status) {
						case 'on-hold':
						case 'pending':
							?>
							<table cellpadding="10" cellspacing="0" align="center" border="0" style="margin: auto; margin-top: 10px; margin-bottom: 10px; border-collapse: collapse; border: 1px solid #1465AA; border-radius: 4px !important; background-color: #FFFFFF;">
								<tr>
									<td style="border: 1px solid #1465AA; border-top-right-radius: 4px !important; border-top-left-radius: 4px !important; text-align: center; color: #000000; font-weight: bold;" colspan="2">
										<?php _e('Payment instructions', 'multibanco_ifthen_for_woocommerce'); ?>
										<br/>
										<img src="<?php echo plugins_url('images/banner.png', __FILE__); ?>" alt="<?php echo esc_attr($this->title); ?>" title="<?php echo esc_attr($this->title); ?>" style="margin-top: 10px;"/>
									</td>
								</tr>
								<?php
									$ref = $this->get_ref($order->id);
									if (is_array($ref)) { ?>
									<tr>
										<td style="border: 1px solid #1465AA; color: #000000;"><?php _e('Entity', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
										<td style="border: 1px solid #1465AA; color: #000000; white-space: nowrap;"><?php echo $ref['ent']; ?></td>
									</tr>
									<tr>
										<td style="border: 1px solid #1465AA; color: #000000;"><?php _e('Reference', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
										<td style="border: 1px solid #1465AA; color: #000000; white-space: nowrap;"><?php echo chunk_split($ref['ref'], 3, ' '); ?></td>
									</tr>
									<tr>
										<td style="border: 1px solid #1465AA; color: #000000;"><?php _e('Value', 'multibanco_ifthen_for_woocommerce'); ?>:</td>
										<td style="border: 1px solid #1465AA; color: #000000; white-space: nowrap;"><?php echo $order->order_total; ?> &euro;</td>
									</tr>
									<tr>
										<td style="font-size: x-small; border: 1px solid #1465AA; border-bottom-right-radius: 4px !important; border-bottom-left-radius: 4px !important; color: #000000; text-align: center;" colspan="2"><?php _e('The receipt issued by the ATM machine is a proof of payment. Keep it.', 'multibanco_ifthen_for_woocommerce'); ?></td>
									</tr>
								<?php } else { ?>
									<tr>
										<td><b><?php _e('Error', 'multibanco_ifthen_for_woocommerce'); ?>:</b></td>
										<td><?php echo $ref; ?></td>
									</tr>
								<?php } ?>
							</table>
							<?php
							break;
						case 'processing':
							?>
							<p><b><?php _e('Multibanco payment received.', 'multibanco_ifthen_for_woocommerce'); ?></b> <?php _e('We will now process your order.', 'multibanco_ifthen_for_woocommerce'); ?></p>
							<?php
							break;
						default:
							return;
							break;
					}
    			}

				/**
				 * Process it
				 */
				function process_payment($order_id) {
					global $woocommerce;
					$order=new WC_Order($order_id);
					// Mark as on-hold
					$order->update_status('on-hold', __('Awaiting Multibanco payment.', 'multibanco_ifthen_for_woocommerce'));
					// Reduce stock levels
					//$order->reduce_order_stock();  //No we don't!
					// Remove cart
					$woocommerce->cart->empty_cart();
					// Empty awaiting payment session
					unset($_SESSION['order_awaiting_payment']);
					// Return thankyou redirect
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url($order)
					);
				}

				/**
				 * Just for Portugal
				 */
				function disable_unless_portugal($available_gateways) {
					global $woocommerce;
					if (isset($available_gateways[$this->id])) {
						if (trim($available_gateways[$this->id]->only_portugal)=='yes' && trim($woocommerce->customer->get_country())!='PT') unset($available_gateways[$this->id]);
					}
					return $available_gateways;
				}

				/**
				 * Just above/bellow certain amounts
				 */
				function disable_only_above_or_bellow($available_gateways) {
					global $woocommerce;
					if (isset($available_gateways[$this->id])) {
						if (@floatval($available_gateways[$this->id]->only_above)>0) {
							if($woocommerce->cart->total<floatval($available_gateways[$this->id]->only_above)) {
								unset($available_gateways[$this->id]);
							}
						} 
						if (@floatval($available_gateways[$this->id]->only_bellow)>0) {
							if($woocommerce->cart->total>floatval($available_gateways[$this->id]->only_bellow)) {
								unset($available_gateways[$this->id]);
							}
						} 
					}
					return $available_gateways;
				}


				/**
				 * Get/Create Reference
				 */
				function get_ref($order_id) {
					$order=new WC_Order($order_id);

					if (trim(get_woocommerce_currency())=='EUR') {
						$meta_values=get_post_meta($order->id);
						if (
							!empty($meta_values['_'.$this->id.'_ent'][0])
							&&
							!empty($meta_values['_'.$this->id.'_ref'][0])
						) {
							//Already created, return it!
							return array(
								'ent' => $meta_values['_'.$this->id.'_ent'][0],
								'ref' => $meta_values['_'.$this->id.'_ref'][0]
							);
						} else {
							//Value ok?
							if ($order->order_total<1){
								return __('It\'s not possible to use Multibanco to pay values under 1&euro;.', 'multibanco_ifthen_for_woocommerce');
						 	} else {
						 		//Value ok? (again)
								if ($order->order_total>=1000000){
									return __('It\'s not possible to use Multibanco to pay values above 999999&euro;.', 'multibanco_ifthen_for_woocommerce');
								} else {
									//Create a new reference
									if(trim(strlen($this->ent))==5 && trim(strlen($this->subent))<=3 && intval($this->ent)>0 && intval($this->subent)>0 && trim($this->secret_key)!=''){
										//$ref=$this->create_ref($this->ent, $this->subent, 0, $order->order_total); //For incremental mode
										$ref=$this->create_ref($this->ent, $this->subent, rand(0,9999), $order->order_total); //For random mode - Less loop possibility
										//Store on the order for later use (like the email)
										update_post_meta($order->id, '_'.$this->id.'_ent', $this->ent);
										update_post_meta($order->id, '_'.$this->id.'_ref', $ref);
										//Return the motherfucker!
										return array(
											'ent' => $this->ent,
											'ref' => $ref
										);
									} else {
										return __('Configuration error. This payment method is disabled because required information was not set.', 'multibanco_ifthen_for_woocommerce');
									}
								}
							}
						}
					} else {
						return __('Configuration error. This store currency is not Euros (&euro;).', 'multibanco_ifthen_for_woocommerce');
					}
				}
				function create_ref($ent, $subent, $seed, $total) {
					$subent=str_pad(intval($subent), 3, "0", STR_PAD_LEFT);
					$seed=str_pad(intval($seed), 4, "0", STR_PAD_LEFT);
					$chk_str=sprintf('%05u%03u%04u%08u', $ent, $subent, $seed, round($total*100));
					$chk_array=array(3, 30, 9, 90, 27, 76, 81, 34, 49, 5, 50, 15, 53, 45, 62, 38, 89, 17, 73, 51);
					$chk_val=0;
					for ($i = 0; $i < 20; $i++) {
						$chk_int = substr($chk_str, 19-$i, 1);
						$chk_val += ($chk_int%10)*$chk_array[$i];
					}
					$chk_val %= 97;
					$chk_digits = sprintf('%02u', 98-$chk_val);
					$ref=$subent.$seed.$chk_digits;
					//Does it exists already? Let's browse the database!
					$exists=false;
					$args = array(
						'meta_query' => array(
							array(
								'key' => '_'.$this->id.'_ent',
								'value' => $ent
							),
							array(
								'key' => '_'.$this->id.'_ref',
								'value' => $ref
							)
						),
						'post_type' => 'shop_order',
						'posts_per_page' => -1
					);
					$the_query = new WP_Query($args);
					if ($the_query->have_posts()) $exists=true;
					wp_reset_postdata();
					if ($exists) {
						//Reference exists - Let's try again
						//$seed=intval($seed)+1; //For incremental mode
						$seed=rand(0,9999); //For random mode - Less loop possibility
						$ref=$this->create_ref($ent, $subent, $seed, $total);
					}
					return $ref;
				}

				/**
				 * Callback
				 *
				 * @access public
				 * @return void
				 */
				function callback() {
					@ob_clean();
					//We must 1st check the situation and then process it and send email to the store owner in case of error.
					if (isset($_GET['chave'])
						&&
						isset($_GET['entidade'])
						&&
						isset($_GET['referencia'])
						&&
						isset($_GET['valor'])
					) {
						//Let's process it
						if ($this->debug) $this->log->add($this->id, '- Callback ('.$_SERVER['REQUEST_URI'].') with all arguments from '.$_SERVER['REMOTE_ADDR']);
						$ref=trim(str_replace(' ', '', $_GET['referencia']));
						$ent=trim($_GET['entidade']);
						$val=floatval($_GET['valor']);
						if (trim($_GET['chave'])==trim($this->secret_key)
							&&
							is_numeric($ref)
							&&
							strlen($ref)==9
							&&
							is_numeric($ent)
							&&
							strlen($ent)==5
							&&
							$val>=1
							) {
							$args = array(
								'post_type'	=> 'shop_order',
								'post_status' => 'publish',
								'posts_per_page' => -1,
								'tax_query' => array(
									array(
									'taxonomy' => 'shop_order_status',
									'field' => 'slug',
									'terms' => array('on-hold', 'pending')
									)
								),
								'meta_query' => array(
									array(
										'key'=>'_'.$this->id.'_ent',
										'value'=>$ent,
										'compare'=>'LIKE'
									),
									array(
										'key'=>'_'.$this->id.'_ref',
										'value'=>$ref,
										'compare'=>'LIKE'
									)
								)
							);
							$the_query = new WP_Query($args);
							if ($the_query->have_posts()) {
								if ($the_query->post_count==1) {
									while ( $the_query->have_posts() ) : $the_query->the_post();
										$order = new WC_Order( $the_query->post->ID );
									endwhile;
									if ($val==floatval($order->order_total)) {
										//We must first change the order status to "pending" and then to "processing" or no email will be sent to the client
										include_once(ABSPATH.'wp-admin/includes/plugin.php' );
										if (!is_plugin_active('order-status-emails-for-woocommerce/order-status-emails-for-woocommerce.php')) //Only if this plugin is not active
											if ($order->status!='pending') $order->update_status('pending', __('Temporary status. Used to force an email on the next order status change.', 'multibanco_ifthen_for_woocommerce'));
										$order->reduce_order_stock(); //Now we reduce the stock
										$order->update_status('processing', __('Multibanco payment received.', 'multibanco_ifthen_for_woocommerce')); //Paid
										header('HTTP/1.1 200 OK');
										if ($this->debug) $this->log->add($this->id, '-- Multibanco payment received');
										if (trim($this->debug_email)!='') wp_mail(trim($this->debug_email), $this->id.' - Multibanco payment received', 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - Multibanco payment received');
										echo 'OK - Multibanco payment received';
									} else {
										header('HTTP/1.1 200 OK');
										if ($this->debug) $this->log->add($this->id, '-- Error: The value does not match');
										if (trim($this->debug_email)!='') wp_mail(trim($this->debug_email), $this->id.' - Error: The value does not match', 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - The value does not match');
										echo 'Error: The value does not match';
									}
								} else {
									header('HTTP/1.1 200 OK');
									if ($this->debug) $this->log->add($this->id, '-- Error: More than 1 order found awaiting payment with these details');
									if (trim($this->debug_email)!='') wp_mail(trim($this->debug_email), $this->id.' - Error: More than 1 order found awaiting payment with these details', 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - More than 1 order found awaiting payment with these details');
									echo 'Error: More than 1 order found awaiting payment with these details';
								}
							} else {
								header('HTTP/1.1 200 OK');
								if ($this->debug) $this->log->add($this->id, '-- Error: No orders found awaiting payment with these details');
								if (trim($this->debug_email)!='') wp_mail(trim($this->debug_email), $this->id.' - Error: No orders found awaiting payment with these details', 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) from '.$_SERVER['REMOTE_ADDR'].' - No orders found awaiting payment with these details');
								echo 'Error: No orders found awaiting payment with these details';
							}
						} else {
							//header("Status: 400");
							if ($this->debug) $this->log->add($this->id, '-- Argument errors');
							if (trim($this->debug_email)!='') wp_mail(trim($this->debug_email), $this->id.' - Error: Callback with argument errors', 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) with argument errors from '.$_SERVER['REMOTE_ADDR']);
							wp_die('Argument errors'); //Sends 500
						}
					} else {
						//header("Status: 400");
						if ($this->debug) $this->log->add($this->id, '- Callback ('.$_SERVER['REQUEST_URI'].') with missing arguments from '.$_SERVER['REMOTE_ADDR']);
						if (trim($this->debug_email)!='') wp_mail(trim($this->debug_email), $this->id.' - Error: Callback with missing arguments', 'Callback ( '.$_SERVER['HTTP_HOST'].' '.$_SERVER['REQUEST_URI'].' ) with missing arguments from '.$_SERVER['REMOTE_ADDR']);
						wp_die('Error: Something is missing...'); //Sends 500
					}
				}

			}
    	}
    }
	add_action( 'plugins_loaded', 'mbifthen_init', 0);
	

	/* Add to WooCommerce */
	function mbifthen_add( $methods ) {
		$methods[] = 'WC_Multibanco_IfThen_Webdados'; 
		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'mbifthen_add' );

	/* Order metabox to show Multibanco payment details */
	function mbifthen_order_add_meta_box() {
		add_meta_box('multibanco_ifthen_for_woocommerce', __('Multibanco payment details', 'multibanco_ifthen_for_woocommerce'), 'mbifthen_order_meta_box_html', 'shop_order', 'side', 'core');
	}
	function mbifthen_order_meta_box_html($post) {
		$order=new WC_Order($post->ID);
		$meta_values=get_post_meta($order->id);
		if (
			!empty($meta_values['_multibanco_ifthen_for_woocommerce_ent'][0])
			&&
			!empty($meta_values['_multibanco_ifthen_for_woocommerce_ref'][0])
		) {
			echo '<p>'.__('Entity', 'multibanco_ifthen_for_woocommerce').': '.trim($meta_values['_multibanco_ifthen_for_woocommerce_ent'][0]).'</p>';
			echo '<p>'.__('Reference', 'multibanco_ifthen_for_woocommerce').': '.chunk_split(trim($meta_values['_multibanco_ifthen_for_woocommerce_ref'][0]), 3, ' ').'</p>';
			echo '<p>'.__('Value', 'multibanco_ifthen_for_woocommerce').': '.$order->order_total.'</p>';
		} else {
			echo '<p>'.__('No details available', 'multibanco_ifthen_for_woocommerce').'</p>';
		}
	}
	add_action('add_meta_boxes', 'mbifthen_order_add_meta_box');

	/* Allow searching orders by reference */
	function mbifthen_shop_order_search( $search_fields ) {
		$search_fields[] = '_multibanco_ifthen_for_woocommerce_ref';
		return $search_fields;
	}
	add_filter('woocommerce_shop_order_search_fields', 'mbifthen_shop_order_search');

	/* If you're reading this you must know what you're doing ;-) Greetings from sunny Portugal! */
	
}