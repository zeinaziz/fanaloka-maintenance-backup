/**
 * Fanaloka Maintenance Manager - Admin Scripts
 *
 * @package Fanaloka\Maintenance
 */

/* global jQuery, fmAdmin */
( function( $ ) {
    'use strict';

    var FMAdmin = {

        lastEntryId: 0,
        refreshTimer: null,

        init: function() {
            this.bindEvents();
            this.stripQuotedText();
            this.initAutoRefresh();
            this.initEmailFrames();
        },

        initEmailFrames: function() {
            var self = this;
            window.addEventListener( 'message', function( e ) {
                if ( e.data && e.data.fmEmail ) {
                    var msg = e.data.fmEmail;
                    var frame = document.getElementById( msg.id );
                    if ( frame && msg.h ) {
                        frame.style.height = msg.h + 'px';
                    }
                    if ( msg.openImage ) {
                        self.openImageModal( msg.openImage.src, msg.openImage.alt );
                    }
                }
            } );
        },

        openImageModal: function( src, alt ) {
            if ( ! src || ! /^(https?:|data:image\/)/i.test( src ) ) {
                return;
            }
            this.closeImageModal();
            var $modal = $( '<div class="fm-image-modal" tabindex="-1" role="dialog" aria-modal="true" aria-label="Image preview">' +
                '<span class="dashicons dashicons-no-alt fm-image-modal-close" title="Close (Esc)"></span>' +
                '<img src="" alt="" />' +
                '</div>' );
            $modal.find( 'img' ).attr( 'src', src ).attr( 'alt', alt || '' );
            $( 'body' ).append( $modal ).addClass( 'fm-modal-open' );
            $modal.trigger( 'focus' );
            $modal.on( 'click', function( e ) {
                if ( e.target === this ) {
                    FMAdmin.closeImageModal();
                }
            } );
            $modal.find( '.fm-image-modal-close' ).on( 'click', function() {
                FMAdmin.closeImageModal();
            } );
            $( document ).on( 'keyup.fmImageModal', function( e ) {
                if ( e.key === 'Escape' || e.keyCode === 27 ) {
                    FMAdmin.closeImageModal();
                }
            } );
        },

        closeImageModal: function() {
            $( '.fm-image-modal' ).remove();
            $( 'body' ).removeClass( 'fm-modal-open' );
            $( document ).off( 'keyup.fmImageModal' );
        },

        bindEvents: function() {
            $( document ).on( 'click', '.fm-btn-test-connection', this.testConnection );
            $( document ).on( 'click', '.fm-btn-test-smtp', this.testSmtp );
            $( document ).on( 'click', '.fm-sync-btn', this.syncNow );
            $( document ).on( 'change', '.fm-ajax-field', this.updateField );
            $( document ).on( 'submit', '#fm-reply-form', this.submitReply );
        },

        showNotice: function( message, type ) {
            var $target = $( '.fm-page-wrap' ).length ? $( '.fm-page-wrap' ) : $( '.wrap' );
            var icon = type === 'success' ? 'yes-alt' : 'warning';
            var $notice = $( '<div class="fm-notice fm-notice-' + type + '"><span class="dashicons dashicons-' + icon + '"></span>' + message + '</div>' );
            $target.first().prepend( $notice );
            setTimeout( function() {
                $notice.fadeOut( 300, function() { $( this ).remove(); } );
            }, 4000 );
        },

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
                    // Update header badges.
                    if ( response.data.badges ) {
                        $( '.fm-ticket-header-right' ).html( response.data.badges );
                    }
                    FMAdmin.showNotice( response.data.message || 'Saved!', 'success' );
                } else {
                    FMAdmin.showNotice( response.data.message || 'Error saving', 'error' );
                }
            } );
        },

        submitReply: function( e ) {
            e.preventDefault();
            var $form = $( this );
            var $btn = $form.find( 'input[type="submit"], button[type="submit"]' );
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

            // Use FormData to support file uploads.
            var formData = new FormData( $form[0] );
            formData.append( 'action', 'fm_reply_ticket' );
            formData.append( 'nonce', fmAdmin.nonce );
            formData.append( 'ticket_id', ticketId );
            formData.append( 'content', content );

            $.ajax( {
                url: fmAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function( response ) {
                    $btn.prop( 'disabled', false ).val( 'Send Reply' );
                    if ( response.success ) {
                        // Append entry to conversation.
                        $( '.fm-ticket-conversation .fm-empty-state' ).remove();
                        $( '.fm-ticket-conversation .fm-reply-box' ).before( response.data.entry );

                        // Update badges.
                        if ( response.data.badges ) {
                            $( '.fm-ticket-header-right' ).html( response.data.badges );
                        }

                        // Reset form.
                        $form.find( 'textarea[name="reply_content"]' ).val( '' );
                        $form.find( 'input[type="file"]' ).val( '' );
                        if ( typeof tinymce !== 'undefined' ) {
                            tinymce.get( 'reply_content' ).setContent( '' );
                        }

                        // Update lastEntryId.
                        var $lastEntry = $( '.fm-ticket-conversation .fm-entry' ).last();
                        if ( $lastEntry.length ) {
                            FMAdmin.lastEntryId = parseInt( $lastEntry.data( 'entry-id' ) || 0 );
                        }

                        FMAdmin.showNotice( response.data.message || 'Reply sent!', 'success' );
                    } else {
                        FMAdmin.showNotice( response.data.message || 'Error sending reply', 'error' );
                    }
                },
                error: function() {
                    $btn.prop( 'disabled', false ).val( 'Send Reply' );
                    FMAdmin.showNotice( 'Request failed', 'error' );
                }
            } );
        },

        initAutoRefresh: function() {
            var self = this;

            // Get last entry ID from page.
            var $lastEntry = $( '.fm-ticket-conversation .fm-entry' ).last();
            if ( $lastEntry.length ) {
                this.lastEntryId = parseInt( $lastEntry.data( 'entry-id' ) || 0 );
            }

            // Only run on ticket detail page.
            if ( ! $( '.fm-ticket-conversation' ).length ) {
                return;
            }

            var ticketId = $( 'input[name="ticket_id"]' ).first().val();
            if ( ! ticketId ) {
                return;
            }

            this.refreshTimer = setInterval( function() {
                self.checkNewEntries( ticketId );
            }, 30000 ); // Check every 30 seconds
        },

        checkNewEntries: function( ticketId ) {
            var self = this;

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_get_entries',
                nonce: fmAdmin.nonce,
                ticket_id: ticketId,
                after_id: this.lastEntryId,
            }, function( response ) {
                if ( response.success && response.data.entries && response.data.entries.length > 0 ) {
                    // Append new entries.
                    var $conversation = $( '.fm-ticket-conversation' );
                    var $replyBox = $conversation.find( '.fm-reply-box' );

                    for ( var i = 0; i < response.data.entries.length; i++ ) {
                        $replyBox.before( response.data.entries[i] );
                    }

                    // Update badges.
                    if ( response.data.badges ) {
                        $( '.fm-ticket-header-right' ).html( response.data.badges );
                    }

                    // Update lastEntryId.
                    self.lastEntryId = parseInt( response.data.last_id || 0 );

                    FMAdmin.showNotice( 'New message(s) received', 'success' );
                }

                // Update badges even if no new entries.
                if ( response.success && response.data.badges ) {
                    $( '.fm-ticket-header-right' ).html( response.data.badges );
                }
            } );
        },

        testConnection: function( e ) {
            e.preventDefault();
            var $btn = $( this );
            var $result = $( '#fm-test-result' );

            $btn.prop( 'disabled', true ).text( fmAdmin.testing || 'Testing...' );
            $result.text( '' ).removeClass( 'fm-test-success fm-test-error' );

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_test_connection',
                nonce: fmAdmin.nonce,
            }, function( response ) {
                if ( response.success ) {
                    $result.text( response.data.message || fmAdmin.success || 'Connected!' ).addClass( 'fm-test-success' );
                } else {
                    $result.text( response.data.message || fmAdmin.failed || 'Failed' ).addClass( 'fm-test-error' );
                }
                $btn.prop( 'disabled', false ).text( fmAdmin.testConnection || 'Test Connection' );
            } ).fail( function() {
                $result.text( 'Request failed. Please try again.' ).addClass( 'fm-test-error' );
                $btn.prop( 'disabled', false ).text( fmAdmin.testConnection || 'Test Connection' );
            } );
        },

        testSmtp: function( e ) {
            e.preventDefault();
            var $btn = $( this );
            var $result = $( '#fm-smtp-test-result' );

            $btn.prop( 'disabled', true ).text( fmAdmin.testing || 'Testing...' );
            $result.text( '' ).removeClass( 'fm-test-success fm-test-error' );

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_test_smtp',
                nonce: fmAdmin.nonce,
            }, function( response ) {
                if ( response.success ) {
                    $result.text( response.data.message || 'Test email sent!' ).addClass( 'fm-test-success' );
                } else {
                    $result.text( response.data.message || 'Failed' ).addClass( 'fm-test-error' );
                }
                $btn.prop( 'disabled', false ).text( fmAdmin.testConnection || 'Send Test Email' );
            } ).fail( function() {
                $result.text( 'Request failed. Please try again.' ).addClass( 'fm-test-error' );
                $btn.prop( 'disabled', false ).text( fmAdmin.testConnection || 'Send Test Email' );
            } );
        },

        syncNow: function( e ) {
            e.preventDefault();
            var $btn = $( this );

            $btn.prop( 'disabled', true ).html( '<span class="dashicons dashicons-update" style="animation:spin 1s linear infinite;"></span> ' + ( fmAdmin.syncing || 'Syncing...' ) );

            $.post( fmAdmin.ajaxUrl, {
                action: 'fm_sync_now',
                nonce: fmAdmin.nonce,
            }, function( response ) {
                if ( response.success ) {
                    $btn.html( '<span class="dashicons dashicons-yes-alt"></span> ' + ( fmAdmin.syncComplete || 'Sync Complete!' ) );
                    FMAdmin.showNotice( response.data.message || 'Sync complete!', 'success' );
                } else {
                    $btn.html( '<span class="dashicons dashicons-warning"></span> ' + ( response.data.message || fmAdmin.failed || 'Failed' ) );
                    FMAdmin.showNotice( response.data.message || 'Sync failed', 'error' );
                }
                setTimeout( function() {
                    $btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update"></span> ' + ( fmAdmin.syncNow || 'Sync Now' ) );
                }, 4000 );
            } ).fail( function() {
                $btn.prop( 'disabled', false ).html( '<span class="dashicons dashicons-update"></span> ' + ( fmAdmin.syncNow || 'Sync Now' ) );
                FMAdmin.showNotice( 'Sync request failed', 'error' );
            } );
        },

        stripQuotedText: function() {
            $( '.fm-entry-client .fm-entry-content' ).each( function() {
                var $el = $( this );
                var text = $el.html();
                // Strip Indonesian quoted text: "Pada [date] [name] menulis:"
                text = text.replace( /Pada\s+[\s\S]*?menulis:[\s\S]*$/i, '' );
                // Strip English quoted text: "On [date], [name] wrote:" — only if text starts with "On"
                var trimmed = text.replace( /^\s+/, '' );
                if ( /^On\b/i.test( trimmed ) && /\bwrote:/i.test( trimmed ) ) {
                    text = text.replace( /On\s+[\s\S]*?\bwrote:[\s\S]*$/i, '' );
                }
                // Strip blockquoted lines starting with >
                text = text.replace( /^\s*&gt;.*$/gm, '' );
                text = text.replace( /\n{3,}/g, '\n\n' ).trim();
                $el.html( text );
            } );
        },
    };

    $( document ).ready( function() {
        FMAdmin.init();
    } );

} )( jQuery );
