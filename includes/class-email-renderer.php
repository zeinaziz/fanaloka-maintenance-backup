<?php
/**
 * Email Renderer - Render email HTML in a Gmail-style sandboxed iframe.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Email;

use Fanaloka\Maintenance\Email\EmailParser;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * EmailRenderer Class.
 *
 * Preserves the sender's CSS (<style>, style="...") so client email looks like
 * Gmail, while keeping the WP admin safe: scripts and event handlers are
 * stripped, and output is rendered inside a sandboxed <iframe> with an opaque
 * origin (no allow-same-origin), so email CSS/JS cannot leak into the admin.
 */
class EmailRenderer {

    /**
     * Tags removed without exception (content intact -> children preserved).
     *
     * @var array<int, string>
     */
    private const BLOCKED_TAGS = [
        'iframe',
        'object',
        'embed',
        'applet',
        'frame',
        'frameset',
        'form',
        'input',
        'button',
        'select',
        'textarea',
        'base',
        'link',
    ];

    /**
     * Sanitize email HTML for storage.
     *
     * Keeps <style> and style attributes so styling survives, but strips
     * anything that could execute: <script>, comments, event handlers,
     * javascript: URLs and media/form/embed elements.
     *
     * @param string $html Raw email HTML.
     * @return string Sanitized HTML.
     */
    public static function sanitize_for_storage( string $html ): string {
        if ( '' === trim( $html ) ) {
            return '';
        }

        // Remove <script> tags and their content.
        $html = preg_replace( '/<script\b[^>]*>.*?<\/script\s*>/is', '', $html );

        // Remove HTML comments.
        $html = preg_replace( '/<!--.*?-->/s', '', $html );

        // Remove blocked tags (paired).
        $html = preg_replace( '/<(?:' . implode( '|', self::BLOCKED_TAGS ) . ')\b[^>]*>[\s\S]*?<\/\s*(?:' . implode( '|', self::BLOCKED_TAGS ) . ')\s*>/i', '', $html );
        // Remove blocked self-closing / unclosed tags.
        $html = preg_replace( '/<(?:' . implode( '|', self::BLOCKED_TAGS ) . ')\b[^>]*\/?>/i', '', $html );

        // Strip event handler attributes (on*).
        $html = preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html );

        // Neutralize javascript: URLs in common URL-bearing attributes.
        $html = preg_replace( '/(\s(?:href|src)\s*=\s*["\'])\s*javascript:[^"\']*(["\'])/i', '$1$2', $html );

        // Normalize line endings.
        $html = str_replace( [ "\r\n", "\r" ], "\n", $html );
        $html = preg_replace( "/\n{3,}/", "\n\n", $html );

        return trim( $html );
    }

    /**
     * Render email HTML inside a sandboxed iframe.
     *
     * @param string $html     Stored email HTML body.
     * @param string $frame_id Unique DOM id for the iframe (used for auto-resize).
     * @return string iframe markup.
     */
    public static function render( string $html, string $frame_id ): string {
        $html = trim( (string) $html );
        if ( '' === $html ) {
            return '';
        }

        // Strip quoted reply/forward text to avoid showing old inline images.
        $parser = new EmailParser();
        $html   = $parser->strip_quoted_html( $html );

        // Basic hardening (scripts, comments, blocked tags, handlers, js: URLs).
        $html = self::sanitize_for_storage( $html );

        // Allow data: URIs in img src by temporarily replacing them.
        $data_uris = [];
        $html = preg_replace_callback( '/src="(data:[^"]+)"/', function ( $m ) use ( &$data_uris ) {
            $key = '___DATA_URI_' . count( $data_uris ) . '___';
            $data_uris[] = $m[1];
            return 'src="' . $key . '"';
        }, $html );

        // Fix malformed HTML with DOMDocument (keeping <style>).
        $doc = new \DOMDocument( LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput       = false;
        @$doc->loadHTML( '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR );

        // Remove images with non-resolvable (bare) CID refs.
        self::strip_bare_cid_images( $doc );

        $body_node = $doc->getElementsByTagName( 'body' )->item( 0 );
        $body      = $doc->saveHTML( $body_node );
        $body      = preg_replace( '/^<body>(.*)<\/body>$/s', '$1', $body );
        $body      = (string) $body;

        // Restore safe data: URIs only in img src (images only).
        $body = preg_replace_callback( '/src="(___DATA_URI_\d+___)"/', function ( $m ) use ( $data_uris ) {
            $idx = (int) preg_replace( '/^___DATA_URI_(\d+)___$/', '$1', $m[1] );
            if ( isset( $data_uris[ $idx ] ) ) {
                $uri = $data_uris[ $idx ];
                if ( preg_match( '/^data:image\/(jpeg|png|gif|webp|svg\+xml);base64,/', $uri ) ) {
                    return 'src="' . esc_attr( $uri ) . '"';
                }
            }
            return 'src=""';
        }, $body );

        // Open all links in new tab (works because sandbox allows popups).
        $body = preg_replace( '/<a\b(?! [^>]*target=)([^>]*)>/i', '<a$1 target="_blank" rel="noopener">', $body );

        // Wrap images in a clickable link to view the full image.
        $body = preg_replace( '/<img\b([^>]*src="([^"]+)")>/i', '<a href="$2" target="_blank" rel="noopener"><img$1></a>', $body );

        if ( '' === trim( $body ) ) {
            return '';
        }

        // Build the standalone document shown inside the isolated iframe.
        $srcdoc  = '<!DOCTYPE html>';
        $srcdoc .= '<html><head><meta charset="UTF-8"><base target="_blank">';
        $srcdoc .= '<style>';
        $srcdoc .= 'html,body{margin:0;padding:0;}';
        $srcdoc .= 'body{font:13px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;color:#1d2327;padding:10px 14px;word-wrap:break-word;overflow-wrap:break-word;}';
        $srcdoc .= 'img{max-width:100%;height:auto;}';
        $srcdoc .= 'a{color:#2271b1;}';
        $srcdoc .= 'a img{cursor:zoom-in;}';
        $srcdoc .= '</style></head>';
        $srcdoc .= '<body>' . $body;
        $srcdoc .= '<script>(function(){var fid=' . wp_json_encode( $frame_id ) . ';function rs(){var h=document.body.scrollHeight;parent.postMessage({fmEmail:{id:fid,h:h}},"*");}window.addEventListener("load",rs);if(window.ResizeObserver){new ResizeObserver(rs).observe(document.body);}document.addEventListener("click",function(e){if(e.metaKey||e.ctrlKey||e.shiftKey||e.altKey||e.defaultPrevented){return;}var a=e.target.closest?e.target.closest("a"):null;var url=null,alt="";if(a&&a.querySelector("img")){url=a.href;var im=a.querySelector("img");alt=im?im.getAttribute("alt")||"":"";}else if(!a&&e.target.tagName==="IMG"){url=e.target.src;alt=e.target.getAttribute("alt")||"";}if(!url||!/^(https?:|data:image\/)/i.test(url)||!/\.(jpe?g|png|gif|webp|svg|avif|bmp)([?#]|$)/i.test(url)){return;}e.preventDefault();parent.postMessage({fmEmail:{id:fid,openImage:{src:url,alt:alt}}},"*");});})();</script>';
        $srcdoc .= '</body></html>';

        return '<iframe id="' . esc_attr( $frame_id ) . '" class="fm-email-frame" data-fm-frame-id="' . esc_attr( $frame_id ) . '" sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox" loading="lazy" srcdoc="' . esc_attr( $srcdoc ) . '"></iframe>';
    }

    /**
     * Remove inline images with non-resolvable CID references from DOMDocument.
     *
     * @param \DOMDocument $doc DOM document to clean.
     * @return void
     */
    private static function strip_bare_cid_images( \DOMDocument $doc ): void {
        $img_tags = $doc->getElementsByTagName( 'img' );
        $to_remove = [];

        for ( $i = 0; $i < $img_tags->length; $i++ ) {
            $img = $img_tags->item( $i );
            $src = trim( $img->getAttribute( 'src' ) );
            if ( ! empty( $src ) && ! preg_match( '/^(https?|cid|data):/i', $src ) ) {
                $to_remove[] = $img;
            }
        }

        foreach ( $to_remove as $img ) {
            $parent = $img->parentNode;
            if ( $parent ) {
                if ( 'div' === $parent->nodeName && self::is_emptyish_container( $parent ) ) {
                    $parent->parentNode->removeChild( $parent );
                } else {
                    $parent->removeChild( $img );
                }
            }
        }
    }

    /**
     * Check if a node is an empty-ish container.
     *
     * @param \DOMNode $node Node to check.
     * @return bool
     */
    private static function is_emptyish_container( \DOMNode $node ): bool {
        $content = trim( $node->textContent );
        return '' === $content || ctype_space( $content );
    }
}
