<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Pans extends CI_Controller {

    public function __construct() {
        parent::__construct();
        sessionCheck();
        require_once (APPPATH . 'third_party/stripe-php/init.php');
        include APPPATH . 'third_party/image-resize/imageresize.php';
        include APPPATH . 'third_party/smtp_mail/smtp_send.php';
//       $a = new SMTP_mail;
//       $res = $a->sendMail('vasimlook@gmail.com','vasim','9099384773','hi');
        $this->load->model('Pans_m');
        $this->load->model('Common_m');
        $this->load->model('Discover_m');
        $this->load->model('Discover2_m');
        $this->load->model('Reviews_m');
        $this->load->model('Login');
        $this->load->helper('url');
        $this->load->helper('functions');
        $this->userId = (int) $_SESSION['user']['user_id'];
    }

    public function globle_pro(){
        $data['title']=GLOBLE_PRO_TITLE;
        $this->load->template('globlepro', $data);
	}

	public function country_products($country_id){
		$country_products = array();
		$products = $this->Common_m->country_products($country_id,0,8);

		foreach ($products AS $key=> $product){
			$products_id = $product['products_id'];
			$product['details'] = current(self::pans_details($products_id));
			$avg_rating = $this->Reviews_m->get_products_avg_rating($products_id);

			if(isset($avg_rating['overall_rating']))
				$avg = number_format($avg_rating['overall_rating'],0,'.','');
			else
				$avg = (int)$avg_rating;

			$product['avg_rating'] = $avg;

			$country_products [] = $product;

		}

		return $country_products;
	}

        public function globle_pro_search($country){             
            $country=str_replace("%20"," ","$country");

            $related_product = self::my_recommendations_products();

            $country_id = $this->Common_m->get_country_byname($country);
			$country_products = self::country_products($country_id);

			$origin = $this->Common_m->load_countries();

			$dockies_categories = self::get_pans_categories();

			$data['dockies_categories']  = $dockies_categories;
			$data['origin']  = $origin;
			$data['country_products'] = $country_products;
			$data['related_product'] = $related_product;

            $data['country_name']  = $country;
            $data['country_id']  = $country_id;
            $data['title']=GLOBLE_PRO_SEARCH_TITLE;
            $this->load->template('globle_pro_search', $data);
	}
    
        public function my_recommendations_products(){

		$products = self::my_recommendations_pans(0,3);

		return $products;

	}
    
    
    public function index() {


        $my_recommendations_pans = self::my_recommendations_pans();
       
        $categories = self::get_pans_categories();
        $category_products = self::category_best_products();                        
        $data['categories'] = $categories;        
        $data['my_recommendations_pans'] = $my_recommendations_pans;
        $data['category_products'] = $category_products;
        $data['title'] = PANS_PANS_HOME_TITLE;
        $this->load->template('pans_home', $data);
      

    }


    public function new_login_view() {
        
        $my_recommendations_pans = self::my_recommendations_pans();
       
        $categories = self::get_pans_categories();

        $category_products = self::category_best_products();                        

        $data['categories'] = $categories;        
       $data['my_recommendations_pans'] = $my_recommendations_pans;
       $data['category_products'] = $category_products;
         $data['title'] = PANS_PANS_HOME_TITLE;
       $this->load->template('newlogin', $data); 
         $this->load->view('newlogin',$data);
 
     }

    public function my_recommendations_pans($start_limit = 0,$end_limit = 6) {
        $my_recommendations_pans = $this->Discover2_m->my_recommendations_pans($this->userId, '', 2,0,$start_limit,$end_limit);

        $not_products_id_arr = array();


        if (sizeof($my_recommendations_pans) < $end_limit) {
            foreach ($my_recommendations_pans AS $key => $product) {
                $products_id = $product['products_id'];
                $not_products_id_arr [] = $products_id;
            }

            $limit = sizeof($my_recommendations_pans) - $end_limit;


            $limit = abs($limit);

            $rating_products = $this->Discover2_m->get_rating_pans_products(0, $limit, $not_products_id_arr);



            $my_recommendations_pans = array_merge($my_recommendations_pans, $rating_products);
        }

        $products = array();

        if (sizeof($my_recommendations_pans) > 0) {
            foreach ($my_recommendations_pans AS $key => $product) {
                $products_id = $product['products_id'];
                $product['details'] = current(self::pans_details($products_id));
                $avg_rating = $this->Reviews_m->get_products_avg_rating($products_id);

                if (isset($avg_rating['overall_rating']))
                    $avg = number_format($avg_rating['overall_rating'], 0, '.', '');
                else
                    $avg = (int) $avg_rating;

                $product['avg_rating'] = $avg;

                $products [] = $product;
            }
        }

        return $products;
    }

    public function pans_viewer() {
        if (isset($_REQUEST['products_id']) && $_REQUEST['products_id'] != '' && isset($_REQUEST['boost_id']) && $_REQUEST['boost_id'] != '') {

            $date = date("Y:m:d H:i:s");

            $add_pans_viewer = array(
                'ref_id' => (int) $_REQUEST['products_id'],
                'ref_BoostId' => (int) $_REQUEST['boost_id'],
                'boost_history_type' => 2,
                'view' => 1,
                'action_by' => $this->userId,
                'created_date' => $date
            );
            $add_view = $this->Pans_m->pans_viewer($add_pans_viewer);
        }
    }

    public function pans_click() {
        if (isset($_REQUEST['products_id']) && $_REQUEST['products_id'] != '' && isset($_REQUEST['boost_id']) && $_REQUEST['boost_id'] != '') {

            $date = date("Y:m:d H:i:s");

            $add_pans_viewer = array(
                'ref_id' => (int) $_REQUEST['products_id'],
                'ref_BoostId' => (int) $_REQUEST['boost_id'],
                'boost_history_type' => 2,
                'click' => 1,
                'action_by' => $this->userId,
                'created_date' => $date
            );
            $add_view = $this->Pans_m->pans_click($add_pans_viewer);
        }
    }

    public function category_best_products() {
        $categories = self::get_pans_categories();

        $category_list = 2;

        foreach ($categories as $key => $category) {
            if ($key < $category_list) {

                $category_id = $category['category_id'];

                $products = $this->Common_m->get_main_category_products($category_id);

                $cat_products = array();

                foreach ($products AS $key => $product) {
                    $products_id = $product['products_id'];

                    $boost_data = $this->Pans_m->is_pans_boosted($products_id);

                    $product['is_boosted'] = 0;
                    if (isset($boost_data) && is_array($boost_data) && sizeof($boost_data) > 0 && $boost_data['status'] == 1) {
                        $product['is_boosted'] = 1;
                    }

                    $product['details'] = current(self::pans_details($products_id));
                    $avg_rating = $this->Reviews_m->get_products_avg_rating($products_id);

                    if (isset($avg_rating['overall_rating']))
                        $avg = number_format($avg_rating['overall_rating'], 0, '.', '');
                    else
                        $avg = (int) $avg_rating;

                    $product['avg_rating'] = $avg;

                    $cat_products [] = $product;
                }
                $category['best_products'] = $cat_products;

                $category_products [] = $category;
            }
        }

        return $category_products;
    }

    public function load_countries() {
        $countries = $this->Common_m->load_countries();
        return $countries;
    }

    public function load_quantity_types() {
        $quantity_types = $this->Common_m->load_quantity_types();
        return $quantity_types;
    }

    public function load_payment_options() {
        $payment_options = $this->Common_m->load_payment_options();
        return $payment_options;
    }

    public function get_pans_categories() {
        $pans_categories = $this->Common_m->get_products_categories();

        $categories = array();

        foreach ($pans_categories AS $key => $category) {
            $category_id = $category['category_id'];
            //load subcategories
            $category ['sub_categories'] = self::get_pans_sub_categories($category_id);
            $categories [] = $category;
        }
        return $categories;
    }

    public function get_pans_selected_categories($refCategory_id) {
        $pans_selected_categories = $this->Common_m->get_products_selected_categories($refCategory_id);
        $pans_child_categories = $this->Common_m->get_products_selected_child_categories($pans_selected_categories['parent_id']);
        $selected_cat = array();
        $selected_cat['details'] = $pans_selected_categories;
        $selected_cat['childCategory'] = $pans_child_categories;
        return $selected_cat;
    }

    public function get_pans_sub_categories($category_id = 0) {

        if (isset($_REQUEST['category_id']) && $_REQUEST['category_id'] != '') {
            $category_id = $_REQUEST['category_id'];
        }

        $sub_categories = $this->Common_m->get_products_sub_categories($category_id);

        return $sub_categories;
    }

    public function pans_new() {

        if (!self::has_create_pans_authority()) {
            //store message in session
            //your plan has been expired please upgrade your plan
            //pans creation limit has been exceeds
            redirect(USER_PANS_MANAGEMENT_LINK);
        }


        $pans_categories = self::get_pans_categories();
        $data['countries'] = self::load_countries();
        $data['quantity_types'] = self::load_quantity_types();
        $data['payment_options'] = self::load_payment_options();
        $data['interest'] = $this->Login->get_all_interest();
        $data['pans_categories'] = $pans_categories;
        $data['title'] = PANS_NEW_PANS_TITLE;
        $this->load->single_page('new_pans', $data);
    }

    public function get_pans_payment_options($pansId) {
        $payment_options = $this->Common_m->get_products_payment_options($pansId);

        return $payment_options;
    }

    public function pans_details($pansId) {
        $details = array();

        $payment_options = $this->get_pans_payment_options($pansId);
        $other_options = current($this->Common_m->get_products_other_payment_options($pansId));

        $payment_options_ids = array();

        foreach ($payment_options as $key => $option) {
            $payment_options_ids [] = $option['payment_option_id'];
        }

        $pans = $this->Discover_m->get_single_pans_details($pansId);

        foreach ($pans as $key => $pan) {
            $pan_main_image = $this->Common_m->get_products_images($pan['products_id'], true);
            $pan['image'] = current($pan_main_image);


            $pan_other_images = $this->Common_m->get_products_images($pan['products_id'], false);
            $pan['other_image'] = $pan_other_images;

            $pan['payment_options'] = $payment_options_ids;
            $pan['other_payment_options'] = $other_options['other_payment_option'];

            $pan['pan_files'] = $this->Common_m->get_products_files($pan['products_id']);

            $details [] = $pan;
        }

        return $details;
    }

    public function pans_edit($pansId) {

        $pans_details = self::pans_details($pansId);

        $pans_categories = self::get_pans_categories();
        $pans_selected_categories = self::get_pans_selected_categories($pans_details[0]['products_category']);


        $data['pans'] = $pans_details[0];
        $data['countries'] = self::load_countries();
        $data['quantity_types'] = self::load_quantity_types();
        $data['payment_options'] = self::load_payment_options();
        $data['interest'] = $this->Login->get_all_interest();
        $data['pans_categories'] = $pans_categories;
        $data['selected_pans_categories'] = $pans_selected_categories;
        $data['title'] = PANS_EDIT_PANS_TITLE;
        $this->load->single_page('edit_pans', $data);
    }

//	public function edit($pansId){
//		$validate = self::validate_pans();
//                
//		if(sizeof($validate['error_message']) > 0){
//			//this is the array of the error messages of the fields
//			print_r($validate);
//			return $validate['error_message'];
//		}
//	}

    public function validate_pans($edit = true, $productid = 0) {

        $error_message = array();

        if (!isset($_REQUEST['products_category'])) {
            $error_message [] = 'Invalid pans category';
        } else {
            $products_category = (int) $_REQUEST['products_category'];

            if ($products_category === 0)
                $error_message [] = 'Invalid pans category';
        }

        $products_name = trim($_REQUEST['products_name']);

        if ($products_name == '') {
            $error_message[] = 'Products name can not be empty!';
        }

        if ($edit) {
            $pan_main_image = $this->Common_m->get_products_images($productid, true);
            $img = current($pan_main_image);
            if (!empty($img['image']['image_path'])) {
                if ($_FILES['products_main_image']['name'] == '') {
                    $error_message [] = 'Please attach products image';
                }
            }
        } else {
            if ($_FILES['products_main_image']['name'] == '') {
                $error_message [] = 'Please attach products image';
            }
        }

        $pans_countries = (int) $_REQUEST['pans_countries'];

        if ($pans_countries === 0) {
            $error_message[] = 'Invalid pans origin!';
        }

        $quantity = (int) $_REQUEST['quantity'];

        if ($quantity === 0) {
            $error_message[] = 'Invalid pans quantity';
        }

        $quantity_type = (int) $_REQUEST['quantity_type'];

        if ($quantity_type === 0) {
            $error_message[] = 'Invalid quantity type';
        }

        $products_descriptions = trim($_REQUEST['products_descriptions']);

        if ($products_descriptions == '') {
            $error_message[] = 'Products description can not be empty!';
        }

        $products_price = $_REQUEST['products_price'];

        if ($products_price == '') {
            $error_message[] = 'Invalid pans price';
        } else {
            $products_price = number_format($products_price, 2, '.', '');
            if ($products_price <= 0)
                $error_message[] = 'Invalid pans price';
        }
        $other_payment_option = trim($_REQUEST['other_payment_option']);

        if (!isset($_REQUEST['paymentOptions']) && $other_payment_option == '') {
            $error_message[] = 'Please provide at list one payment options';
        }

        return array(
            'error_message' => $error_message
        );
    }

    public function has_create_pans_authority() {
        $has_plan = subscriptionStatus();

        if (!$has_plan) {//check user pans total
            $total_users_pan = $this->Pans_m->get_user_pans_total($this->userId);

            if ($total_users_pan['total_pans'] <= 19) {
                return true;
            }
        }
        return $has_plan;
    }

    public function pans_delete($pansId) {
        $this->Pans_m->pans_delete($pansId);
        successOrErrorMessage("Your pan deactivated", 'success');
        redirect(USER_PANS_MANAGEMENT_LINK);
    }

    public function edit($pansId) {
        $has_add_pan_authority = self::has_create_pans_authority();

        if (!$has_add_pan_authority) {

            //add this message somewhere like error messgae popup
//            echo "Your pan limit has been exceeds please upgrade your plan";
            successOrErrorMessage("Upgrade now to dockies prime,  to manage dockies", 'error');
            redirect(USER_PANS_MANAGEMENT_LINK);
        }

        $validate = self::validate_pans(true, $pansId);

        if (sizeof($validate['error_message']) > 0) {
            //this is the array of the error messages of the fields
            print_r($validate);
            return $validate['error_message'];
        } else {
            // safe insert pans data
            $category = $this->Common_m->get_products_main_category($_REQUEST['products_sub_category']);
            $_REQUEST['products_main_category'] = $category['main_categoryId'];
            $edit_pans = $this->Pans_m->edit_pans($_REQUEST, $pansId);
            if (!empty($edit_pans)) {
                //main image 
                if (($_FILES['products_main_image']['name']) != '') {
                    $main_img = singleImageUpload('products_main_image');
                    $products_main_image = $main_img[2]['file_name'];

                    $params = array();
                    $params['products_id'] = $pansId;
                    $params['image_path'] = $products_main_image;
                    $params['is_main'] = 1;
                    $params['status'] = 1;
                    $this->Common_m->add_products_image($params);
                }
                //multiple image
                if (($_FILES['products_others_image']['name'][0]) != '') {


                    $products_others_image = multiImageUpload('products_others_image');

                    foreach ($products_others_image as $poi) {
                        $params = array();
                        $params['products_id'] = $pansId;
                        $params['image_path'] = $poi[2]['file_name'];
                        $params['status'] = 1;
                        $this->Common_m->add_products_image($params);
                    }
                }

                if (($_FILES['file-upload']['name'][0]) != '') {
                    $this->Pans_m->delete_files($pansId);
                    $files = multiFileUpload('file-upload');
                    foreach ($files as $frow) {
                        $params = array();
                        $params['products_id'] = $pansId;
                        $params['file_path'] = $frow[2]['file_name'];
                        $params['file_name'] = $frow[2]['original_file_name'];
                        $params['extension'] = $frow[2]['file_ext'];
                        $params['status'] = 1;
                        $this->Common_m->add_products_file($params);
                    }
                }
            }
        }


        //code for adding pans payment options
        if ($_REQUEST['paymentOptions'] && sizeof($_REQUEST['paymentOptions']) > 0) {
            $this->Pans_m->delete_payment_option($pansId);
            foreach ($_REQUEST['paymentOptions'] AS $key => $optionId) {
                $params = array(
                    'products_id' => $pansId,
                    'payment_option_id' => $optionId
                );
                $this->Common_m->add_payment_options($params);
            }
        }

        //other pans payment options
        if ($_REQUEST['other_payment_option'] && $_REQUEST['other_payment_option'] != '') {
            $params = array(
                'products_id' => $pansId,
                'payment_option_id' => 0,
                'other_payment_option' => $_REQUEST['other_payment_option']
            );

            $this->Common_m->add_payment_options($params);
        }
        if ($edit_pans) {
            successOrErrorMessage("Your pan updated successfully", 'success');
            redirect(USER_PANS_MANAGEMENT_LINK);
        }
    }

    public function create() {

        // print_r($_REQUEST);die;
        $has_add_pan_authority = self::has_create_pans_authority();

        if (!$has_add_pan_authority) {

            //add this message somewhere like error messgae popup
//            echo "Your pan limit has been exceeds please upgrade your plan";
            successOrErrorMessage("Your pan limit has been exceeds please upgrade your plan", 'error');
            redirect(USER_PANS_MANAGEMENT_LINK);
        }

        $validate = self::validate_pans();

        if (sizeof($validate['error_message']) > 0) {
            //this is the array of the error messages of the fields
            print_r($validate);
            return $validate['error_message'];
        } else {

            //get products_main_category

            $category = $this->Common_m->get_products_main_category($_REQUEST['products_sub_category']);
            $_REQUEST['products_main_category'] = $category['main_categoryId'];
            // safe insert pans data            
            $create_pans = $this->Pans_m->create($this->userId, $_REQUEST);

            if (!empty($create_pans)) {
                //main image 
                if (($_FILES['products_main_image']['name']) != '') {
                    $main_img = singleImageUpload('products_main_image');
                    $products_main_image = $main_img[2]['file_name'];

                    $params = array();
                    $params['products_id'] = $create_pans;
                    $params['image_path'] = $products_main_image;
                    $params['is_main'] = 1;
                    $params['status'] = 1;
                    $this->Common_m->add_products_image($params);
                }



                if (($_FILES['products_others_image_one']['name']) != '') {
                    $main_img = singleImageUpload('products_others_image_one');
                    $products_others_image_one = $main_img[2]['file_name'];

                    $params = array();
                    $params['products_id'] = $create_pans;
                    $params['image_path'] = $products_others_image_one;
                    $params['is_main'] = 0;
                    $params['status'] = 1;
                    $this->Common_m->add_products_image($params);
                }

                if (($_FILES['products_others_image_two']['name']) != '') {
                    $main_img = singleImageUpload('products_others_image_two');
                    $products_others_image_two = $main_img[2]['file_name'];

                    $params = array();
                    $params['products_id'] = $create_pans;
                    $params['image_path'] = $products_others_image_two;
                    $params['is_main'] = 0;
                    $params['status'] = 1;
                    $this->Common_m->add_products_image($params);
                }

                if (($_FILES['products_others_image_three']['name']) != '') {
                    $main_img = singleImageUpload('products_others_image_three');
                    $products_others_image_three = $main_img[2]['file_name'];

                    $params = array();
                    $params['products_id'] = $create_pans;
                    $params['image_path'] = $products_others_image_three;
                    $params['is_main'] = 0;
                    $params['status'] = 1;
                    $this->Common_m->add_products_image($params);
                }
                //multiple image
                if (($_FILES['products_others_image']['name'][0]) != '') {

                    $main_img = singleImageUpload('products_others_image');
                    $products_others_image = $main_img[2]['file_name'];

                    // $products_others_image = multiImageUpload('products_others_image');

                    // foreach ($products_others_image as $poi) {
          
                    //     $params = array();
                    //     $params['products_id'] = $create_pans;
                    //     $params['image_path'] = $poi[2]['file_name'];
                    //     $params['status'] = 1;
                    //     $this->Common_m->add_products_image($params);
                    // }

                    $params = array();
                    $params['products_id'] = $create_pans;
                    $params['image_path'] = $products_others_image;
                    $params['is_main'] = 0;
                    $params['status'] = 1;
                    $this->Common_m->add_products_image($params);
                }

                if (($_FILES['file-upload']['name'][0]) != '') {
                    $files = multiFileUpload('file-upload');
                    foreach ($files as $frow) {
                        $params = array();
                        $params['products_id'] = $create_pans;
                        $params['file_path'] = $frow[2]['file_name'];
                        $params['file_name'] = $frow[2]['original_file_name'];
                        $params['extension'] = $frow[2]['file_ext'];
                        $params['status'] = 1;
                        $this->Common_m->add_products_file($params);
                    }
                }

                $notification = array(
                        'action_by_userId' => $this->userId,
                        'action_on'  => $create_pans,
                        'notification_type' => 'create_pans',
                        'notification_status'=> 1
                );

                $this->Common_m->add_notification($notification);
                
                $usersNotification=$this->Common_m->get_all_notification_users($_REQUEST['pans_interest']);
                if(!empty($usersNotification)){
                    $emailArray=array();
                    foreach ($usersNotification as $unrow){
                        $unparams=array();
                        $unparams['refUser_id']=$unrow['user_id'];
                        $unparams['refProduct_id']=$create_pans;
                        $unparams['notification_type']='new_product';                       
                        array_push($emailArray, $unrow['user_email']);
                    }
                    $sendmail = new SMTP_mail();
                    $sendmail->newProductEmail($emailArray,$_REQUEST['products_name']);
                }
            }
            //code for adding pans payment options
            if ($_REQUEST['paymentOptions'] && sizeof($_REQUEST['paymentOptions']) > 0) {
                foreach ($_REQUEST['paymentOptions'] AS $key => $optionId) {
                    $params = array(
                        'products_id' => $create_pans,
                        'payment_option_id' => $optionId
                    );
                    $this->Common_m->add_payment_options($params);
                }
            }

            //other pans payment options
            if ($_REQUEST['other_payment_option'] && $_REQUEST['other_payment_option'] != '') {
                $params = array(
                    'products_id' => $create_pans,
                    'payment_option_id' => 0,
                    'other_payment_option' => $_REQUEST['other_payment_option']
                );
                $this->Common_m->add_payment_options($params);
            }
        }
        if ($create_pans) {
            successOrErrorMessage("Your pan created successfully", 'success');
            redirect(USER_PANS_MANAGEMENT_LINK);
        }
    }

    public function get_all_pans($start_limit = 0, $end_limit = 12, $isboosted = 0) {
        $all_pans = array();
        $pans = $this->Pans_m->get_all_pans($this->userId, $start_limit, $end_limit, $isboosted);
        //add pans images and payment options
        foreach ($pans as $key => $pan) {

            $products_id = (int) $pan['products_id'];

            $boost_data = $this->Pans_m->is_pans_boosted($products_id);

            $pan['is_boosted'] = 0;
            if (isset($boost_data) && is_array($boost_data) && sizeof($boost_data) > 0 && $boost_data['status'] == 1) {
                $pan['is_boosted'] = 1;
                $pan['boost_data'] = $boost_data;
            }

            $pan_main_image = $this->Common_m->get_products_images($pan['products_id'], true);
            $pan['image'] = current($pan_main_image);

            $pan_other_images = $this->Common_m->get_products_images($pan['products_id'], false);
            $pan['other_image'] = $pan_other_images;

            $all_pans [] = $pan;
        }
        return $all_pans;
    }

    public function pans_management() {
        //third parameter is for product is oosted or not (0 for all product) (1 for is boosted) (2 for not boosted)
        $all_pans = self::get_all_pans(0, 12, 0);
        $data['customer_audience'] = $this->get_customers_audience();
        $data['pans'] = $all_pans;
        $data['title'] = PANS_MANAGEMENT_TITLE;
        $this->load->template('pans_management', $data);
    }

    public function pans_management_boosted() {
        //third parameter is for product is oosted or not (0 for all product) (1 for is boosted) (2 for not boosted)
        $all_pans = self::get_all_pans(0, 12, 1);
        $data['customer_audience'] = $this->get_customers_audience();
        $data['pans'] = $all_pans;
        $data['title'] = PANS_MANAGEMENT_BOOSTED_TITLE;
        $this->load->template('pans_management_boosted', $data);
    }

    public function pans_management_notboosted() {
        //third parameter is for product is oosted or not (0 for all product) (1 for is boosted) (2 for not boosted)
        $all_pans = self::get_all_pans(0, 12, 2);
        $data['customer_audience'] = $this->get_customers_audience();
        $data['pans'] = $all_pans;
        $data['title'] = PANS_MANAGEMENT_NOTBOOSTED_TITLE;
        $this->load->template('pans_management_notboosted', $data);
    }

    public function load_more_pans() {
        $pans_html = '';
        if ($_REQUEST['start_limit'] && $_REQUEST['start_limit'] != '') {

            $start_limit = (int) $_REQUEST['start_limit'];

            $more_pans = self::get_all_pans($start_limit, 12, $_REQUEST['isboosted']);

            foreach ($more_pans as $key => $pan) {

                $pan_image = $pan['image']['image_path'];
                $products_price = $pan['products_price'];
                $products_descriptions = $pan['products_descriptions'];
                $created_date = strtotime($pan['created_date']);
                $created_date = date('F d, Y', $created_date);

                $productsId = $pan['products_id'];

                $is_boosted = $pan['is_boosted'];


                $filter_class = 'notboosted';
                $boosted_button = '<a href="#" pans-id = ' . $productsId . ' class="btn btn-blue btn-sm bost-btn btn-half-width boost_pans">Boost</a>';

                if ($is_boosted) {
                    $filter_class = 'boosted';
                    $boostId = $pan['boost_data']['boost_id'];
                    $boosted_button = '<a href="#" pans-id = ' . $productsId . ' data-boostid =' . $boostId . ' class="btn btn-grey-light btn-sm bost-btn btn-half-width stop_boost_pans">Stop Boosting</a>';
                }

                $pans_html .= '<div class="col col-xl-3 col-lg-3 col-md-6 col-sm-12 col-12 sorting-item ' . $filter_class . '">
                    <div class="ui-block">
                        <!-- Post -->
                        <article class="hentry post has-post-thumbnail">
                            <a href="' . USER_SINGLE_DISCOVER_LINK . $pan['products_id'] . '">
                            <div class="post-thumb  mb-2">
                                <img class="boostpost-post-img" src="' . IMG_URL . 'medium/' . $pan_image . '" alt="photo">
                            </div></a>
                            <div class="block-with-text">' . $products_descriptions . '</div>

                            <a href="' . USER_SINGLE_DISCOVER_LINK . $pan['products_id'] . '" class="h4 post-title ">US ' . $products_price . '$/Pieces</a>
                            <div class="post__date ">
                                <time class="published mt-2" datetime="2017-03-24T18:18">
                                   ' . $created_date . '
                                </time>
                                <div class="setting-inline">
                                    <div class="dropdown">
                                        <a id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-sliders-h pr-2"></i>
                                        </a>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item" href="' . USER_EDIT_PANS_LINK . $pan['products_id'] . '">Edit</a>
                                            <a class="dropdown-item" href="' . USER_DELETE_PANS_LINK . $pan['products_id'] . '" onclick="return confirm(\'Are you sure you want to delete?\')">Delete</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="post-additional-info">
                                ' . $boosted_button . '
                                <a href="' . USER_BOOST_PRODUCTS_DETAIL_LINK . $productsId . '" class="btn btn-purple  btn-sm bost-btn btn-half-width">History</a>
                            </div>
                        </article>
                        <!-- ... end Post -->
                    </div>
                </div>';
            }
        }
        echo $pans_html;
    }

    public function get_customers_audience() {
        $audience = $this->Common_m->get_customers_audience($this->userId);

        return $audience;
    }

}
