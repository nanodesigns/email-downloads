<?php
/**
 * Plugin Name: Email Downloads
 * Plugin URI: http://nanodesignsbd.com/
 * Description: Embed a form in your pages and posts that accept an email address in exchange for a file to download. The plugin is simpler, quicker, with minimal database usage, and completely in WordPress' way.
 * Version: 1.0.0
 * Author: Mayeenul Islam (@mayeenulislam), Sisir Kanti Adhikari (@prionkor)
 * Author URI: http://nanodesignsbd.com/mayeenulislam/
 * License: GNU General Public License v2.0
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */


/*  Copyright 2014 nanodesigns (email: info@nanodesignsbd.com)

    This plugin is a free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This plugin is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// let not call the files directly
if( !defined( 'ABSPATH' ) ) exit;


/**
 * Define the necessary data first
 * will be dynamic later
 */

$_sender = 'Mayeenul Islam';
$_from_email = 'info@nanodesignsbd.com';


/**
 * Shortcode
 * Usage: [email-downloads file="http://path/to/file.ext"]
 * @param  array $atts attributes that passed through shortcode.
 * @return string       formatted form.
 * ------------------------------------------------------------------------------
 */
function nanodesigns_email_downalods_shortcode( $atts ) {    
    $atts = shortcode_atts( array( 'file' => '' ), $atts );
    $file_path = $atts['file'];

    if( isset( $_POST['download_submit'] ) ) {

        $email      = $_POST['download_email'];

        if( $email && is_email( $email ) ) {

            $hashprefix     = 'downlink_';
            $ip_address     = nanodesigns_get_the_ip(); //grab the user's IP
            $unique_string  = $email . $file_path;
            $hash           = $hashprefix . md5( $unique_string );

            //db storage - for 12 hours only (12 * HOUR_IN_SECONDS) (P.S.: testing with 60 seconds only)
            set_transient( $hash, $file_path, 60 );

            /**
             * Making the download link with parameter
             * 'download_token' is important.
             * @var string
             */
            $download_link  = esc_url( add_query_arg( 'download_token', $hash, site_url() ) );

            //email the download link
            nanodesigns_email_downloads( $email, $download_link );
        }

    }

    ob_start();
    ?>
    <div class="email-downloads">
        <form action="" enctype="multipart/form-data" method="post">
            <p><?php _e( 'Enter your email address to download the file', 'email-downloads' ); ?></p>
            <input type="email" name="download_email" id="download-email" value=""><br>
            <button type="submit" name="download_submit"><?php _e( 'Send me the File', 'email-downloads' ); ?></button>
        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'email-downloads', 'nanodesigns_email_downalods_shortcode' );


/**
 * The Actual download link processor
 * @return void
 * ------------------------------------------------------------------------------
 */
function nanodesigns_let_the_user_download() {
    if( isset($_GET['download_token']) ){
        $download_token = sanitize_text_field( $_GET['download_token'] );
        $transient_data = get_transient( $download_token );
        $file_path = $transient_data ? $transient_data : false;

        if( $transient_data ) {

            //forcing download with appropriate headers
            header('Content-Type: application/octet-stream');
            header('Content-Description: File Transfer');
            header('Content-Transfer-Encoding: Binary');
            header('Content-disposition: attachment; filename="'. basename( $file_path ) .'"');
            header('Content-Length: '. filesize( $file_path ));
            header('Cache-Control: must-revalidate');

            //clean output buffering to let the user download larger files
            ob_clean();
            flush();

            //download the file
            readfile( $file_path );
            exit();

        } else {
            //transient is expired
            exit('<strong>Sorry!</strong> You are trying to explore an expired link.<br><a href="'. home_url() .'">&laquo; Home Page</a>');
        }
    }
}
add_action( 'template_redirect', 'nanodesigns_let_the_user_download' );


/**
 * Download link mailer
 * @param  string $email         the user submitted email address
 * @param  string $download_link the author submitted file path (hashed)
 * @return void
 * ------------------------------------------------------------------------------
 */
function nanodesigns_email_downloads( $email, $download_link ) {
    if( $email && is_email($email) && $download_link ) :
        
        global $_sender, $_from_email;

        $to_email       = $email;
        $subject        = __( 'Download is ready!', 'email-downloads' );

        ob_start(); ?>

            <html lang="en">
                <head>
                    <title><?php echo $subject; ?></title>
                    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                </head>
                <body style="line-height: 1; font-family: Georgia, 'Times New Roman', serif; font-size: 15px;">
                    <h2><?php _e('Wow! Download is Ready.', 'email-downloads' ); ?></h2>
                    <p><?php _e('Please follow the following link to download the file:', 'email-downloads' ); ?></p>
                    <p><a class="download-link" href="<?php echo esc_url( $download_link ); ?>" target="_blank" style="background-color: #E43435; color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none;"><?php _e( 'Download File', 'email-downloads' ); ?></a></p>
                </body>
            </html>

        <?php
        $message = ob_get_clean();

        $headers      = "From: ". $_sender ." <". $_from_email .">\r\n";
        $headers      .= "Reply-To: ". $_from_email ."\r\n";
        $headers      .= "MIME-Version: 1.0\r\n";
        $headers      .= "Content-Type: text/html; charset=UTF-8";

        function nanodesigns_mail_content_type() {
            return "text/html";
        }
        add_filter ("wp_mail_content_type", "nanodesigns_mail_content_type");

        //send the email
        $sent = wp_mail( $to_email, $subject, $message, $headers );

        if( $sent )
            _e( '<p>The download link is sent to your email address. Check your inbox please', 'email-downloads</p>' );
        else
            _e( 'Sorry, an error occured', 'email-downloads' );

    endif;
}



/**
 * Get the user's IP address
 * @author Barış Ünver
 * @link http://code.tutsplus.com/articles/creating-a-simple-contact-form-for-simple-needs--wp-27893
 * @return string IP address, formatted.
 * ------------------------------------------------------------------------------
 */
function nanodesigns_get_the_ip() {
    if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        return $_SERVER["HTTP_X_FORWARDED_FOR"];
    }
    elseif (isset($_SERVER["HTTP_CLIENT_IP"])) {
        return $_SERVER["HTTP_CLIENT_IP"];
    }
    else {
        return $_SERVER["REMOTE_ADDR"];
    }
}