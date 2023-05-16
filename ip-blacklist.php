<?php
/*
Plugin Name: IP Blacklist
Description: A plugin to record user IP at registration and blacklist certain IPs
Version: 1.0
Author: Dezzyboy | Glibx Inc
*/

global $jal_db_version;
$jal_db_version = '1.0';

function jal_install() {
    global $wpdb;
    global $jal_db_version;

    $table_name = $wpdb->prefix . 'blacklisted_ips';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        username varchar(55) DEFAULT '' NOT NULL,
        ip_address varchar(55) DEFAULT '' NOT NULL,
        reg_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        device_info text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'jal_db_version', $jal_db_version );
}


register_activation_hook( plugin_basename( __DIR__ ) . '/ip-blacklist.php', 'jal_install' );

function get_client_ip() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

function record_registration_ip($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'blacklisted_ips';

    $ip_address = get_client_ip();
    $username = $_POST['user_login'];

    $blacklisted = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE ip_address = %s",
        $ip_address
    ) );

    if ( $blacklisted ) {
        wp_die('Your IP has been blacklisted.');
    } else {
        update_user_meta($user_id, 'registration_ip', $ip_address);
        $wpdb->insert( 
            $table_name, 
            array( 
                'username' => $username,
                'ip_address' => $ip_address 
            ) 
        );
    }
}

add_action('user_register', 'record_registration_ip');

function blacklist_ips_menu() {
    add_menu_page(
        __( 'Blacklisted IPs', 'textdomain' ),
        'Blacklist IPs',
        'manage_options',
        'blacklist_ips',
        'blacklist_ips_admin_page',
        'dashicons-shield',
        6
    );
}
add_action( 'admin_menu', 'blacklist_ips_menu' );

function blacklist_ips_admin_page(){
    global $wpdb;
    $table_name = $wpdb->prefix . 'blacklisted_ips';
    
    if (isset($_POST['new_ip'])) {
        $wpdb->insert( 
            $table_name, 
            array( 
                'ip_address' => $_POST['new_ip'] 
            ) 
        );
    }

    echo '<h1>Blacklist IP</h1>';
    echo '<form method="post" action="">';
    echo '<input type="text" name="new_ip" placeholder="Enter IP to blacklist" required>';
    echo '<input type="submit" value="Blacklist IP">';
    echo '</form>';

    echo '<h2>Registered User IPs</h2>';
    echo '<table border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><th>Username</th><th>IP Address</th><th>Registration Date</th><th>Device Info</th></tr>';
    
    $users = get_users();
    foreach ($users as $user) {
        $ip = get_user_meta($user->ID, 'registration_ip', true);
        $date = get_user_meta($user->ID, 'registration_date', true);
        $device = get_user_meta($user->ID, 'registration_device', true);
        echo '<tr><td>' . $user->user_login . '</td><td>' . $ip . '</td><td>' . $date . '</td><td>' . $device . '</td></tr>';
    }
    echo '</table>';

    $results = $wpdb->get_results( "SELECT * FROM $table_name" );
    
    echo '<h2>Blacklisted IPs</h2>';
    echo '<table border="1" cellspacing="0" cellpadding="5">';
    echo '<tr><th>IP Address</th></tr>';
    foreach ( $results as $row ) {
        echo '<tr><td>' . $row->ip_address . '</td></tr>';
    }
    echo '</table>';
}






