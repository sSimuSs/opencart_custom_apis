<?php
class ControllerApiCatalog extends Controller
{
    public function product()
    {
        // $this->load->language('api/cart');
        $this->load->model('catalog/product');
        $this->load->model('tool/image');
        
        $limit = 10;
        if (isset($this->request->get['limit'])) {
            $limit = $this->request->get['limit'];
        }
        $offset = 0;
        if (isset($this->request->get['offset'])) {
            $offset = $this->request->get['offset'];
        }
        $filter_data = array('limit'=>$limit, 'start'=>$offset);
        $results = $this->model_catalog_product->getProducts($filter_data);

        $json = array();
        $json['count'] = (int)$this->model_catalog_product->getTotalProducts($filter_data);
        $json['result'] = array();
        
        foreach ($results as $result) {
            $images = array();
            if ($result['image']) {
                $images[] = array("product_image_id"=>null, "product_id"=>$result['product_id'], "image"=>$result['image']);
            }
            $images += $this->model_catalog_product->getProductImages($result['product_id']);
            if ($result['image']) {
                $thumb = $this->model_tool_image->resize($result['image'], $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
            } else {
                $thumb = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height'));
            }
            if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
                $price = (float)preg_replace( '/[^.\d]/', '', $this->currency->format(
                    $this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), 
                    $this->session->data['currency'],
                ));
            } else {
                $price = null;
            }
            if ((float) $result['special']) {
                $special = (float)preg_replace( '/[^.\d]/', '', $this->currency->format(
                    $this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), 
                    $this->session->data['currency']
                ));
            } else {
                $special = null;
            }
            if ($this->config->get('config_tax')) {
                $tax = preg_replace( '/[^.\d]/', '', $this->currency->format(
                    (float) $result['special'] ? $result['special'] : $result['price'], 
                    $this->session->data['currency']
                ));
            } else {
                $tax = false;
            }
            if ($this->config->get('config_review_status')) {
                $rating = (int) $result['rating'];
            } else {
                $rating = false;
            }
            $discount = null;
            if ($price && $special){
                $discount = $price-$special;
            }
            $radio_options = array();
            $options = $this->model_catalog_product->getProductOptions($result['product_id']);
            foreach($options as $opt){
                if($opt['type'] == 'radio' && $opt['required']){
                    $radio_options[] = $opt;
                }
            }
            $json['result'][] = array(
                'id' => (int)$result['product_id'],
                'images' => $images,
                'thumb' => $thumb,
                'name' => html_entity_decode($result['name']),
                'categories' => $this->model_catalog_product->getCategories($result['product_id']),
                'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, 500) . '..',
                'brand' => $result['manufacturer'],
                'cost' => $price,
                'discount' => $discount, // special cost, not discount in opencart case
                'discount_type' => $discount!=null ? 'c' : null,
                'qty' => (int)$result['quantity'],
                'currency' => strtolower($this->session->data['currency']),
                'options' => $radio_options,
                // 'tax' => $tax,
                'min_order_qty' => $result['minimum'] > 0 ? (int)$result['minimum'] : 1,
                // 'rating' => $result['rating'],
            );
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function category()
    {
        $this->load->model('catalog/category');
        $parent_id = 0;
        if (isset($this->request->get['parent_id'])) {
            $parent_id = $this->request->get['parent_id'];
        }
        $results = $this->model_catalog_category->getCategories($parent_id);

        $json = array();
        $json['count'] = count($results);
        $json['result'] = array();
        foreach ($results as $result) {
            $json['result'][] = array(
                'id' => $result['category_id'],
                'name' => html_entity_decode($result['name']),
            );
        }
        
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
