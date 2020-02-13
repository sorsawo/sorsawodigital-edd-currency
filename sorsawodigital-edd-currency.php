<?php
/*
Plugin Name: EDD Currency
Description: Adds custom currency to Easy Digital Downloads.
Version: 1.0.9
Author: Sorsawo Team
Author URI: https://sorsawo.com
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

class Sorsawo_Digital_EDD_Currency {
    private static $_instance = NULL;
    public $admin_page = NULL;
    public $action = NULL;
    
    /**
     * retrieve singleton class instance
     * @return instance reference to plugin
     */
    public static function get_instance() {
        if ( NULL === self::$_instance ) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }
    
    /**
     * Initialize all variables, filters and actions
     */
    private function __construct() {
        if ( isset( $_REQUEST['action'] ) && -1 != $_REQUEST['action'] ) {
            $this->action = $_REQUEST['action'];
        }

        if ( isset( $_REQUEST['action2'] ) && -1 != $_REQUEST['action2'] ) {
            $this->action = $_REQUEST['action2'];
        }
            
        if ( is_admin() ) {            
            add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 1 );
            add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        }
        
        add_filter( 'edd_currencies', array( $this, 'add_currency' ) );
        add_filter( 'edd_currency_symbol', array( $this, 'add_currency_simbol' ), 10, 2 );
        
        $_currencies = get_option( 'sorsawodigital_edd_currency_settings', array() );
        
        if ( $_currencies !== array() ) {
            foreach ( $_currencies as $code => $info ) {
                add_filter( 'edd_' . strtolower( $code ) . '_currency_filter_before', array( $this, 'add_currency_filter_before' ), 10, 3 );
                add_filter( 'edd_' . strtolower( $code ) . '_currency_filter_after', array( $this, 'add_currency_filter_after' ), 10, 3 );
            }
        }
    }
    
    /**
     * Creates the dashboard page
     */
    public function register_admin_menu() {
        $this->admin_page = add_submenu_page( 'edit.php?post_type=download', __( 'Manage Currencies', 'sorsawodigital-edd-currency' ), __( 'Manage Currencies', 'sorsawodigital-edd-currency' ), 'manage_shop_settings', 'sorsawodigital-edd-currency', array( $this, 'render_pages' ) );
        add_action( 'load-' . $this->admin_page, array( $this, 'load_pages' ) );
    }
    
    /**
     * Execute the actions
     */
    public function load_pages() {        
        if ( $_GET['page'] === 'sorsawodigital-edd-currency' ) {		            
            if ( 'create' == $this->action ) {                
                $args = $_POST['new_currency'];

                if ( empty( $args['name'] ) || empty( $args['code'] ) ) {
                    wp_die( __( 'Please choose a valid Name and Code for this currency', 'sorsawodigital-edd-currency' ) );
                    exit;
                }

                check_admin_referer( 'sorsawodigital-edd-currency_create' );

                $db = (array) get_option( 'sorsawodigital_edd_currency_settings', array() );
                $new = array(
                    strtoupper( $args['code'] ) => array(
                        'name' => esc_html( $args['name'] ),
                        'symbol' => esc_html( $args['symbol'] )
                    )
                );

                if ( array_key_exists( $args['code'], $db ) ) {
                    wp_die( __( 'That currency code already exists', 'sorsawodigital-edd-currency' ) );
                    exit;
                }

                $_currencies = wp_parse_args( $new, $db );

                update_option( 'sorsawodigital_edd_currency_settings', $_currencies );
                wp_redirect( admin_url( 'admin.php?page=sorsawodigital-edd-currency&created=true' ) );
                exit;                
            }

            if ( 'edit' == $this->action && ! isset( $_REQUEST['code'] ) ) {
                $args = $_POST['edit_currency'];

                if ( empty( $args['name'] ) || empty( $args['code'] ) ) {
                    wp_die( __( 'You are trying to edit a currency that does not exist, or is not editable', 'sorsawodigital-edd-currency' ) );
                    exit;
                }

                //* nonce verification
                check_admin_referer( 'sorsawodigital-edd-currency_edit' );

                $db = (array) get_option( 'sorsawodigital_edd_currency_settings', array() );
                $new = array(
                    strtoupper( $args['code'] ) => array(
                        'name' => esc_html( $args['name'] ),
                        'symbol' => esc_html( $args['symbol'] )
                    )
                );

                if ( ! array_key_exists( $args['code'], $db ) ) {                    
                    wp_die( __( 'You are trying to edit a currency that does not exist, or is not editable', 'sorsawodigital-edd-currency' ) );
                    exit;                    
                }

                $_currencies = wp_parse_args( $new, $db );

                update_option( 'sorsawodigital_edd_currency_settings', $_currencies );
                wp_redirect( admin_url( 'admin.php?page=sorsawodigital-edd-currency&edited=true' ) );
                exit;                
            }

            if ( 'delete' == $this->action ) {
                $code = $_REQUEST['code'];

                if ( empty( $code ) ) {
                    wp_die( __( 'You are trying to delete a currency that does not exist, or cannot be deleted', 'sorsawodigital-edd-currency' ) );
                    exit;
                }

                //* nonce verification
                check_admin_referer( 'sorsawodigital-edd-currency_delete' );

                $_currencies = (array) get_option( 'sorsawodigital_edd_currency_settings' );

                if ( ! isset( $_currencies[$code] ) ) {
                    wp_die( __( 'You are trying to delete a currency that does not exist, or cannot be deleted', 'sorsawodigital-edd-currency' ) );
                    exit;
                }

                unset( $_currencies[$code] );

                update_option( 'sorsawodigital_edd_currency_settings', $_currencies );
                wp_redirect( admin_url( 'admin.php?page=sorsawodigital-edd-currency&deleted=true' ) );
                exit;                
            }
        }
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {        
        if ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] === 'sorsawodigital-edd-currency' ){	            
            if ( isset( $_REQUEST['created'] ) && 'true' === $_REQUEST['created'] ) {
                echo '<div id="message" class="updated"><p><strong>' . __( 'New currency successfully added!', 'sorsawodigital-edd-currency' ) . '</strong></p></div>';
            } elseif ( isset( $_REQUEST['edited'] ) && 'true' === $_REQUEST['edited'] ) {
                echo '<div id="message" class="updated"><p><strong>' . __( 'Currency successfully edited!', 'sorsawodigital-edd-currency' ) . '</strong></p></div>';
            } elseif ( isset( $_REQUEST['deleted'] ) && 'true' === $_REQUEST['deleted'] ) {
                echo '<div id="message" class="updated"><p><strong>' . __( 'Currency successfully deleted.', 'sorsawodigital-edd-currency' ) . '</strong></p></div>';
            }
        }        
    }
    
    /**
     * The management page
     */
    public function render_pages() {
        echo '<div class="wrap">';
        echo '<h2>', __( 'Manage Currencies', 'sorsawodigital-edd-currency' ), '</h2>';

        if ( 'edit' === $this->action ) {
            $this->currency_editor();
        } else {
            $this->currency_table();
        }

        echo '</div>';
    }

    /**
     * Print currency editor
     */
    private function currency_editor() {
        $_currencies = get_option( 'sorsawodigital_edd_currency_settings', array() );

        if ( array_key_exists( $_REQUEST['code'], (array) $_currencies ) ) {
            $_currency = stripslashes_deep( $_currencies[$_REQUEST['code']] );
        } else {
            wp_die( __( 'Nice try, partner. But that currency doesn\'t exist. Click back and try again.', 'sorsawodigital-edd-currency' ) );
        }
        ?>
        <form method="post" action="<?php echo admin_url( 'admin.php?page=sorsawodigital-edd-currency&amp;action=edit' ); ?>">
        <?php wp_nonce_field( 'sorsawodigital-edd-currency_edit' ); ?>
        <table class="form-table">
            <tr class="form-field">
                <th scope="row" valign="top"><label for="edit_currency[name]"><?php _e( 'Name', 'sorsawodigital-edd-currency' ); ?></label></th>
                <td><input name="edit_currency[name]" id="edit_currency[name]" type="text" value="<?php echo esc_html( $_currency['name'] ); ?>" size="40" />
            </tr>
            <tr class="form-field">
                <th scope="row" valign="top"><label for="edit_currency[code]"><?php _e( 'Code', 'sorsawodigital-edd-currency' ); ?></label></th>
                <td>
                <input type="text" value="<?php echo esc_html( $_REQUEST['code'] ); ?>" size="40" disabled="disabled" />
                <input name="edit_currency[code]" id="edit_currency[code]" type="hidden" value="<?php echo esc_html( $_REQUEST['code'] ); ?>" size="40" />
            </tr>
            <tr class="form-field">
                <th scope="row" valign="top"><label for="edit_currency[symbol]"><?php _e( 'Symbol', 'sorsawodigital-edd-currency' ); ?></label></th>
                <td><input name="edit_currency[symbol]" id="edit_currency[symbol]" type="text" value="<?php echo esc_html( $_currency['symbol'] ); ?>" size="40" /></td>
            </tr>
        </table>
        <p class="submit"><input type="submit" class="button-primary" name="submit" value="<?php _e( 'Update', 'sorsawodigital-edd-currency' ); ?>" /></p>	
        </form>
        <?php
    }
    
    /**
     * Print currency table
     */
    private function currency_table() {
        ?>
        <div id="col-container">
            <div id="col-right">
                <div class="col-wrap">	
                <h3><?php _e( 'Available Currencies', 'sorsawodigital-edd-currency' ); ?></h3>
                <table class="widefat tag fixed">
                    <thead>
                        <tr>
                            <th scope="col" id="name" class="manage-column column-name"><?php _e( 'Name', 'sorsawodigital-edd-currency' ); ?></th>
                            <th scope="col" class="manage-column column-slug"><?php _e( 'Code', 'sorsawodigital-edd-currency' ); ?></th>
                            <th scope="col" class="manage-column column-slug"><?php _e( 'Type', 'sorsawodigital-edd-currency' ); ?></th>
                            <th scope="col" id="description" class="manage-column column-description"><?php _e( 'Symbol', 'sorsawodigital-edd-currency' ); ?></th>
                        </tr>
                    </thead>	
                    <tfoot>
                        <tr>
                            <th scope="col" class="manage-column column-name"><?php _e( 'Name', 'sorsawodigital-edd-currency' ); ?></th>
                            <th scope="col" class="manage-column column-slug"><?php _e( 'Code', 'sorsawodigital-edd-currency' ); ?></th>
                            <th scope="col" class="manage-column column-slug"><?php _e( 'Type', 'sorsawodigital-edd-currency' ); ?></th>
                            <th scope="col" class="manage-column column-description"><?php _e( 'Symbol', 'sorsawodigital-edd-currency' ); ?></th>
                        </tr>
                    </tfoot>	
                    <tbody id="the-list" class="list:tag">	
                        <?php
                        $_currencies = $this->get_builtin_currencies();
                        $alt = true;

                        foreach ( (array) $_currencies as $code => $info ) :	
                            $is_editable = isset( $info['editable'] ) && $info['editable'] ? true : false;	
                            $type = isset( $info['editable'] ) && $info['editable'] ? __( 'Custom Currency', 'sorsawodigital-edd-currency' ) : __( 'Built-In Currency', 'sorsawodigital-edd-currency' );	
                        ?>	
                        <tr <?php if ( $alt ) { echo 'class="alternate"'; $alt = false; } else { $alt = true; } ?>>
                            <td class="name column-name">
                                <?php
                                if ( $is_editable ) {
                                    printf( '<a class="row-title" href="%s" title="Edit %s">%s</a>', admin_url('admin.php?page=sorsawodigital-edd-currency&amp;action=edit&amp;code=' . esc_html( $code ) ), esc_html( $info['name'] ), esc_html( $info['name'] ) );
                                } else {
                                    printf( '<strong class="row-title">%s</strong>', esc_html( $info['name'] ) );
                                }
                                ?>	
                                <?php if ( $is_editable ) : ?>
                                <br />
                                <div class="row-actions">
                                    <span class="edit"><a href="<?php echo admin_url('admin.php?page=sorsawodigital-edd-currency&amp;action=edit&amp;code=' . esc_html( $code ) ); ?>"><?php _e('Edit', 'sorsawodigital-edd-currency'); ?></a> | </span>
                                    <span class="delete"><a class="delete-tag" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=sorsawodigital-edd-currency&amp;action=delete&amp;code=' . esc_html( $code ) ), 'sorsawodigital-edd-currency_delete' ); ?>"><?php _e('Delete', 'sorsawodigital-edd-currency'); ?></a></span>
                                </div>
                                <?php endif; ?>	
                            </td>
                            <td class="slug column-slug"><?php echo esc_html( $code ); ?></td>
                            <td class="slug column-slug"><?php echo esc_html( $type ); ?></td>
                            <td class="description column-description"><?php echo esc_html( $info['symbol'] )?></td>
                        </tr>	
                        <?php endforeach; ?>
                    </tbody>
                </table>	
                </div>
            </div><!-- /col-right -->	
            <div id="col-left">
                <div class="col-wrap">		
                    <div class="form-wrap">
                        <h3><?php _e( 'Add New Currency', 'sorsawodigital-edd-currency' ); ?></h3>	
                        <form method="post" action="<?php echo admin_url( 'admin.php?page=sorsawodigital-edd-currency&amp;action=create' ); ?>">
                        <?php wp_nonce_field( 'sorsawodigital-edd-currency_create' ); ?>	
                        <div class="form-field form-required">
                            <label for="currency-name"><?php _e( 'Name', 'sorsawodigital-edd-currency' ); ?></label>
                            <input name="new_currency[name]" id="currency-name" type="text" value="" size="40" aria-required="true" />
                        </div>	
                        <div class="form-field">
                            <label for="currency-code"><?php _e( 'Code', 'sorsawodigital-edd-currency' ); ?></label>
                            <input name="new_currency[code]" id="currency-code" type="text" value="" size="40" />
                        </div>	
                        <div class="form-field">
                            <label for="currency-symbol"><?php _e( 'Symbol', 'sorsawodigital-edd-currency' ); ?></label>
                            <input name="new_currency[symbol]" id="currency-symbol" type="text" value="" size="40" />
                        </div>	
                        <p class="submit"><input type="submit" class="button button-primary" name="submit" id="submit" value="<?php _e( 'Add New Currency', 'sorsawodigital-edd-currency' ); ?>" /></p>
                        </form>
                    </div>	
                </div>
            </div><!-- /col-left -->	
        </div><!-- /col-container -->
        <?php
    }
    
    private function get_builtin_currencies() {
        $editable = false;
        $currencies = array();
        $available_currencies = edd_get_currencies();
        $_currencies = get_option( 'sorsawodigital_edd_currency_settings', array() );

        foreach ( $available_currencies as $code => $label ) {
            if ( array_key_exists( $code, $_currencies ) ) {
                $editable = true;
            }
            $currencies[$code] = array( 'name' => $label, 'symbol' => edd_currency_symbol( $code ), 'editable' => $editable );
        }
        
        return $currencies;
    }
    
    public function add_currency( $currencies ) {
        $_currencies = get_option( 'sorsawodigital_edd_currency_settings', array() );
        
        if ( $_currencies !== array() ) {
            foreach ( $_currencies as $code => $currency ) {
                $currencies[$code] = $currency['name'];
            }
        }
        
        return $currencies;
    }
    
    public function add_currency_simbol( $symbol, $currency ) { 
        $_currencies = get_option( 'sorsawodigital_edd_currency_settings', array() );
        
        if ( $_currencies !== array() ) {
            foreach ( $_currencies as $code => $info ) {
                if ( $currency === $code ) {
                    $symbol = $info['symbol'];
                }
            }
        } 
        
        return $symbol;
    }
    
    public function add_currency_filter_before( $formatted, $currency, $price ) { 
        $_currencies = get_option( 'sorsawodigital_edd_currency_settings', array() );
        
        if ( $_currencies !== array() ) {
            if ( isset( $_currencies[$currency] ) ) {
                $formatted = $_currencies[$currency]['symbol'] . ' ' . $price;
                return $formatted;
            }
        }
    }
    
    public function add_currency_filter_after( $formatted, $currency, $price ) { 
        $_currencies = get_option( 'sorsawodigital_edd_currency_settings', array() );
        
        if ( $_currencies !== array() ) {
            if ( isset( $_currencies[$currency] ) ) {
                $formatted = $price . ' ' . $_currencies[$currency]['symbol'];
                return $formatted;
            }
        }
    }
}

function sorsawodigital_edd_currency_init() {
    if ( class_exists( 'Easy_Digital_Downloads' ) ) {
        Sorsawo_Digital_EDD_Currency::get_instance();
    }
}
add_action( 'plugins_loaded', 'sorsawodigital_edd_currency_init' );