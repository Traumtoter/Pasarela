<?php
/**
 * Plugin Name: Pasarela
 * Plugin URI: localhost
 * Author Name: Elder y Jose Alejandro
 * Author URI: localhost
 * Description: Plugin de pasarela de pago para woocomerce.
 * Version: 1.0.0
*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'misha_add_gateway_class' );
function misha_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_ej_Gateway';
	return $gateways;
}
add_action( 'plugins_loaded', 'ej_init_gateway_class' );
function ej_init_gateway_class() {

	class WC_ej_Gateway extends WC_Payment_Gateway {

 		public function __construct() {
            $this->id = 'aletienda';
            $this->icon = '';
            $this->has_fields = true; 
            $this->method_title = 'Pasarela de woocommerce';
            $this->method_description = 'Pasarela de pago';
        
            $this->supports = array(
                'products'
            );

            $this->init_form_fields();
        
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
            $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
    
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
 		}

 		public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Habilitar/Deshabilitar',
                    'label'       => 'Habilitar Pasarela de pago LOCALHOST',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Titulo',
                    'type'        => 'text',
                    'description' => 'Descriocion del pago.',
                    'default'     => 'Tarjeta de credito/debito',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Descripción',
                    'type'        => 'textarea',
                    'description' => 'Descriocion del pago.',
                    'default'     => 'Puede realizar su pago por medio de esta pasarela, utilizando tarjeta de debito o credito, gracias.',
                ),
                'testmode' => array(
                    'title'       => 'Modo de prueba',
                    'label'       => 'Habilitar modo de prueba',
                    'type'        => 'checkbox',
                    'description' => 'Porfavor colocar la API de prueba.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'private key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'public key',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'public key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'private key.',
                    'type'        => 'password'
                )
            );
	 	}
		public function payment_fields() {
 
            if ( $this->description ) {
                
                if ( $this->testmode ) {
                    $this->description .= 'Se esta usando el modo de prueba.';
                    $this->description  = trim( $this->description );
                }
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
        
            do_action( 'woocommerce_credit_card_form_start', $this->id );
        
            echo '<div class="form-row form-row-wide"><label>Numero de Tarjeta <span class="required">*</span></label>
                <input id="ej_ccNo" type="text" autocomplete="off">
                </div>
                <div class="form-row form-row-first">
                    <label>Fecha de expiración <span class="required">*</span></label>
                    <input id="ej_expdate" type="text" autocomplete="off" placeholder="MM / YY">
                </div>
                <div class="form-row form-row-last">
                    <label>Codigo de tarjeta (CVC) <span class="required">*</span></label>
                    <input id="ej_cvv" type="password" autocomplete="off" placeholder="CVC">
                </div>
                <div class="clear"></div>';
        
            do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';
        
				 
		}

	 	public function payment_scripts() {
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }
            if ( 'no' === $this->enabled ) {
                return;
            }

            if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
                return;
            }
            if ( ! $this->testmode && ! is_ssl() ) {
                return;
            }
            wp_enqueue_script( 'misha_js', 'https://comercio.free.beeceptor.com/pago' );
            wp_register_script( 'woocommerce_misha', plugins_url( 'misha.js', __FILE__ ), array( 'jquery', 'misha_js' ) );
            wp_localize_script( 'woocommerce_misha', 'misha_params', array(
                'publishableKey' => $this->publishable_key
            ) );

            wp_enqueue_script( 'woocommerce_misha' );
	
	 	}

		public function validate_fields() {

            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }
            return true;
		}

		public function process_payment( $order_id ) {

            global $woocommerce;
            $order = wc_get_order( $order_id );
            $args = array(
        
            );
            $response = wp_remote_post( '{payment processor endpoint}', $args );
        
        
            if( !is_wp_error( $response ) ) {
        
                $body = json_decode( $response['body'], true );
        
                if ( $body['response']['responseCode'] == 'APPROVED' ) {
                    $order->payment_complete();
                    $order->reduce_order_stock();

                    $order->add_order_note( 'Su orden a sudo procesada por nuestra pasarela, gracias por su compra!', true );
        
                    $woocommerce->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url( $order )
                    );
        
                } else {
                    wc_add_notice(  'Please try again.', 'error' );
                    return;
                }
        
            } else {
                wc_add_notice(  'Connection error.', 'error' );
                return;
            }
	 	}
		public function webhook() {
            			
	 	}
 	}
}
