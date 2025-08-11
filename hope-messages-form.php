<?php
/**
 * Plugin Name: Hope Messages Form
 * Description: Provides a shortcode to collect hopeful messages from visitors. The form collects a name, email and message, stores submissions as a custom post type, and displays approved messages.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register a custom post type for storing hope messages.
 *
 * Post type registrations should run on the `init` hook. The developer
 * documentation notes that post type registrations must not occur before
 * the `init` action because WordPress hasn’t finished loading its APIs yet【451138413803681†L79-L86】.
 */
function hm_register_hope_message_post_type() {
    $labels = array(
        'name'                  => _x( 'Hope Messages', 'post type general name', 'hope-messages' ),
        'singular_name'         => _x( 'Hope Message', 'post type singular name', 'hope-messages' ),
        'menu_name'             => __( 'Hope Messages', 'hope-messages' ),
        'name_admin_bar'        => __( 'Hope Message', 'hope-messages' ),
        'add_new'               => __( 'Add New', 'hope-messages' ),
        'add_new_item'          => __( 'Add New Message', 'hope-messages' ),
        'new_item'              => __( 'New Message', 'hope-messages' ),
        'edit_item'             => __( 'Edit Message', 'hope-messages' ),
        'view_item'             => __( 'View Message', 'hope-messages' ),
        'all_items'             => __( 'All Messages', 'hope-messages' ),
        'search_items'          => __( 'Search Messages', 'hope-messages' ),
        'not_found'             => __( 'No messages found.', 'hope-messages' ),
        'not_found_in_trash'    => __( 'No messages found in Trash.', 'hope-messages' ),
    );

    $args = array(
        'labels'                => $labels,
        'public'                => false,
        'publicly_queryable'    => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'query_var'             => true,
        'rewrite'               => array( 'slug' => 'hope_message' ),
        'capability_type'       => 'post',
        'has_archive'           => false,
        'hierarchical'          => false,
        'supports'              => array( 'title', 'editor' ),
    );

    register_post_type( 'hope_message', $args );
}
add_action( 'init', 'hm_register_hope_message_post_type' );

/**
 * Display the form and handle submissions.
 *
 * When the visitor submits the form, this function sanitizes the input,
 * inserts a new `hope_message` post with a pending status, and stores the
 * email in post meta. Published (approved) messages are queried and
 * displayed below the form.
 *
 * @return string Form markup and list of approved messages.
 */
function hm_hope_message_form_shortcode() {
    // Notice message to display after processing the form.
    $submission_notice = '';
    // Process form submission without redirect. The message will be displayed after submission.
    if ( isset( $_POST['hm_form_submitted'] ) && isset( $_POST['hm_nonce'] ) && wp_verify_nonce( $_POST['hm_nonce'], 'hm_form_submission' ) ) {
        $name    = isset( $_POST['hope_name'] )    ? sanitize_text_field( wp_unslash( $_POST['hope_name'] ) )        : '';
        $email   = isset( $_POST['hope_email'] )   ? sanitize_email( wp_unslash( $_POST['hope_email'] ) )            : '';
        $message = isset( $_POST['hope_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['hope_message'] ) ) : '';
        
        if ( $name && $email && $message ) {
            // Insert the message and publish immediately so it appears right away
            $post_id = wp_insert_post( array(
                'post_title'   => $name,
                'post_content' => $message,
                'post_type'    => 'hope_message',
                // Publish the post instead of saving as pending so it shows right away
                'post_status'  => 'publish',
            ) );
            if ( $post_id ) {
                update_post_meta( $post_id, '_hope_email', $email );
                // Handle image upload
                if ( ! empty( $_FILES['hope_image']['name'] ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    require_once ABSPATH . 'wp-admin/includes/image.php';
                    require_once ABSPATH . 'wp-admin/includes/media.php';
                    $attachment_id = media_handle_upload( 'hope_image', $post_id );
                    if ( ! is_wp_error( $attachment_id ) ) {
                        update_post_meta( $post_id, '_hope_image_id', $attachment_id );
                    }
                }
                // Success notice – immediately published
                $submission_notice = '<p class="hope-message-notice" style="background:#e6f8e6;padding:10px;border:1px solid #b6e4b6;">' . esc_html__( 'Your form has been submitted.', 'hope-messages' ) . '</p>';
            } else {
                // Error inserting post
                $submission_notice = '<p class="hope-message-error" style="background:#f8e6e6;padding:10px;border:1px solid #e4b6b6;">' . esc_html__( 'There was an error saving your message. Please try again later.', 'hope-messages' ) . '</p>';
            }
        } else {
            // Missing fields
            $submission_notice = '<p class="hope-message-error" style="background:#f8e6e6;padding:10px;border:1px solid #e4b6b6;">' . esc_html__( 'Please complete all fields.', 'hope-messages' ) . '</p>';
        }
    }

    ob_start();

    // Inline CSS for styling the form and messages
    ?>
    <style>
        .hope-message-form {
            margin-bottom: 2rem;
        }
        .hope-message-form input,
        .hope-message-form textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 0.5rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .hope-message-form button {
            background-color: #012D7E;
            color: #ffffff;
            font-weight: bold;
            border: none;
            padding: 0.6rem 1.2rem;
            cursor: pointer;
            border-radius: 4px;
        }
        .hope-message-form button:hover {
            background-color: #0a3eaf;
        }
        .hope-message-error {
            color: #a00;
            font-weight: bold;
        }
        .hope-message-notice {
            color: #0a0;
            font-weight: bold;
        }
        .hope-messages-wrapper {
            margin-top: 2rem;
        }
        .hope-messages-wrapper .hope-message-item {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
        }
        .hope-messages-wrapper .hope-message-item img {
            width: 145px;
            height: 145px;
            object-fit: cover;
            display: block;
            margin-bottom: 0.5rem;
        }
    </style>
    <?php

    // Display notice from current submission (if any)
    if ( ! empty( $submission_notice ) ) {
        echo $submission_notice;
    }

    // Form markup
    ?>
    <form method="post" class="hope-message-form" enctype="multipart/form-data">
        <p>
            <input type="text" name="hope_name" placeholder="<?php echo esc_attr( __( 'Name', 'hope-messages' ) ); ?>" required />
        </p>
        <p>
            <input type="email" name="hope_email" placeholder="<?php echo esc_attr( __( 'Email', 'hope-messages' ) ); ?>" required />
        </p>
        <p>
            <textarea name="hope_message" placeholder="<?php echo esc_attr( __( 'Your message', 'hope-messages' ) ); ?>" rows="5" required></textarea>
        </p>
        <p>
            <input type="file" name="hope_image" accept="image/*" />
        </p>
        <?php wp_nonce_field( 'hm_form_submission', 'hm_nonce' ); ?>
        <input type="hidden" name="hm_form_submitted" value="1" />
        <p>
            <button type="submit"><?php echo esc_html( __( 'Submit', 'hope-messages' ) ); ?></button>
        </p>
    </form>
    <?php

    // Query approved (published) messages
    $messages = new WP_Query( array(
        'post_type'      => 'hope_message',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    if ( $messages->have_posts() ) {
        echo '<div class="hope-messages-wrapper">';
        while ( $messages->have_posts() ) {
            $messages->the_post();
            $email_meta    = get_post_meta( get_the_ID(), '_hope_email', true );
            $attachment_id = get_post_meta( get_the_ID(), '_hope_image_id', true );
            ?>
            <div class="hope-message-item">
                <?php
                if ( $attachment_id ) {
                    // Display the uploaded image; size set via CSS
                    $img_url = wp_get_attachment_image_url( $attachment_id, 'full' );
                    if ( $img_url ) {
                        echo '<img src="' . esc_url( $img_url ) . '" alt="" />';
                    } else {
                        // Fallback to default image if attachment exists but no URL could be retrieved
                        echo '<img src="' . esc_url( HM_DEFAULT_IMAGE_DATA_URI ) . '" alt="Default avatar" />';
                    }
                } else {
                    // No image uploaded, use default avatar
                    echo '<img src="' . esc_url( HM_DEFAULT_IMAGE_DATA_URI ) . '" alt="Default avatar" />';
                }
                ?>
                <strong><?php echo esc_html( get_the_title() ); ?></strong><br />
                <?php if ( $email_meta ) : ?>
                    <em><?php echo esc_html( $email_meta ); ?></em><br />
                <?php endif; ?>
                <div class="hope-message-content">
                    <?php echo wpautop( get_the_content() ); ?>
                </div>
            </div>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
    }

    return ob_get_clean();
}
add_shortcode( 'hope_message_form', 'hm_hope_message_form_shortcode' );

/**
 * Flush rewrite rules upon plugin activation to ensure the custom post type
 * permalinks work correctly.
 */
function hm_hope_message_plugin_activation() {
    // Register the post type before flushing rules.
    hm_register_hope_message_post_type();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'hm_hope_message_plugin_activation' );

/**
 * Flush rewrite rules on deactivation.
 */
function hm_hope_message_plugin_deactivation() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'hm_hope_message_plugin_deactivation' );

/**
 * Base64-encoded default avatar used when a submission doesn’t include an image.
 * The plugin uses a data URI for the default profile picture so there’s no need
 * to upload a separate file. The image is a simple abstract silhouette and
 * doesn’t depict any real person.
 */
if ( ! defined( 'HM_DEFAULT_IMAGE_DATA_URI' ) ) {
    define(
        'HM_DEFAULT_IMAGE_DATA_URI',
        'data:image/png;base64,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f9ICfeZ8PoIOAAGCDgABgg4AAYIOAAGCDgABgg4AAYIOAAGCDgABgg4AAYIOAAGCDgABgg4AAYIOAAGCDgABgg4AAYIOAAGCDgABgg4AAYIOAAGCDgABgg4AAYI+obe3t6MfAdiYOZ+PoANAgKADQICgA0CAoANAgKADQICgA0CAoANAgKBPyp+0QJf5npOgA0CAoANAgKADQICgT8z3bNBjrucl6AAQIOgAECDoABAg6JPzfRt0mOe5CToABAg6AAQIOl7TQYA5RtABIEDQASBA0LlcLl7XwZmZXy4XQQeABEHnF5/y4XzMLT8JOgAECDoABAg6f/H6Ds7DvPInQQeAAEHnE5/6YXzmlH8JOgAECDpX+fQP4zKfXCPoABAg6NzkFgDjMZfcIuh8yfKAcZhHviLoABAg6NzlVgDHM4fcI+gAECDoLOJ2AMcxfywh6CxmqcD+zB1LCToABAg6D3FbgP2YNx4h6DzMkoHtmTMeJegAECDoPMXtAbZjvniGoPM0SwfWZ654lqDzEssH1mOeeIWg8zJLCF5njniVoANAgKCzCrcLeJ75YQ2CzmosJXicuWEtgs6qLCdYzrywJkFndZYU3GdOWJugswnLCm4zH2xB0NmMpQWfmQu2IuhsyvKC38wDWxJ0NmeJgTlge4LOLiwzZub8swdBZzeWGjNy7tmLoLMry42ZOO/sSdDZnSXHDJxz9iboHMKyo8z55gjfPj4+fhz9EMzt/f396EeAVQg5R3JD53CWIAXOMUcTdIZgGXJmzi8jEHSGYSlyRs4to/AdOkPyvTqjE3JG44bOkCxLRuZ8MiI3dIbnts4ohJyRuaEzPEuUETiHjM4NnVNxW2dvQs5ZuKFzKpYre3LeOBM3dE7LbZ2tCDln5IbOaVm6bMG54qzc0ElwW+dVQs7ZCTopws6jhJwKQSdJ2LlHyKkRdNKEnX8JOVWCzhSEHSGnTtCZirDPR8iZhaAzJWHvE3JmI+hMTdh7hJxZCTr8n7ifl4iDoMMnwn4eQg6/CTp8QdzHI+JwnaDDQuJ+HBGH+wQdniDu2xNxeIygwwoE/nUCDq8RdFiZuC8n4rAeQYcdiLx4w9YEHQ5Sjrx4w/4EHQZzptALN4xD0OGE9oi+WMO5CDoABHw/+gEAgNcJOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABAg6AAQIOgAECDoABPwX1r6+kVn1LpQAAAAASUVORK5CYII='
    );
}