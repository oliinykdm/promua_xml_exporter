<?
/**
* @package Prom.Ua Exporter
* @version 1.0
*/
/*
Plugin Name: PROM.UA Exporter (xml)
Plugin URI: http://www.oliynyk.me/projects/pua_exporter
Description: Плагин для экспорта каталога товаров на сайт Prom.ua.
Armstrong: My Plugin.
Author: Dima Oliynyk
Version: 1.0
Author URI: http://www.dima.rv.ua
*/

add_action('admin_menu', 'xml_exporter_panel');

function xml_exporter_panel() {
add_menu_page('Экспорт товаров', 'Экспорт товаров', 'manage_options', 'xml-exporter-panel', 'xml_exporter_page', '../wp-content/plugins/xml_exporter/assets/img/icon.ico');
}

function get_all_categories($parent_id__ = 0) {

    $taxonomy     = 'product_cat';
    $orderby      = 'name';
    $show_count   = 0;
    $pad_counts   = 0;
    $hierarchical = 1;
    $title        = '';
    $empty        = 0;

    $args = array(
        'taxonomy'     => $taxonomy,
        'child_of'     => 0,
        'parent'       => $parent_id__,
        'orderby'      => $orderby,
        'show_count'   => $show_count,
        'pad_counts'   => $pad_counts,
        'hierarchical' => $hierarchical,
        'title_li'     => $title,
        'hide_empty'   => $empty
    );

    $sub_cats = get_categories( $args );
    if($sub_cats) {
        foreach($sub_cats as $sub_category) {
            if($sub_cats->$sub_category == 0) {
                $GLOBALS['cats_array'][] = array(
                    'category_id'       => $sub_category->term_id,
                    'parent_id'         => $parent_id__,
                    'cat_name'          => $sub_category->name
                );
                get_all_categories($sub_category->term_id);
            }
        }
    }
}

function xml_exporter_page(){
    $path = dirname(__FILE__);
    $name = 'export.xml';
                echo '<div class="wrap">
                        <br>

           <div> <h3>Экспорт товаров для загрузки на сайт &mdash; <img style=" vertical-align:middle;" src="../wp-content/plugins/xml_exporter/assets/img/logo_main-trans.png" /></h3>

               </div><br />


               ';
    if($_POST['is_previous'] == "yes") {

        if (file_exists($path.'/'.$name)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.basename($path.'/'.$name));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($path.'/'.$name));
            ob_clean();
            flush();
            readfile($path.'/'.$name);
            exit;
        }
        else
        {
            echo "Ошибка! Файла пока нет, сначала его нужно создать, воспользуйтесь второй кнопкой!";
        }

    }
    else
    {
    if($_POST['is_export'] == "yes") {
        get_all_categories();
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><price></price>');
        $xml->addAttribute('date', date('Y-m-d H:i:s'));
        $catalog = $xml->addChild('catalog');

        $__countOfCats = count($GLOBALS['cats_array']);
        $i = 0;
        while($i < $__countOfCats) {

            $category = $catalog->addChild('category', $GLOBALS['cats_array'][$i]['cat_name']);
            $category->addAttribute('id', $GLOBALS['cats_array'][$i]['category_id']);
            if($GLOBALS['cats_array'][$i]['parent_id'] != 0) {
                $category->addAttribute('parentID', $GLOBALS['cats_array'][$i]['parent_id']);
            }
            $i++;
        }

        $args = array( 'post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => 0, 'orderby' => 'name' );
        $posts = get_posts( $args );
        $items = $xml->addChild('items');
        foreach ( $posts as $post ) : setup_postdata( $post );
            $_post_id            = $post->ID;
            $_post_title         = $post->post_title;
            $_post_desc          = $post->post_excerpt;
            $_post_link          = $post->post_name;
            $_post_preterm = get_the_terms($_post_id, 'product_cat');
            foreach($_post_preterm as $term) {
                if($term->object_id == $_post_id) {
                    $_post_term = $term->term_id;
                }
                $_post_pretag = get_the_terms($_post_id, 'product_tag');
                foreach($_post_pretag as $tag) {
                    if($tag->object_id == $_post_id) {
                        $_post_vendor = $tag->name;
                    }
                }
            }
            $price = get_post_meta( $_post_id, '_regular_price');
            $price = $price[0];
            $sku = get_post_meta( $_post_id, '_sku');
            $sku = $sku[0]; // vendor code
            $stock_status = get_post_meta( $_post_id, '_stock_status');
            $stock_status = $stock_status[0]; // stock_status
            if($stock_status == "instock") {$stock_status = "true";} else { if(!empty($stock_status)) {$stock_status = "false";}}

            $images = & get_children( array (
                'post_parent' => $_post_id,
                'post_type' => 'attachment',
                'post_mime_type' => 'image'
            ));

            if ( empty($images) ) {

            } else {
                foreach ( $images as $attachment_id => $attachment ) {
                    $_post_images = wp_get_attachment_url( $attachment_id, 'large');
                }
            }

            $item = $items->addChild('item');
            $item->addAttribute('id', $_post_id);
            $item->addChild('name', $_post_title);
            $item->addChild('categoryId', $_post_term);
            $item->addChild('price', $price);
            $item->addChild('url', get_permalink( woocommerce_get_page_id( 'shop' ) ).$_post_link);
            $item->addChild('image', $_post_images);
            $item->addChild('vendor', $_post_vendor);
            $item->addChild('vendorCode', $sku);
            $item->addChild('description', $_post_desc);
            $item->addChild('available', $stock_status);
        endforeach;
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        $dom->save($path.'/'.$name);
        if (file_exists($path.'/'.$name)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.basename($path.'/'.$name));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($path.'/'.$name));
            ob_clean();
            flush();
            readfile($path.'/'.$name);
            exit;
        }
        wp_reset_query();
    }

    else
    {

    echo '
                 <br />
                <form action="" method="POST">
                <input type="hidden" value="yes" name="is_export"/>
                <input type="submit" value="Сохранить XML файл с товарами" class="button button-primary" style="width: 300px; height: 35px"/>
                </form>
                <br />
                <form action="" method="POST">
                <input type="hidden" value="yes" name="is_previous"/>
                <input type="submit" value="Сохранить предыдущий XML файл" class="button button-secondary" style="width: 300px; height: 35px"/>
                </form>
                <br />
                ';
    }
    }

}


?>
