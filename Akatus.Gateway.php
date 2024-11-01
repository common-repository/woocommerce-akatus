<?php
/*
Plugin Name: WooCommerce Akatus
Plugin URI: http://johnhenrique.com/plugin/woocommerce-akatus/
Description: Integração Akatus para WooCommerce. A Akatus dá ao WooCommerce a habilidade de receber pagamentos via boleto, transferencia eletrônica e cartão. É obrigatório ter uma conta confirmada no Gateway Akatus com acesso a API, você pode criar a sua na página <a href="http://goo.gl/ICz2O">Akatus API</a>.
Version: 1.9
Author: John-Henrique
Author URI: http://johnhenrique.com/
License: GPL2


Requires at least: 3.5
Tested up to: 3.5.1
Text domain: wc-akatus
*/
/*  Copyright 2013  John-Henrique  (email : para@johnhenrique.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	
	add_action('plugins_loaded', 'woocommerce_gateway_akatus', 0);
	
	
	
	function woocommerce_gateway_akatus(){
		
		
		if( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
		/**
	 	 * Localisation
		 */
		load_plugin_textdomain('WC_Akatus', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
	    
		
		add_filter('woocommerce_payment_gateways', 'add_akatus_gateway' );
	
		
		class WC_Gateway_Akatus extends WC_Payment_Gateway {
			
			
			public function __construct() {
				global $woocommerce;
				
				$this->versao				= '1.9';
				
				
				$this->id 			= 'akatus';
		        $this->method_title = 'Akatus';
				$this->icon 		= apply_filters('woocommerce_akatus_icon', WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/imagens/akatus.png' );
				$this->has_fields 	= false;
				
				// Load the form fields.
				$this->init_form_fields();
				
				// Load the settings.
				$this->init_settings();
				
				// Define user set variables
				$this->title 		= $this->settings['title'];
				$this->description 	= $this->settings['description'];
				$this->nip 			= $this->settings['nip'];
				$this->key 			= $this->settings['key'];
				$this->email		= $this->settings['email'];
				$this->debug		= $this->settings['debug'];	
				$this->ambiente		= $this->settings['ambiente'];
				$this->payment_type	= $this->settings['payment_type'];
				
				// completa a URL RestFull
				if( ( $this->ambiente == 'dev' ) or ( $this->ambiente == '' ) ){
					$this->ambiente = 'dev';
				}else{
					$this->ambiente = 'www';
				}
				
				// Logs
				if ($this->debug=='yes') $this->log = $woocommerce->logger();
				
				if ( !$this->is_valid_currency() || !$this->are_token_set() )
					$this->enabled = false;
					
					
					
				
				
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
					// Pre 2.0
					add_action( 'woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
					add_action('init', array(&$this, 'notificacao' ));
					//add_action('init', array(&$this, 'fix_url_order_received' )); no work in WC 2.0
				} else {
					// 2.0
					add_action( 'woocommerce_update_options_payment_gateways_'. $this->id, array( $this, 'process_admin_options' ) );
					add_action( 'woocommerce_api_'. strtolower( get_class( $this ) ), array( $this, 'notificacao' ) );
				}
	
				// Actions
				add_action('woocommerce_receipt_akatus', array(&$this, 'receipt_page' ));
				
			}
			
			
			/**
		     * Initialise Gateway Settings Form Fields
		     */
		    public function init_form_fields() {
		    
		    	$this->form_fields = array(
					'enabled' => array(
									'title' => __( 'Enable/Disable', 'WC_Akatus' ), 
									'type' => 'checkbox', 
									'label' => __( 'Enable Akatus standard', 'WC_Akatus' ), 
									'default' => 'yes'
								), 
					'title' => array(
									'title' => __( 'Title', 'WC_Akatus' ), 
									'type' => 'text', 
									'description' => __( 'This controls the title which the user sees during checkout.', 'WC_Akatus' ), 
									'default' => __( 'Akatus', 'WC_Akatus' )
								),
					'description' => array(
									'title' => __( 'Description', 'WC_Akatus' ), 
									'type' => 'textarea', 
									'description' => __( 'This controls the description which the user sees during checkout.', 'WC_Akatus' ), 
									'default' => __("Pague com boleto bancário.", 'WC_Akatus')
								),
					'email' => array(
									'title' => __( 'Akatus Email', 'WC_Akatus' ), 
									'type' => 'text', 
									'description' => __( 'Please enter your Akatus email address; this is needed in order to take payment.', 'WC_Akatus' ), 
									'default' => ''
								),
					'key' => array(
									'title' => __( 'Akatus key', 'WC_Akatus' ), 
									'type' => 'text', 
									'description' => __( 'Please enter your Akatus token; this is needed in order to take payment. See <a href="https://www.akatus.com/painel/cart/token" target="_blank">Token of security</a>', 'WC_Akatus' ), 
									'default' => ''
								),
					'nip' => array(
									'title' => __( 'What is your nip code?', 'WC_Akatus' ), 
									'type' => 'text', 
									'description' => __( 'This is used to connect in Akatus API. Change only if you change in your Akatus account. See <a href="https://www.akatus.com/painel/cart/token" target="_blank">Transaction code</a>', 'WC_Akatus' ), 
									'default' => ''
								),
					'payment_type' => array(
									'title' => __( 'Qual meio de pagamento disponibilizar?', 'WC_Akatus' ), 
									'type' => 'select', 
									'options' => array(
										'boleto' => __( "Boleto bancário", 'WC_Akatus' ), 
										'cartao' => __( "Cartão de crédito", 'WC_Akatus' ), 
										'tef_itau' => __( "Transfêrencia eletrônica Itaú", 'WC_Akatus' ), 
										'tef_bradesco' => __( "Transfêrencia eletrônica Bradesco", 'WC_Akatus' )
									),
									'description' => __( 'This is used to choose what is the payment method to your store. See <a href="https://www.akatus.com/painel/payment_methods" target="_blank">Transaction code</a>', 'WC_Akatus' ), 
									'default' => 'boleto'
								),
					'ambiente' => array(
									'title' => __( 'Ambiente', 'WC_Akatus' ), 
									'type' => 'select', 
									'options' => array(
										'dev' => __( "Testes", 'WC_Akatus' ), 
										'www' => __( "Produção", 'WC_Akatus' ), 
									),
									'description' => __( 'Isto permite que você realize pagamentos fictícios, não gerando cobranças reais. Se sua loja ainda está em testes selecione "Testes".', 'WC_Akatus' ), 
									'default' => 'www'
								),
					'debug' => array(
									'title' => __( 'Enable/Disable Log', 'WC_Akatus' ), 
									'type' => 'checkbox', 
									'description' => __( 'Enable logging (<code>woocommerce/logs/akatus.txt</code>)', 'WC_Akatus' ), 
									'default' => ''
								),
					
					);
		    
		    } // End init_form_fields()
		    
		    
			/**
			 * Admin Panel Options 
			 * - Options for bits like 'title' and availability on a country-by-country basis
			 *
			 * @since 1.0.0
			 */
			public function admin_options() {
		
		    	?>
		    	<h3><?php echo __('Akatus XML Básico', 'WC_Akatus'); ?></h3>
		    	<p><?php echo __('É obrigatório ter uma conta confirmada no Gateway Akatus com acesso a API, você pode criar a sua na página <a href="http://goo.gl/ICz2O">Akatus API</a>.', 'WC_Akatus'); ?>
		    	<?php echo __( "Conheça outros <a href='http://woocommerce.com.br/'>plugins para WooCommerce</a>"); ?>
		    	</p>
		    	<table class="form-table">
					<?php if ( ! $this->is_valid_currency() ) : ?>
						<div class="inline error">
							<p><strong><?php _e( 'Gateway Disabled', 'WC_Akatus' ); ?></strong>: <?php _e( 'Akatus does not support your store\'s currency. You need to select the currency of Brazil Real.', 'WC_Akatus' ); ?></p>
						</div>
					<?php endif; ?>
					
					<?php if ( ! $this->are_credentials_set() ) : ?>
						<div class="inline error">
							<p><strong><?php _e( 'Gateway Disabled', 'WC_Akatus' ); ?></strong>: <?php _e( 'You must give the token of your account email.', 'WC_Akatus' ); ?></p>
						</div>
					<?php endif; ?>
		    	<?php
		    		// Generate the HTML For the settings form.
		    		$this->generate_settings_html();
		    	?>
				</table><!--/.form-table-->
		    	<?php
		    } // End admin_options()
		    
		    
		    
	
	
		    
			// para token
			protected function are_token_set(){
				if( empty( $this->key ) ):
					return false;
				endif;
				
				return true;
			}
			
			
		    
			/**
			 * Check if Akatus can be used with the store's currency.
			 * For now only work with Real of Brazil, but...
			 *
			 * @since 1.0
			 */
			function is_valid_currency() {
				if ( !in_array( get_option( 'woocommerce_currency' ), array( 'BRL' ) ) )
					return false;
				else
					return true;
			}
			
	
	
			/**
			 * Check if Akatus Credentials are set
			 *
			 * @since 1.0
			 */
			function are_credentials_set() {
				if( empty( $this->email ) || empty( $this->key ) || empty( $this->nip ) )
					return false;
				else
					return true;
			}
		    
		    
		    /**
			 * There are no payment fields for Akatus, but we want to show the description if set.
			 **/
		    function payment_fields() {
		    	if ($this->description) echo wpautop(wptexturize($this->description));
		    }
		    
		    
		    
		    
			
			/**
			 * Generate the Akatus button link
			 **/
		    public function receipt_page( $order_id ) {
				global $woocommerce;
				
				$order = &new WC_Order( $order_id );
				/*
				$post_id = $_GET['order'];
				
				$transacao_existente = get_post_meta( $post_id, 'akatus_transacao', true );
				if( $transacao_existente == '' ){

					// give me, please =)   (receive a token)
					$retorno = $this->processa_retorno( $this->request_token_API( $order ) );
				}else{
					
					// recupera a URL do carrinho anterior
					$retorno = get_post_meta( $post_id, 'akatus_url_retorno', true );
				}
				*/
				
				$this->pedido_id = $order->id;
				
				$retorno = $this->existe_transacao( $order );
				
			    if ($this->debug=='yes') $this->log->add( $this->id, 'Retorno de existe_transacao: '. print_r( $retorno, true ) );
				
				
				// token is false
				if( is_array( $retorno ) && ( $retorno['status'] == false ) ){
	
					$html  ="<h3>". __("Sorry, an error has occurred", 'WC_Akatus') ."</h3>";
					$html .="<p>". __("Try again, if the problem persists try to choose another payment method or contact the service.", 'WC_Akatus') ."</p>";
					$html .="<p class='error'>Informação: <strong>". $retorno['descricao'] ."</strong></p>";
					
				    if ($this->debug=='yes') $this->log->add( $this->id, 'Token inválido/falso: '. $retorno['descricao'] );
	
					
				} else {
					
					$woocommerce->add_inline_js('
					jQuery(function(){
						jQuery("body").block(
							{ 
								message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"'. __("Redirecting...", 'WC_Akatus') .'\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Akatus to make payment.', 'WC_Akatus').'", 
								overlayCSS: 
								{ 
									background: "#fff", 
									opacity: 0.6 
								},
								css: { 
							        padding:        20, 
							        textAlign:      "center", 
							        color:          "#555", 
							        border:         "3px solid #aaa", 
							        backgroundColor:"#fff", 
							        cursor:         "wait",
							        lineHeight:		"32px"
							    } 
							});
						jQuery("#submit_akatus_payment_form").click();
					});');
					
					
					// HTML to payment
					$html = '<form action="'. esc_url( $this->url_retorno( $retorno ) ) .'" method="post" id="akatus_payment_form">
								<input type="submit" class="button-alt" id="submit_akatus_payment_form" value="'.__('Pay via Akatus', 'WC_Akatus').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'WC_Akatus').'</a>
							</form>';
					
					
					if ($this->debug=='yes') $this->log->add( $this->id, 'Formulário de pagamento gerado com sucesso.' );
				}
				
				echo $html;
			}
			
			
			
			
			/**
			 * Process URL returned fixing the 
			 * DEV URL return
			 *
			 * @param URL $url
			 * @return URL fixed
			 */
			protected function url_retorno( $url = '' ){
				
				if( $this->ambiente == 'dev' ){
					
					if ($this->debug=='yes') $this->log->add( $this->id, 'Corrigindo URL de desenvolvimento.' );
					
					return str_replace( 'https://www.akatus.com', 'https://'. $this->ambiente .'.akatus.com', $url );
				}
				
				return $url;
			}
			
			
			
			public function request_token_API( $order ){
	
				$order->billing_phone = str_replace(array('(', '-', ' ', ')'), '', $order->billing_phone);
				$ddd = substr($order->billing_phone, 0, 2 );
				$telefone = substr($order->billing_phone, 2 );
				
				$xml = '
				<carrinho>
				    <recebedor>
				        <api_key>'. $this->key .'</api_key>
				        <email>'. $this->email .'</email>
				    </recebedor>
				    <pagador>
				        <nome>'. $order->billing_first_name ." ". $order->billing_last_name .'</nome>
				        <email>'. $order->billing_email .'</email>
				        <enderecos>
				            <endereco>
				                <tipo>entrega</tipo>
				                <logradouro>'. $order->billing_address_1 .'</logradouro>
				                <numero></numero>
				                <bairro>'. $order->billing_address_2 .'</bairro>
				                <cidade>'. $order->billing_city .'</cidade>
				                <estado>'. $order->billing_state .'</estado>
				                <pais>BRA</pais>
				                <cep>'. $order->billing_postcode .'</cep>
				            </endereco>
				        </enderecos>
				        <telefones>
				            <telefone>
				                <tipo>residencial</tipo>
				                <numero>'. $order->billing_phone .'</numero>
				            </telefone>
				        </telefones>
				    </pagador>
				    <produtos>
				    	'. $this->order_itens() .' 
				    </produtos>
				    <transacao>
				        <desconto_total>0</desconto_total>
				        <peso_total>0</peso_total>
				        <frete_total>0</frete_total>
				        <moeda>BRL</moeda>
				        <referencia>'. $order->id .'</referencia>
				        <meio_de_pagamento>'. $this->payment_type .'</meio_de_pagamento>
				    </transacao>
				    
				</carrinho>';
				/*
					
				        <produto>
				            <codigo>1</codigo>
				            <descricao>'. __( 'Pagamento do pedido #', 'WC_Akatus' ) . $order->id .'</descricao>
				            <quantidade>1</quantidade>
				            <preco>'. number_format( $order->order_total, 2, '.', '' ) .'</preco>
				            <peso>0</peso>
				            <frete>0</frete>
				            <desconto>0</desconto>
				        </produto>
				        
				    <transacao>
						<numero>NUMERO_DO_CARTAO_DE_CREDITO</numero>
						<parcelas>1</parcelas>
						<codigo_de_seguranca>643</codigo_de_seguranca>
						<expiracao>03/2015</expiracao>
						<desconto_total>20.00</desconto_total>
						<peso_total>17.0</peso_total>
						<frete_total>32.90</frete_total>
						<moeda>BRL</moeda>
						<referencia>abc1234</referencia>
						<meio_de_pagamento>cartao_master</meio_de_pagamento>
						<portador>
							<nome>NOME IMPRESSO NO CARTAO</nome>
							<cpf>CPF DO PORTADOR</cpf>
							<telefone>TELEFONE DO PORTADOR</telefone>
						</portador>
					</transacao>
					*/
				
				if($this->debug=='yes') $this->log->add( $this->id, 'XML '. $xml );
				
				
				$target = 'https://'. $this->ambiente .'.akatus.com/api/v1/carrinho.xml';
				
				if($this->debug=='yes') $this->log->add( $this->id, 'Ambiente: '. $this->ambiente );
				if($this->debug=='yes') $this->log->add( $this->id, 'URL: '. $target );
				
				
	        	$resposta = wp_remote_post( $target, array( 
	        		'method' 	=> 'POST', 
	        		'body' 		=> $xml, 
	        		'sslverify' => false, 
	        	) );
	        	
	        	
	        	if($this->debug=='yes') $this->log->add( $this->id, 'Requisitando token' );
	        	
	        	
	        	// verificando se tudo correu bem
	        	if( is_wp_error( $resposta ) ) {
	        		if($this->debug=='yes') $this->log->add( $this->id, 'Erro ao requisitar token: '. print_r( $resposta, true ) );
	        		
	        		return false;
				}else{
					if($this->debug=='yes') $this->log->add( $this->id, 'Retorno do token: '. print_r( $this->url_retorno( $resposta['body'] ), true ) );
				}
	        	
	        	
	        	if($this->debug=='yes') $this->log->add( $this->id, 'Requisição recebida' );
	        	
	        	
				return $resposta['body'];
			}
			
			
			

		    
		    /**
		     * Verifica se existe uma transação com o $order_id informado 
		     * caso exista retorna o URL para a transação 
		     * caso não exista, cria uma nova transação e retorna o URL
		     *
		     * @param Object order $order
		     * @return String URL da transação ou um Array com informações sobre o erro
		     */
		    protected function existe_transacao( $order ){
		    	global $post;
		    	

				$transacao = get_post_meta( $order->id, 'akatus_transacao', true );
				if ($this->debug=='yes') $this->log->add( $this->id, 'Transação existente: '. $transacao );
				
				
				// Verificando se o carrinho já foi utilizado
				if( $transacao == '' ){
					
					
					// requisita o token e processa o retorno
					$respostaXML = $this->processa_retorno( $this->request_token_API( $order ) );
					
					/**
					 * Caso o retorno seja um array iremos informar
					 * ao programa, pois trata-se de um erro 
					 * finalizamos o processamento do aplicativo
					 */
					if( is_array( $respostaXML ) ){
						return $respostaXML;
					}
					
					
					
					/**
					 * Não existia registros desta transação 
					 * então vamos cria-los
					 */
					if ($this->debug=='yes') $this->log->add( $this->id, 'Criando transação: '. $transacao );
					
					
					$carrinho 		= str_replace( '', '', $respostaXML->carrinho );
					$url_retorno 	= $this->url_retorno( $respostaXML->url_retorno );
					$transacao		= str_replace( '', '', $respostaXML->transacao );
					
					
					// código do carrinho na Akatus
					update_post_meta( $post->ID, 'akatus_carrinho', $carrinho );
					
					// código da transação na Akatus
					update_post_meta( $post->ID, 'akatus_transacao', $transacao );
					
					// Endereço do boleto na akatus
					update_post_meta( $post->ID, 'akatus_url_retorno', $url_retorno );
				}else{
					
					/**
					 * Existem registros desta transação 
					 * então vamos reutiliza-los
					 */
					if ($this->debug=='yes') $this->log->add( $this->id, 'Recuperando dados da transação: '. $transacao );
					
					
					// recupera a URL do carrinho anterior
					$url_retorno = get_post_meta( $post->ID, 'akatus_url_retorno', true );
				}
		    	
				
				return $url_retorno;
		    }
			
			
			
			
			/**
			 * Process data returned
			 *
			 * @param XML $resposta
			 * @return URL to redirect
			 */
			protected function processa_retorno( $resposta ){
				global $post;
				
				
				if ($this->debug=='yes') $this->log->add( $this->id, 'Processando XML retornado.' );
				
				
				// atalho
				$respostaXML = simplexml_load_string( $resposta );
				
				
				// houve algum erro?
				if( $respostaXML->status == 'erro' ){
					
					if($this->debug=='yes') $this->log->add( $this->id, 'Houve um erro: '. $respostaXML->descricao );
					
					// Descrição do erro
					return array( 'descricao' => $respostaXML->descricao, 'status' => false );
				}
				
				
				return $respostaXML;
			}
			
			
			
			
			/**
			 * Process the payment and return the result
			 * checkout
			 * checkout page
			 * 
			 **/
			function process_payment( $order_id = 0 ) {
				global $woocommerce;
				
				
				if ($this->debug=='yes') $this->log->add( $this->id, 'Processando pagamento (process_payment).' );
				
				
				if($this->debug=='yes') $this->log->add( $this->id, 'WooCommerce '. WOOCOMMERCE_VERSION );
				if($this->debug=='yes') $this->log->add( $this->id, 'WooCommerce '. get_class( $this ) .' '. $this->versao );
				
				
				$order = &new WC_Order( $order_id );
	
				// clean cart
				$woocommerce->cart->empty_cart();
				
				
				// no uncomment
				//$order->update_status('on-hold', __('Waiting payment', 'WC_Akatus' ));
				
				
				// Empty awaiting payment session
				if ( version_compare( WOOCOMMERCE_VERSION, '2.0', '<' ) ) {
					// Pre 2.0
					unset($_SESSION['order_awaiting_payment']);
				} else {
				// 2.0
					unset( $woocommerce->session->order_awaiting_payment );
				}
				
				
				
				// add note to control
				$order->add_order_note( __('Pedido recebido', 'WC_Akatus') );
				
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
				);
			}
			
			
			
			
			/**
			 * Notification of status payments
			 *
			 */
			function notificacao() {
				global $woocommerce;
				
				
				// Existe um POST?
				if( isset( $_POST ) ){
					
					$post = $_POST;
					
					// verificando se os dados vieram da Akatus
					if( isset( $post['token'] ) && isset( $post['transacao_id'] ) && isset( $post['status'] ) && isset( $post['referencia'] ) ){
						
						
						if ($this->debug=='yes') $this->log->add( $this->id, 'Recebendo POST do NIP' );
						if ($this->debug=='yes') $this->log->add( $this->id, 'Notificação de pagamento da Akatus recebida: '. print_r( $post, true ) );
						
						
						if ($this->debug=='yes') $this->log->add( $this->id, 'Referência recebida: '. print_r( $post['referencia'], true ) );
						
						
						// verificando se a transação existe
						$transacao = get_post_meta( $post['referencia'], 'akatus_transacao' );
						
						// localizando pedido
						$pedido = new WC_Order( $post['referencia'] );
						
						
						if( isset($pedido->order_custom_fields ) ){
							// removendo parte dos dados para melhorar a visualização
							unset( $pedido->order_custom_fields );
						}
						
						
						if ($this->debug=='yes') $this->log->add( $this->id, 'Transação encontrada: '. print_r( $pedido, true ) );
						
						
						/**
						 * verificando se a transação existe e 
						 * se o valor é identifico ao da loja
						 */
						if ( is_int( $pedido->id ) && $pedido->order_total == $post['valor_total'] ){
							
							
							if ($this->debug=='yes') $this->log->add( $this->id, 'O valor da transação está correto.' );
							
							
							if ($this->debug=='yes') $this->log->add( $this->id, 'Processando status do pagamento.' );
							// alterando o status
							$this->status_helper( $post['status'], $pedido );
						}else{
							
							if ($this->debug=='yes') $this->log->add( $this->id, 'Pedido #'. $post['referencia'] .': O valor recebido pelo NIP é diferente do valor cobrado na loja, status não alterado.' );
							if ($this->debug=='yes') $this->log->add( $this->id, 'Possível tentativa de burlar o pagamento' );
							
							wp_redirect( $this->get_return_url( $pedido ) );
							
						}
					}
				}
			}
			
			
			

			/**
			 * Helper to status of payments
			 *
			 * @param Status of gatewat $status
			 * @param unknown_type $order
			 * @return unknown
			 */
			protected function status_helper( $status = null, $order = null ){
				global $woocommerce;
				
				switch ( $status ){
					
					case "Aguardando Pagamento":
						$arrStatus['status'] = "pending"; // aguardando pagamento
						$arrStatus['log'] = __('Payment pending, Waiting payment confirmation', 'WC_Akatus'); // aguardando pagamento
						
						// add note to control
						$order->add_order_note( $arrStatus['log'] );
						break;
					
					case "Em Análise":
						$arrStatus['status'] = "pending"; // em analise
						$arrStatus['log'] = __('Payment in analysis, Waiting payment confirmation', 'WC_Akatus');
			    		
						// add note to control
						$order->add_order_note( $arrStatus['log'] );
						break;
					
					case "Aprovado":
						$arrStatus['status'] = "completed"; // paga
						$arrStatus['log'] = __('Manual confirmation of payment, Check payment confirmation', 'WC_Akatus');
						
			    		// change payment status
			    		$order->payment_complete();
						break;
					
					
					case "Cancelado":
						$arrStatus['status'] = "cancelled"; // cancelada
						$arrStatus['log'] = __('Order cancelled', 'WC_Akatus');
						
			    		// cancel this order 
			    		$order->cancel_order( $order->id );
						break;
						
					// improvavel mas nao custa prevenir
					default:
						$arrStatus['status'] = 'pending'; 
						$arrStatus['log'] = __('Payment pending, Waiting payment confirmation', 'WC_Akatus');
						
						// add note to control
						$order->add_order_note( $arrStatus['log'] );
				}
				
				
				// Adicionando o log
				if ($this->debug=='yes') $this->log->add( $this->id, 'Status do pagamento processado: '. $arrStatus['status'] );
				
				
				return $arrStatus;
			}
			
			
			
			protected function order_itens(){
				
				$item_loop = 0;
				$xml = '';
				
				$pedido = new WC_Order( $this->pedido_id );
				
				// Percorrendo Array de itens
				foreach ( $pedido->get_items( ) as $item_pedido ){
					
					// verificando se existem itens
					if( $item_pedido['qty'] ){
					
						$item_loop++;
						
						
						// Preço do produto
						$item_preco = $pedido->get_item_subtotal( $item_pedido, false );
						
						$xml .= '
				        <produto>
				            <codigo>'. $item_pedido['product_id'] .'</codigo>
				            <descricao>'. $item_pedido['name'] .'</descricao>
				            <quantidade>'. $item_pedido['qty'] .'</quantidade>
				            <preco>'. number_format( $item_preco, 2, '.', '' ) .'</preco>
				            <peso>0</peso>
				            <frete>0</frete>
				            <desconto>0</desconto>
				        </produto>
						';
					}
				}
				
				return $xml;
			}
			
			
		}
		
	}
	


	function add_akatus_gateway( $methods ){
	    $methods[] = 'WC_Gateway_Akatus'; return $methods;
	}

}


?>