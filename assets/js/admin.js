/**
 * Fanaloka Maintenance Manager - Admin Scripts
 *
 * @package Fanaloka\Maintenance
 */

/* global jQuery, fmAdmin */
( function( $ ) {
    'use strict';

    var FMAdmin = {

        /**
         * Initialize.
         */
        init: function() {
            this.bindEvents();
            this.stripQuotedText();
        },

        /**
         * Bind events.
         */
        bindEvents: function() {
            $( document ).on( 'click', '.fm-btn-test-connection', this.testConnection );
            $( document ).on( 'click', '.fm-sync-btn', this.syncNow );
            $( document ).on( 'change', '.fm-ajax-field', this.updateField );
            $( document ).on( 'submit', '#fm-reply-form', this.submitReply );
        },

        /**
         * Show notification.
         */
        showNotice: function( message, type ) {
            var $notice = $( '<div class="fm-notice fm-notice-' + type + '">' + message + '</div>' );
            $( '#poststuff' ).prepend( $notice );
            setTimeout( function() {
                $notice.fadeOut( 300, function() { $( this ).remove(); } );
            }, 2000 );
        },

        /**
         * Update ticket field via AJAX.
         */
        updateField: function( e ) {
            var $select = $( this );
            var ticketId = $select.data( 'ticket-id' );
            var field = $select.data( 'field' );
            var value = $select.val();

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_update_ticket',
                nonce: fmAdmin.nonce,
                ticket_id: ticketId,
                field: field,
                value: value,
            }, function( response ) {
                if ( response.success ) {
                    FMAdmin.showNotice( fmAdmin.saved || 'Saved!', 'success' );
                } else {
                    FMAdmin.showNotice( fmAdmin.savedError || 'Error saving', 'error' );
                }
            } );
        },

        /**
         * Submit reply via AJAX.
         */
        submitReply: function( e ) {
            e.preventDefault();
            var $form = $( this );
            var $btn = $form.find( 'input[type="submit"]' );
            var ticketId = $form.find( 'input[name="ticket_id"]' ).val();

            // Sync TinyMCE content to textarea.
            if ( typeof tinymce !== 'undefined' ) {
                tinymce.triggerSave();
            }
            var content = $form.find( 'textarea[name="reply_content"]' ).val();

            if ( ! content.trim() ) {
                return;
            }

            $btn.prop( 'disabled', true ).val( 'Sending...' );

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_reply_ticket',
                nonce: fmAdmin.nonce,
                ticket_id: ticketId,
                content: content,
            }, function( response ) {
                $btn.prop( 'disabled', false ).val( 'Send Reply' );
                if ( response.success ) {
                    $form.find( 'textarea' ).val( '' );
                    // Append to timeline.
                    var html = '<div class="fm-conversation-entry fm-entry-developer">' +
                        '<div class="fm-entry-header">' +
                        '<strong>' + response.data.author + '</strong>' +
                        '<span class="fm-entry-type">Developer</span>' +
                        '<span class="fm-entry-date">' + response.data.date + '</span>' +
                        '</div>' +
                        '<div class="fm-entry-content">' + response.data.content + '</div>' +
                        '</div>';
                    $( '.fm-conversation' ).append( html );
                    FMAdmin.showNotice( response.data.message || 'Reply sent!', 'success' );
                } else {
                    FMAdmin.showNotice( fmAdmin.savedError || 'Error sending reply', 'error' );
                }
            } );
        },

        /**
         * Test IMAP connection.
         */
        testConnection: function( e ) {
            e.preventDefault();
            var $btn = $( this );

            $btn.prop( 'disabled', true ).text( fmAdmin.testing || 'Testing...' );

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_test_connection',
                nonce: fmAdmin.nonce,
            }, function( response ) {
                if ( response.success ) {
                    $btn.text( fmAdmin.success || 'Connected!' );
                } else {
                    $btn.text( response.data.message || fmAdmin.failed || 'Failed' );
                }
                setTimeout( function() {
                    $btn.prop( 'disabled', false ).text( fmAdmin.testConnection || 'Test Connection' );
                }, 3000 );
            } );
        },

        /**
         * Sync emails now.
         */
        syncNow: function( e ) {
            e.preventDefault();
            var $btn = $( this );

            $btn.prop( 'disabled', true ).text( fmAdmin.syncing || 'Syncing...' );

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_sync_now',
                nonce: fmAdmin.nonce,
            }, function( response ) {
                if ( response.success ) {
                    $btn.text( fmAdmin.syncComplete || 'Sync Complete!' );
                } else {
                    $btn.text( response.data.message || fmAdmin.failed || 'Failed' );
                }
                setTimeout( function() {
                    $btn.prop( 'disabled', false ).text( fmAdmin.syncNow || 'Sync Now' );
                }, 3000 );
            } );
        },

        /**
         * Strip quoted reply text from client entries in conversation timeline.
         * Removes "Pada [date] [name] menulis:" and everything after.
         */
        stripQuotedText: function() {
            $( '.fm-entry-client .fm-entry-content' ).each( function() {
                var $el = $( this );
                var text = $el.html();

                // Remove "Pada ... menulis:" and everything after.
                text = text.replace( /Pada\s+[\s\S]*?menulis:[\s\S]*$/i, '' );

                // Remove lines starting with > (quoted lines).
                text = text.replace( /^\s*&gt;.*$/gm, '' );

                // Clean up extra whitespace.
                text = text.replace( /\n{3,}/g, '\n\n' ).trim();

                $el.html( text );
            } );
        },
    };

    $( document ).ready( function() {
        FMAdmin.init();
    } );

} )( jQuery );
