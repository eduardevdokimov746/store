<?php

namespace app\controllers;

use \app\models\ProductModel;
use \store\Register;
use \store\Db;

class ProductController extends MainController
{
    public function indexAction()
    {   
        $alias = isset($this->route['alias']) ? $this->route['alias'] : '';

        if(empty($alias)) throw new \Exception('Страница не найдена', 404);

        $model = new ProductModel;

        $product = $model->getData($alias);

        if(!$product) redirect(HOST);

        $breadcrumbs = new \app\models\Breadcrumbs($product['category_id'], $product['title']);
        
        $viewedProducts = isset($_COOKIE['viewedProducts']) ? $_COOKIE['viewedProducts'] : '';

        if(!empty($viewedProducts)){
            $viewedProductsData = $model->db->query("SELECT * FROM product WHERE id IN ($viewedProducts)");

            if(strpos($viewedProducts, ',') !== false)
                $arrViewedProducts = explode(',', $viewedProducts);
            else
                $arrViewedProducts = [$viewedProducts];

            foreach ($viewedProductsData as $key => $value) {
                $viewedProductsData[$value['id']] = $value;
            }

            $count = 0;
            
            foreach ($viewedProductsData as $key => $value) {
                if(isset($arrViewedProducts[$count]))
                    $sortViewedProducts[] = $viewedProductsData[$arrViewedProducts[$count]];
                $count++;
                if($count == 8) break;
            }

            $viewedProducts = $model->createDataProduct($sortViewedProducts);
        }


        $commentsData = $this->getComments($alias);
        $countComments = $commentsData['countComments'];
        $comments = $commentsData['comments'];

        $this->addViewed($product['id']);
        $this->setParams(['product' => $product, 'breadcrumbs' => $breadcrumbs, 'viewedProducts' => $viewedProducts, 'comments' => $comments, 'countComments' => $countComments]);
        $this->setMeta($product['title'], $product['meta_description'], $product['meta_keywords']);
    }


    private function addViewed($productId)
    {
        $cookie = isset($_COOKIE['viewedProducts']) ? $_COOKIE['viewedProducts'] : '';
        $newCookie = $productId;

        if(!empty($cookie)){

            if(strpos($cookie, ',')){
                $arrViewed = explode(',', $cookie);
            }else{
                $arrViewed = [$cookie]; 
            }

            if(array_search($productId, $arrViewed) !== false) return;

            if(count($arrViewed) > 7){
                unset($arrViewed[6]);
            }

            $arrViewed[] = $productId;

            $newCookie = implode(',', array_reverse($arrViewed));
        }

        setcookie('viewedProducts', $newCookie, time() + 60 * 60 * 24 * 7, '/');
    }

    public function getComments($alias)
    {
        $db = Db::getInstance();

        $comments = $db->execute('SELECT comments.id, type, comment, good_comment, bad_comment, plus_likes, minus_likes, rating, date, name FROM comments JOIN users ON comments.user_id=users.id WHERE comments.product_id=(SELECT id FROM product WHERE alias=?) ORDER BY comments.date DESC LIMIT 3', [$alias]);

        $countComments = $db->execute('SELECT COUNT(*) FROM comments JOIN users ON comments.user_id=users.id WHERE comments.product_id=(SELECT id FROM product WHERE alias=?)', [$alias])[0]['COUNT(*)'];

        $countComments = ($countComments > 0) ? $countComments : '';

        if(!empty($comments)){
            foreach ($comments as $key => $value) {
                $value['date'] = newFormatDate($value['date']);
                $stars = [];
                for($x = 0; $x < 5; $x++) {
                    if($x < $value['rating'])
                        $stars[] = '';
                    else
                        $stars[] = '-o';
                }
                $value['stars'] = $stars;

                $comments[$key] = $value;
            }
            
            return ['comments' => $comments, 'countComments' => $countComments];
        }



        return false;
    }
}