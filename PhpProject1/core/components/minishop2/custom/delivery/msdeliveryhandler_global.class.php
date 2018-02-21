<?php
if(!class_exists('msDeliveryInterface')) {
    require_once dirname(dirname(dirname(__FILE__))) . '/model/minishop2/msdeliveryhandler.class.php';
}

# тут вся логика
if(!class_exists('DC')) {
    require_once dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/core/_ext/delivery.class.php';
}


class msDeliveryHandlerGlobal extends msDeliveryHandler implements msDeliveryInterface{

    public function getCost(msOrderInterface $order, msDelivery $delivery, $cost = 0) {
		
		$orderData = $order->get();
		$cartData = $this->ms2->cart->get();
		#print_r($orderData);

        if(!$orderData['city'] || !$orderData['index']) {
            $errors = array(
                'message' => 'заполните поля город и почтовый индекс'
                ,'requires' => array('city','index')
            );
        }
        else {
            $DC = new DC($this->modx, $cartData, $orderData, $delivery);
            $delivery_data = $DC->run();
    		$cost = $cost + $delivery_data['delivery_cost'];
        }
        
        if(isset($errors)) {
            return array('cost' => -1, 'message' => $errors['message'], 'requires' => $errors['requires']);
        }
        
        return array_merge(array('cost'=>$cost),$delivery_data);
    }
   
    
    # заодно можно переопределить getNum - формирование номера заказа
    /*public function getNum()  {       
        
    }*/
    
    public function success($message = '', $data = array(), $placeholders = array()) {
        if (empty($this->ms2)) {
            $this->ms2 = $this->modx->getService('miniShop2');
        }

        return $this->ms2->success($message, $data, $placeholders);
    }
} 
?>